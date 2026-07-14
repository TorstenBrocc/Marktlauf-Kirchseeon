<?php
/**
 * Sponsor vCard-Export (GET, read-only)
 * Exportiert alle Ansprechpartner als vCard 3.0 (.vcf) — importierbar in die
 * Handy-Kontakte. Eine VCARD je Ansprechpartner, mit Firma (ORG), Funktion
 * (TITLE), E-Mail und Telefon. Respektiert die Filter status/paket wie die
 * Übersicht (analog sponsor_export.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/sponsor_status.php';

$filterStatus = $_GET['status'] ?? '';
$filterPaket  = $_GET['paket'] ?? '';

$pdo = getDbConnection();

// Ansprechpartner + Firma; Filter greifen auf den zugehörigen Sponsor
$sql = 'SELECT ap.anrede, ap.vorname, ap.nachname, ap.funktion, ap.email, ap.telefon,
               s.firma
          FROM sponsor_ansprechpartner ap
          JOIN sponsors s ON s.id = ap.sponsor_id';
$where  = [];
$params = [];

if ($filterStatus !== '' && sponsorStatusValid($filterStatus)) {
    $where[] = 's.status = :status';
    $params['status'] = $filterStatus;
}
if ($filterPaket !== '' && in_array($filterPaket, ['hauptsponsor', 'gold', 'silber', 'bronze'], true)) {
    $where[] = 's.paket = :paket';
    $params['paket'] = $filterPaket;
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY s.firma ASC, ap.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kontakte = $stmt->fetchAll();

/** vCard-Feldwert escapen (RFC 6350 / 2426). */
function vcardEscape(string $v): string {
    $v = str_replace('\\', '\\\\', $v);
    $v = str_replace(["\r\n", "\n", "\r"], '\\n', $v);
    $v = str_replace(',', '\\,', $v);
    $v = str_replace(';', '\\;', $v);
    return $v;
}

$filename = 'sponsoren_kontakte_' . date('Y-m-d') . '.vcf';

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$eol = "\r\n"; // vCard verlangt CRLF

foreach ($kontakte as $k) {
    $vorname  = trim((string) $k['vorname']);
    $nachname = trim((string) $k['nachname']);
    $firma    = trim((string) $k['firma']);
    $email    = trim((string) $k['email']);
    $telefon  = trim((string) $k['telefon']);
    $funktion = trim((string) $k['funktion']);
    $anrede   = trim((string) $k['anrede']);

    // Leere Datensätze (weder Name noch Kontaktweg) überspringen
    if ($vorname === '' && $nachname === '' && $email === '' && $telefon === '') {
        continue;
    }

    $fn = trim($vorname . ' ' . $nachname);
    if ($fn === '') {
        $fn = $firma !== '' ? $firma : 'Kontakt';
    }

    echo 'BEGIN:VCARD' . $eol;
    echo 'VERSION:3.0' . $eol;
    // N: Nachname;Vorname;;Prefix(Anrede);
    echo 'N:' . vcardEscape($nachname) . ';' . vcardEscape($vorname) . ';;' . vcardEscape($anrede) . ';' . $eol;
    echo 'FN:' . vcardEscape($fn) . $eol;
    if ($firma !== '') {
        echo 'ORG:' . vcardEscape($firma) . $eol;
    }
    if ($funktion !== '') {
        echo 'TITLE:' . vcardEscape($funktion) . $eol;
    }
    if ($email !== '') {
        echo 'EMAIL;TYPE=WORK:' . vcardEscape($email) . $eol;
    }
    if ($telefon !== '') {
        echo 'TEL;TYPE=WORK,VOICE:' . vcardEscape($telefon) . $eol;
    }
    echo 'END:VCARD' . $eol;
}

exit;
