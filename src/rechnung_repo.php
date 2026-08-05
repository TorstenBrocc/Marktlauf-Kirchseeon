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
 * Baut aus einer Sponsor-Zeile den Rechnungs-Snapshot (inkl. Beträge und
 * Leistungs-Fallbacks). Wirft InvalidArgumentException, wenn Pflichtdaten fehlen.
 */
function rechnungSnapshotVonSponsor(array $sponsor): array
{
    $firma = trim((string) ($sponsor['rechnung_firma'] ?? '')) !== ''
        ? trim((string) $sponsor['rechnung_firma'])
        : trim((string) ($sponsor['firma'] ?? ''));

    $strasse = trim((string) ($sponsor['rechnung_strasse'] ?? ''));
    $plz     = trim((string) ($sponsor['rechnung_plz'] ?? ''));
    $ort     = trim((string) ($sponsor['rechnung_ort'] ?? ''));

    $netto = (float) ($sponsor['summe'] ?? 0);

    // Pflichtprüfungen für eine gültige Rechnung
    $fehlt = [];
    if ($firma === '')                 { $fehlt[] = 'Firma/Empfänger'; }
    if ($strasse === '')               { $fehlt[] = 'Straße (Rechnungsanschrift)'; }
    if ($plz === '' || $ort === '')    { $fehlt[] = 'PLZ/Ort (Rechnungsanschrift)'; }
    if ($netto <= 0)                   { $fehlt[] = 'Summe (Netto)'; }
    if ($fehlt !== []) {
        throw new InvalidArgumentException(implode(', ', $fehlt));
    }

    $zeitraum = trim((string) ($sponsor['leistung_zeitraum'] ?? ''));
    if ($zeitraum === '') {
        $zeitraum = leistungszeitraumDefault();
    }
    $leistung = trim((string) ($sponsor['rechnung_leistung'] ?? ''));
    if ($leistung === '') {
        $leistung = paketLeistungDefault($sponsor['paket'] ?? null, $zeitraum);
    }

    $b = rechnungBetraege($netto);

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

    $snap = rechnungSnapshotVonSponsor($sponsor);

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

    return [
        'id'       => (int) $pdo->lastInsertId(),
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
