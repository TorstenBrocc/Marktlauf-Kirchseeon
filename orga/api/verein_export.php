<?php
/**
 * CSV-Export Vereine & Laufevents (GET). UTF-8 BOM + ';'-Delimiter (Excel-freundlich).
 * Spaltensatz = Import-Format (Round-Trip). Respektiert kategorie/status-Filter.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/verein_status.php';

$filterKategorie = $_GET['kategorie'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$pdo = getDbConnection();

$sql = 'SELECT * FROM vereine';
$where = [];
$params = [];
if (in_array($filterKategorie, ['verein', 'laufevent'], true)) {
    $where[] = 'kategorie = :kategorie';
    $params['kategorie'] = $filterKategorie;
}
if ($filterStatus !== '' && vereinStatusValid($filterStatus)) {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
}
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY kategorie ASC, name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = 'vereine_export_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

$cols = ['KATEGORIE', 'NAME', 'VERANSTALTER', 'ORT', 'ENTFERNUNG', 'RELEVANZ', 'TERMIN',
         'ANREDE', 'VORNAME', 'NACHNAME', 'FUNKTION', 'EMAIL', 'TELEFON', 'ANSCHRIFT',
         'WEBSITE', 'SOCIAL', 'QUELLE', 'HINWEIS', 'STATUS'];
fputcsv($out, $cols, ';');

$map = [
    'KATEGORIE' => 'kategorie', 'NAME' => 'name', 'VERANSTALTER' => 'veranstalter', 'ORT' => 'ort',
    'ENTFERNUNG' => 'entfernung', 'RELEVANZ' => 'relevanz', 'TERMIN' => 'termin',
    'ANREDE' => 'anrede', 'VORNAME' => 'vorname', 'NACHNAME' => 'nachname', 'FUNKTION' => 'funktion',
    'EMAIL' => 'email', 'TELEFON' => 'telefon', 'ANSCHRIFT' => 'anschrift', 'WEBSITE' => 'website',
    'SOCIAL' => 'social', 'QUELLE' => 'quelle', 'HINWEIS' => 'hinweis', 'STATUS' => 'status',
];

while ($row = $stmt->fetch()) {
    $line = [];
    foreach ($cols as $c) {
        $line[] = (string) ($row[$map[$c]] ?? '');
    }
    fputcsv($out, $line, ';');
}
fclose($out);
exit;
