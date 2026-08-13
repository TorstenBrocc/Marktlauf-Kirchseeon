<?php
/**
 * Einmaliger Seed: Kontakt-Ergebnisse vom 12.08.2026 nachtragen (TT-Rückmeldung).
 * Grundlage: intern/sponsoren-abarbeitung-2026-08-12.md Teil A.
 *
 * Trägt nach, was TT am 12.08. tatsächlich getan hat — Status nur dort, wo wirklich
 * kommuniziert wurde. Ein erfolgloser Anrufversuch ist KEIN Anschreiben und lässt den
 * Status daher unverändert (nur Notiz + Wiedervorlage).
 *
 * Guards wie gehabt: zugesagt/bestaetigt/abgerechnet/bezahlt werden nicht angefasst.
 * Notizen werden angehängt (Marker-Check), nie überschrieben.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_kontakt_stand_0812.php"
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

const PROTECTED_STATUS = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'];

/**
 * @param array{status?:string, gesendet?:bool, wiedervorlage?:string, notiz:string, marker:string} $e
 */
function pflegeSponsor(PDO $pdo, int $id, string $firmaPrefix, array $e): void
{
    $st = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        return;
    }
    if (mb_stripos((string) $row['firma'], $firmaPrefix) !== 0) {
        echo "SKIP #{$id}: Firma-Guard verletzt (ist '{$row['firma']}')\n";
        return;
    }
    if (in_array((string) $row['status'], PROTECTED_STATUS, true)) {
        echo "SKIP #{$id} {$row['firma']}: Schutzstatus '{$row['status']}'\n";
        return;
    }

    $set = [];
    $params = [];

    if (isset($e['status']) && (string) $row['status'] !== $e['status']) {
        $set[] = 'status = :status';
        $params['status'] = $e['status'];
    }
    // gesendet_am nur setzen, wenn noch leer — ein Nachfassen soll das Datum des
    // Erstanschreibens nicht überschreiben (Historie bleibt in den Notizen).
    if (!empty($e['gesendet']) && empty($row['gesendet_am'])) {
        $set[] = 'gesendet_am = NOW()';
    }
    if (isset($e['wiedervorlage']) && empty($row['wiedervorlage'])) {
        $set[] = 'wiedervorlage = :wv';
        $params['wv'] = $e['wiedervorlage'];
    }
    if (mb_stripos((string) ($row['notizen'] ?? ''), $e['marker']) === false) {
        $set[] = 'notizen = :notizen';
        $params['notizen'] = (trim((string) ($row['notizen'] ?? '')) === ''
            ? '' : rtrim((string) $row['notizen']) . "\n\n") . $e['notiz'];
    }

    if ($set === []) {
        echo "OK  #{$id} {$row['firma']}: bereits auf Stand\n";
        return;
    }
    $params['id'] = $id;
    $pdo->prepare('UPDATE sponsors SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    echo "UPD #{$id} {$row['firma']}: " . implode(', ', array_map(static fn ($s) => explode(' ', $s)[0], $set)) . "\n";
}

// --- 1 · BKK Landesverband Bayern: Türöffner-Mail raus ----------------------
pflegeSponsor($pdo, 97, 'BKK Landesverband Bayern', [
    'status'   => 'angefragt',
    'gesendet' => true,
    'notiz'    => 'TT 12.08.2026: Türöffner-Mail an info@bkk-lv-bayern.de raus (Bitte um Weiterleitung an regional engagierte Mitgliedskassen).',
    'marker'   => 'Türöffner-Mail an info@bkk-lv-bayern.de raus',
]);

// --- 2 · Allianz Generalvertretung Gillhuber: Anruf ohne Erfolg -------------
// Kein Status-Wechsel: ein nicht zustande gekommener Anruf ist kein Anschreiben.
pflegeSponsor($pdo, 80, 'Allianz', [
    'wiedervorlage' => '2026-08-14',
    'notiz'         => 'TT 12.08.2026: Generalvertretung Gillhuber e.K. (08091 5383836) angerufen — niemand erreicht. Erneut versuchen; Zusage müsste bis 30.08. stehen (Druck der Werbemittel).',
    'marker'        => 'Gillhuber e.K. (08091 5383836) angerufen — niemand erreicht',
]);

// --- 4 · RVB Ebersberg: nachgefasst, neues Erstanschreiben an Fr. Straßmaier -
pflegeSponsor($pdo, 7, 'Raiffeisen-Volksbank Ebersberg', [
    'status'        => 'in_klaerung',
    'wiedervorlage' => '2026-08-19',
    'notiz'         => 'TT 12.08.2026: Nachgefasst — Ansprechpartnerin ist Frau Straßmaier. Sie hatte die erste Mail (08.07.) nie erhalten, daher neues Erstanschreiben rausgeschickt. Sie schaut es sich an, braucht aber vermutlich einen Moment. Offen im Gespräch mitnehmen: Spendenaktion „Unterstützung für die Heimat" (Auswahl über die Ersten Bürgermeister), VR-Gewinnsparverein, VR-Förderpreis, Schwäbisch Hall.',
    'marker'        => 'Ansprechpartnerin ist Frau Straßmaier',
]);

