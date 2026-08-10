<?php
/**
 * Sponsoring-Rechnungen — Datenzugriff (Entwurf erstellen, Nummer vergeben, laden).
 *
 * Eine Rechnung friert beim Erstellen einen SNAPSHOT der Sponsordaten ein; spätere
 * Änderungen am Sponsor verändern die Rechnung nicht mehr. Die fortlaufende Nummer
 * bleibt NULL (Entwurf), bis der Kassier sie vergibt.
 */

declare(strict_types=1);

require_once __DIR__ . '/rechnung.php';

/**
 * Baut aus einer Sponsor-Zeile den Rechnungs-Snapshot. $pakete ist die vollständige
 * Paket-Definition (aus sponsoringPakete()) — nicht nur das gebuchte Paket, weil der
 * Leistungstext die kleineren Stufen mit ausschreibt. Leistung + Betrag kommen aus dem Paket,
 * sofern nicht pro Sponsor überschrieben. Wirft InvalidArgumentException bei fehlenden Pflichtdaten.
 */
function rechnungSnapshotVonSponsor(array $sponsor, array $pakete = [], bool $istBrutto = false): array
{
    $paketKey = trim((string) ($sponsor['paket'] ?? ''));
    $paketDef = $pakete[$paketKey] ?? [];

    $firma = trim((string) ($sponsor['rechnung_firma'] ?? '')) !== ''
        ? trim((string) $sponsor['rechnung_firma'])
        : trim((string) ($sponsor['firma'] ?? ''));

    $strasse = trim((string) ($sponsor['rechnung_strasse'] ?? ''));
    $plz     = trim((string) ($sponsor['rechnung_plz'] ?? ''));
    $ort     = trim((string) ($sponsor['rechnung_ort'] ?? ''));

    // Pflichtprüfungen (Adresse + Betrag) sammeln
    $fehlt = [];
    if ($firma === '')              { $fehlt[] = 'Firma/Empfänger'; }
    if ($strasse === '')            { $fehlt[] = 'Straße (Rechnungsanschrift)'; }
    if ($plz === '' || $ort === '') { $fehlt[] = 'PLZ/Ort (Rechnungsanschrift)'; }

    try {
        $b = rechnungBetraegeFuerSponsor($sponsor, $paketDef, null, $istBrutto);
    } catch (InvalidArgumentException $e) {
        $fehlt[] = $e->getMessage();
        $b = null;
    }

    if ($fehlt !== []) {
        throw new InvalidArgumentException(implode(', ', $fehlt));
    }

    $zeitraum = trim((string) ($sponsor['leistung_zeitraum'] ?? ''));
    if ($zeitraum === '') {
        $zeitraum = leistungszeitraumDefault();
    }
    $leistung = trim((string) ($sponsor['rechnung_leistung'] ?? ''));
    if ($leistung === '') {
        $leistung = paketLeistung($pakete, $paketKey, $zeitraum);
    }

    return [
        'empfaenger_firma'   => $firma,
        'empfaenger_strasse' => $strasse,
        'empfaenger_plz'     => $plz,
        'empfaenger_ort'     => $ort,
        'leistung'           => $leistung,
        'zeitraum'           => $zeitraum,
        'netto'              => $b['netto'],
        'ust_satz'           => $b['ust_satz'],
        'ust_betrag'         => $b['ust_betrag'],
        'brutto'             => $b['brutto'],
    ];
}

/**
 * Legt einen Rechnungsentwurf für einen Sponsor an. Rückgabe:
 * ['id'=>int, 'snapshot'=>array, 'sponsor'=>array]. Wirft InvalidArgumentException
 * (Pflichtdaten fehlen) oder RuntimeException (Sponsor nicht gefunden).
 */
