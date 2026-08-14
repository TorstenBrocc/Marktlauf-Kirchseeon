<?php
/**
 * Sponsor-Stammdaten-Vollexport (GET) — ALLE Spalten der sponsors-Tabelle als CSV.
 * Grundlage: intern/sponsor-crm-ausbau.md §4 (ergänzt den schlanken Round-Trip-Export
 * sponsor_export.php). Liefert wirklich alle Stammdatenfelder (u. a. website, branche,
 * foerdergruppe, logo_web_asset, Rechnungsfelder) plus den ersten Ansprechpartner.
 * Nur Admin/Orga. Respektiert die Filter status/paket wie die Übersicht.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/sponsor_status.php';
require_once __DIR__ . '/../../src/helpers.php';

$filterStatus = $_GET['status'] ?? '';
$filterPaket  = $_GET['paket'] ?? '';

$pdo = getDbConnection();

$sql    = 'SELECT * FROM sponsors';
$where  = [];
$params = [];

if ($filterStatus !== '' && sponsorStatusValid($filterStatus)) {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
}
if ($filterPaket !== '' && in_array($filterPaket, ['hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'], true)) {
    $where[] = 'paket = :paket';
    $params['paket'] = $filterPaket;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY firma ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Kopfzeile dynamisch aus den echten Spaltennamen — steht auch bei 0 Treffern.
$spaltenNamen = [];
for ($i = 0, $n = $stmt->columnCount(); $i < $n; $i++) {
    $meta = $stmt->getColumnMeta($i);
    $spaltenNamen[] = (string) ($meta['name'] ?? ('spalte_' . $i));
}
$sponsoren = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ersten Ansprechpartner je Sponsor vorladen (SELECT * = robust gegen fehlende Spalten).
$apBySponsor = [];
try {
    $apStmt = $pdo->query('SELECT * FROM sponsor_ansprechpartner ORDER BY sponsor_id, id');
    while ($row = $apStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($apBySponsor[$row['sponsor_id']])) {
            $apBySponsor[$row['sponsor_id']] = $row;
        }
    }
} catch (PDOException $e) {
    // Tabelle evtl. nicht vorhanden — Export läuft ohne Ansprechpartner weiter.
}
$apFelder = ['anrede', 'vorname', 'nachname', 'funktion', 'telefon', 'email'];

$filename = 'sponsoren_stammdaten_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
content_disposition($filename);
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM für Excel (UTF-8)

// Kopf: alle sponsors-Spalten (Großschreibung) + AP_-Präfix für den Ansprechpartner.
$kopf = array_map('strtoupper', $spaltenNamen);
foreach ($apFelder as $f) {
    $kopf[] = 'AP_' . strtoupper($f);
}
fputcsv($out, $kopf, ';');

foreach ($sponsoren as $s) {
    $zeile = [];
    foreach ($spaltenNamen as $spalte) {
        $wert = $s[$spalte] ?? '';
        $zeile[] = $wert === null ? '' : (string) $wert;
    }
    $ap = $apBySponsor[$s['id'] ?? 0] ?? null;
    foreach ($apFelder as $f) {
        $zeile[] = $ap[$f] ?? '';
    }
    fputcsv($out, $zeile, ';');
}

fclose($out);
exit;
