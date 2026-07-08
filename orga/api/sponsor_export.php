<?php
/**
 * Sponsor CSV-Export (GET)
 * Grundlage: intern/sponsor-crm-ausbau.md §4
 *
 * Spiegelbildlich zum Import (Round-Trip / Backup / Brevo-Suppression).
 * Respektiert die Filter status/paket wie die Übersicht.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/sponsor_status.php';

$filterStatus = $_GET['status'] ?? '';
$filterPaket = $_GET['paket'] ?? '';

$pdo = getDbConnection();

$sql = 'SELECT * FROM sponsors';
$where = [];
$params = [];

if ($filterStatus !== '' && sponsorStatusValid($filterStatus)) {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
}
if ($filterPaket !== '' && in_array($filterPaket, ['hauptsponsor', 'gold', 'silber', 'bronze'], true)) {
    $where[] = 'paket = :paket';
    $params['paket'] = $filterPaket;
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY firma ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sponsoren = $stmt->fetchAll();

// Ersten Ansprechpartner je Sponsor vorladen
$apBySponsor = [];
try {
    $apStmt = $pdo->query('SELECT sponsor_id, anrede, nachname, email FROM sponsor_ansprechpartner ORDER BY sponsor_id, id');
    while ($row = $apStmt->fetch()) {
        if (!isset($apBySponsor[$row['sponsor_id']])) {
            $apBySponsor[$row['sponsor_id']] = $row;
        }
    }
} catch (PDOException $e) {
    // Tabelle evtl. nicht vorhanden
}

$paketLabel = static fn (?string $p): string => match ($p) {
    'gold'         => 'Gold',
    'silber'       => 'Silber',
    'bronze'       => 'Bronze',
    'hauptsponsor' => 'Hauptsponsor',
    default        => '',
};
$prioLabel = static fn (?string $p): string => match ((string) $p) {
    '1'     => 'Hoch',
    '2'     => 'Mittel',
    '3'     => 'Niedrig',
    default => '',
};

$filename = 'sponsoren_export_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// BOM für Excel (UTF-8)
fwrite($out, "\xEF\xBB\xBF");

$columns = ['COMPANY', 'TIER_VORSCHLAG', 'EMAIL', 'ANREDE', 'LASTNAME', 'PRIORITAET', 'ORT', 'GESENDET', 'STATUS', 'SUMME', 'NOTIZEN'];
fputcsv($out, $columns, ';');

foreach ($sponsoren as $s) {
    $ap = $apBySponsor[$s['id']] ?? null;
    fputcsv($out, [
        $s['firma'],
        $paketLabel($s['paket']),
        $ap['email'] ?? '',
        $ap['anrede'] ?? '',
        $ap['nachname'] ?? '',
        $prioLabel($s['prioritaet'] ?? null),
        $s['ort'] ?? '',
        !empty($s['gesendet_am']) ? 'Ja' : 'Nein',
        sponsorStatusLabel($s['status']),
        $s['summe'] !== null ? number_format((float) $s['summe'], 2, '.', '') : '',
        $s['notizen'] ?? '',
    ], ';');
}

fclose($out);
exit;