function rechnungEntwurfErstellen(PDO $pdo, int $sponsorId, ?int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $stmt->execute(['id' => $sponsorId]);
    $sponsor = $stmt->fetch();
    if (!$sponsor) {
        throw new RuntimeException('Sponsor nicht gefunden.');
    }

    $pakete = sponsoringPakete($pdo);
    // Paketpreise sind immer netto; nur der Pro-Sponsor-Haken schaltet diese Rechnung auf brutto.
    $istBrutto = ((int) ($sponsor['rechnung_betrag_brutto'] ?? 0) === 1);
    $snap      = rechnungSnapshotVonSponsor($sponsor, $pakete, $istBrutto);

    $ins = $pdo->prepare('
        INSERT INTO sponsor_rechnungen
            (sponsor_id, empfaenger_firma, empfaenger_strasse, empfaenger_plz, empfaenger_ort,
             leistung, zeitraum, netto, ust_satz, ust_betrag, brutto, status, erstellt_von)
        VALUES
            (:sponsor_id, :empfaenger_firma, :empfaenger_strasse, :empfaenger_plz, :empfaenger_ort,
             :leistung, :zeitraum, :netto, :ust_satz, :ust_betrag, :brutto, \'entwurf\', :erstellt_von)
    ');
    $ins->execute([
        'sponsor_id'         => $sponsorId,
        'empfaenger_firma'   => $snap['empfaenger_firma'],
        'empfaenger_strasse' => $snap['empfaenger_strasse'],
        'empfaenger_plz'     => $snap['empfaenger_plz'],
        'empfaenger_ort'     => $snap['empfaenger_ort'],
        'leistung'           => $snap['leistung'],
        'zeitraum'           => $snap['zeitraum'],
        'netto'              => $snap['netto'],
        'ust_satz'           => $snap['ust_satz'],
        'ust_betrag'         => $snap['ust_betrag'],
        'brutto'             => $snap['brutto'],
        'erstellt_von'       => $userId,
    ]);

    $neueId = (int) $pdo->lastInsertId();

    // Sponsor als abgerechnet markieren (aber ein bereits bezahlter bleibt bezahlt).
    try {
        $pdo->prepare("UPDATE sponsors SET status = 'abgerechnet' WHERE id = :id AND status <> 'bezahlt'")
            ->execute(['id' => $sponsorId]);
    } catch (PDOException $e) {
        // Status-ENUM evtl. noch nicht migriert -> Status unverändert lassen
    }

    return [
        'id'       => $neueId,
        'snapshot' => $snap,
        'sponsor'  => $sponsor,
    ];
}

/** Lädt eine Rechnung (inkl. Sponsor-Firma). */
function rechnungLaden(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT r.*, s.firma AS sponsor_firma
        FROM sponsor_rechnungen r
        LEFT JOIN sponsors s ON s.id = r.sponsor_id
        WHERE r.id = :id
    ');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Alle Rechnungen, neueste zuerst; optional nach Status gefiltert. */
function rechnungenListe(PDO $pdo, string $status = ''): array
{
    $sql = '
        SELECT r.*, s.firma AS sponsor_firma,
               eu.name AS erstellt_name, nu.name AS nummer_name
        FROM sponsor_rechnungen r
        LEFT JOIN sponsors s ON s.id = r.sponsor_id
        LEFT JOIN users eu ON eu.id = r.erstellt_von
        LEFT JOIN users nu ON nu.id = r.nummer_von
    ';
    $params = [];
    if ($status === 'entwurf' || $status === 'nummeriert') {
        $sql .= ' WHERE r.status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY r.status = \'nummeriert\', r.erstellt_am DESC, r.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Vergibt die fortlaufende Rechnungsnummer. Validiert Format und Doppelvergabe.
 * Wirft InvalidArgumentException (Format), RuntimeException (nicht gefunden /
 * schon nummeriert / Nummer vergeben).
 */
function rechnungNummerVergeben(PDO $pdo, int $id, string $nummer, ?int $userId): void
{
    $nummer = trim($nummer);
    if (!rechnungsnummerGueltig($nummer)) {
        throw new InvalidArgumentException('Ungültiges Format. Erwartet: NN-JJJJ, z. B. 05-2026.');
    }

    $r = rechnungLaden($pdo, $id);
    if ($r === null) {
        throw new RuntimeException('Rechnung nicht gefunden.');
    }
    if ($r['status'] === 'nummeriert') {
        throw new RuntimeException('Diese Rechnung hat bereits die Nummer ' . $r['rechnungsnummer'] . '.');
    }

    // Doppelvergabe vorab prüfen (DB-UNIQUE fängt Race zusätzlich ab)
    $dup = $pdo->prepare('SELECT id FROM sponsor_rechnungen WHERE rechnungsnummer = :n AND id <> :id');
    $dup->execute(['n' => $nummer, 'id' => $id]);
    if ($dup->fetch()) {
        throw new RuntimeException('Die Nummer ' . $nummer . ' ist bereits vergeben.');
    }

    try {
        $upd = $pdo->prepare('
            UPDATE sponsor_rechnungen
            SET rechnungsnummer = :n, status = \'nummeriert\', nummer_von = :uid, nummer_am = NOW()
            WHERE id = :id AND status = \'entwurf\'
        ');
        $upd->execute(['n' => $nummer, 'uid' => $userId, 'id' => $id]);
    } catch (PDOException $e) {
        // UNIQUE-Verletzung o. Ä.
        throw new RuntimeException('Die Nummer ' . $nummer . ' konnte nicht vergeben werden (evtl. bereits vergeben).');
    }
}

/**
 * Verwirft eine Rechnung endgültig — Entwurf oder nummeriert, solange sie nie beim Sponsor
 * gelandet ist. Eine vergebene Nummer wird damit wieder frei (die UNIQUE-Zeile verschwindet),
 * was genau der Sinn der Aktion ist: eine noch nicht versendete Nummer darf neu verwendet
 * werden, ein Nummernloch wäre die schlechtere Buchführung.
 *
 * Räumt vollständig auf: Protokollzeilen gehen per ON DELETE CASCADE (Migration 043) mit, und
 * der Sponsor fällt von 'abgerechnet' auf 'bestaetigt' zurück, damit er wieder unter
 * „Abzurechnen" auftaucht. 'bezahlt' bleibt unangetastet — Geld ist geflossen.
 *
 * Wirft RuntimeException, wenn die Rechnung fehlt oder schon erfolgreich versendet wurde.
 * @return array{nummer:string, firma:string, status_zurueck:bool}
 */
function rechnungVerwerfen(PDO $pdo, int $id): array
{
    $r = rechnungLaden($pdo, $id);
    if ($r === null) {
        throw new RuntimeException('Rechnung nicht gefunden.');
    }

    foreach (rechnungVersandHistorie($pdo, $id) as $h) {
        if (($h['ergebnis'] ?? '') === 'ok') {
            throw new RuntimeException(
                'Diese Rechnung wurde am ' . date('d.m.Y', strtotime((string) $h['versendet_am']))
                . ' an ' . $h['empfaenger'] . ' versendet und kann nicht mehr verworfen werden. '
                . 'Eine versendete Rechnung wird storniert, nicht gelöscht.'
            );
        }
    }

    $sponsorId = (int) ($r['sponsor_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM sponsor_rechnungen WHERE id = :id')->execute(['id' => $id]);
        $statusZurueck = false;
        if ($sponsorId > 0) {
            $upd = $pdo->prepare("UPDATE sponsors SET status = 'bestaetigt' WHERE id = :id AND status = 'abgerechnet'");
            $upd->execute(['id' => $sponsorId]);
            $statusZurueck = $upd->rowCount() > 0;
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'nummer'         => (string) ($r['rechnungsnummer'] ?? ''),
        'firma'          => (string) ($r['empfaenger_firma'] ?? ''),
        'status_zurueck' => $statusZurueck,
    ];
}

/**
 * Mögliche Empfänger-Adressen für den Rechnungsversand eines Sponsors:
 * Rechnungs-E-Mail (falls gesetzt) + alle Ansprechpartner-E-Mails. Dedupliziert.
 * @return array<int,string>
 */
function rechnungSponsorEmails(PDO $pdo, int $sponsorId): array
{
    $mails = [];
    try {
        $s = $pdo->prepare('SELECT rechnung_email, email FROM sponsors WHERE id = :id');
        $s->execute(['id' => $sponsorId]);
        $row = $s->fetch();
        if ($row) {
            foreach (['rechnung_email', 'email'] as $c) {
                $m = trim((string) ($row[$c] ?? ''));
                if ($m !== '') { $mails[] = $m; }
            }
        }
        $ap = $pdo->prepare("SELECT email FROM sponsor_ansprechpartner WHERE sponsor_id = :id AND email <> ''");
        $ap->execute(['id' => $sponsorId]);
        foreach ($ap->fetchAll(PDO::FETCH_COLUMN) as $m) {
            $m = trim((string) $m);
            if ($m !== '') { $mails[] = $m; }
        }
    } catch (PDOException $e) {
        // Tabelle evtl. nicht vorhanden -> was da ist zurückgeben
    }
    // Dedup case-insensitiv, Reihenfolge erhalten
    $seen = [];
    $out = [];
    foreach ($mails as $m) {
        $k = strtolower($m);
        if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $m; }
    }
    return $out;
}

/** Eine Zeile ins fortlaufende Versand-Protokoll schreiben. */
function rechnungVersandLog(PDO $pdo, int $rechnungId, string $empfaenger, ?int $userId, ?string $driveFileId, string $ergebnis, ?string $hinweis = null): void
{
    $pdo->prepare('
        INSERT INTO rechnung_versand_log (rechnung_id, empfaenger, versendet_von, drive_datei_id, ergebnis, hinweis)
        VALUES (:rid, :empf, :uid, :drive, :erg, :hinweis)
    ')->execute([
        'rid'     => $rechnungId,
        'empf'    => mb_substr($empfaenger, 0, 255),
        'uid'     => $userId,
        'drive'   => $driveFileId,
        'erg'     => $ergebnis === 'fehler' ? 'fehler' : 'ok',
        'hinweis' => $hinweis !== null ? mb_substr($hinweis, 0, 500) : null,
    ]);
}

/** Versand-Historie einer Rechnung (neueste zuerst). @return array<int,array<string,mixed>> */
function rechnungVersandHistorie(PDO $pdo, int $rechnungId): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT l.*, u.name AS von_name
            FROM rechnung_versand_log l
            LEFT JOIN users u ON u.id = l.versendet_von
            WHERE l.rechnung_id = :rid
            ORDER BY l.versendet_am DESC, l.id DESC
        ');
        $stmt->execute(['rid' => $rechnungId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Jahr aus einer Rechnungsnummer NN-JJJJ; Fallback aktuelles Jahr. */
function rechnungJahrAusNummer(string $nummer): int
{
    if (preg_match('/-(\d{4})$/', trim($nummer), $m)) {
        return (int) $m[1];
    }
    return (int) date('Y');
}

/** Extrahiert aus einer geladenen Rechnungs-Zeile das Snapshot-Array für den Renderer. */
function rechnungSnapshotAusRow(array $row): array
{
    return [
        'empfaenger_firma'   => $row['empfaenger_firma'] ?? '',
        'empfaenger_strasse' => $row['empfaenger_strasse'] ?? '',
        'empfaenger_plz'     => $row['empfaenger_plz'] ?? '',
        'empfaenger_ort'     => $row['empfaenger_ort'] ?? '',
        'leistung'           => $row['leistung'] ?? '',
        'zeitraum'           => $row['zeitraum'] ?? '',
        'netto'              => (float) ($row['netto'] ?? 0),
        'ust_satz'           => (float) ($row['ust_satz'] ?? 19),
        'ust_betrag'         => (float) ($row['ust_betrag'] ?? 0),
        'brutto'             => (float) ($row['brutto'] ?? 0),
        'erstellt_am'        => $row['erstellt_am'] ?? null,
    ];
}