// --- 5 · EBERwerk: auf Anrufbeantworter gesprochen --------------------------
pflegeSponsor($pdo, 3, 'EBERwerk', [
    'wiedervorlage' => '2026-08-18',
    'notiz'         => 'TT 12.08.2026: Auf den Anrufbeantworter gesprochen (nach der Nachfass-Mail an servus@eberwerk.de). Wenn bis zur Wiedervorlage keine Reaktion: erneut anrufen.',
    'marker'        => 'Auf den Anrufbeantworter gesprochen',
]);

// --- 6 · DSGV-Sportförderung + LBS Bayern: über die KSK-Kontakte ------------
$verbund = 'TT 12.08.2026: Mail an Herrn Baier (KSK MSE, michael.baier@kskmse.de) raus — Frage nach zusätzlicher Unterstützung über den Sparkassen-Verbund. Läuft ausschließlich über die Sparkasse, kein eigener Antragsweg.';
pflegeSponsor($pdo, 76, 'Sparkassen-Finanzgruppe', [
    'status'   => 'angefragt',
    'gesendet' => true,
    'notiz'    => $verbund,
    'marker'   => 'Mail an Herrn Baier (KSK MSE',
]);
pflegeSponsor($pdo, 82, 'LBS Bayern', [
    'status'   => 'angefragt',
    'gesendet' => true,
    'notiz'    => $verbund,
    'marker'   => 'Mail an Herrn Baier (KSK MSE',
]);

// --- 3 · Rathaus-Weg vorbereitet (RVB-Spendenaktion + Bayernwerk) -----------
pflegeSponsor($pdo, 110, 'Bayernwerk', [
    'notiz'  => 'Weg steht: über das Rathaus Kirchseeon den zuständigen Kommunalbetreuer erfragen (Bayernwerk hat kein Antragsportal). Guter Draht ins Rathaus vorhanden — Erster Bürgermeister Jan Paeplow, 08091 552-10, jan.paeplow@kirchseeon.de.',
    'marker' => 'zuständigen Kommunalbetreuer erfragen',
]);

// --- Krankenkassen-Absagen (TT-Rückmeldung 12./13.08.) ---------------------
// Begruendung wortgleich wie von TT genannt. Barmer behaelt seinen offenen Faden
// (moegliche Rueckmeldung wg. Praeventionsprogramm) sichtbar in der Notiz — TTs
// eigener Eintrag wird dabei nicht ueberschrieben, sondern ergaenzt.
$absage = 'Absage 12.08.2026: Sponsoring als Körperschaft öffentlichen Rechts grundsätzlich untersagt. Kein Verhandlungsspielraum — Hintergrund und Alternativwege: intern/sponsoren-abarbeitung-2026-08-12.md Teil C.';

pflegeSponsor($pdo, 85, 'Techniker Krankenkasse', [
    'status' => 'abgelehnt',
    'notiz'  => $absage,
    'marker' => 'Sponsoring als Körperschaft öffentlichen Rechts grundsätzlich untersagt',
]);
pflegeSponsor($pdo, 87, 'DAK-Gesundheit', [
    'status' => 'abgelehnt',
    'notiz'  => $absage,
    'marker' => 'Sponsoring als Körperschaft öffentlichen Rechts grundsätzlich untersagt',
]);
pflegeSponsor($pdo, 86, 'Barmer', [
    'status' => 'abgelehnt',
    'notiz'  => $absage . ' ACHTUNG offener Faden (siehe Notiz TT 12.08.): mögliche Rückmeldung von anderer Stelle wegen Präventionsprogramm. Falls die kommt, Eintrag wieder öffnen — die Präventionsschiene ist rechtlich der einzige Weg, auf dem von einer Kasse Mittel fließen können.',
    'marker' => 'Sponsoring als Körperschaft öffentlichen Rechts grundsätzlich untersagt',
]);

// --- Ansprechpartnerin Frau Straßmaier bei der RVB anlegen -----------------
$st = $pdo->prepare("SELECT COUNT(*) FROM sponsor_ansprechpartner WHERE sponsor_id = 7 AND nachname = 'Straßmaier'");
$st->execute();
if ((int) $st->fetchColumn() === 0) {
    $pdo->prepare("
        INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, nachname, telefon)
        VALUES (7, 'Frau', 'Straßmaier', '08092 701-0')
    ")->execute();
    echo "AP  #7: Frau Straßmaier angelegt\n";
} else {
    echo "OK  #7: Frau Straßmaier bereits angelegt\n";
}

echo "Fertig.\n";
