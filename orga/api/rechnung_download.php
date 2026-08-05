<?php
/**
 * Rechnungs-PDF ausliefern. GET ?id=NN
 * Rendert das PDF deterministisch aus dem gespeicherten Snapshot (kein Datei-Cache).
 * Entwürfe (ohne Nummer) und nummerierte Rechnungen sind beide abrufbar.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/rechnung_repo.php';
require_once __DIR__ . '/../../src/rechnung_pdf.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

try {
    $pdo = getDbConnection();
    $row = rechnungLaden($pdo, $id);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankfehler.');
}

if ($row === null) {
    http_response_code(404);
    exit('Rechnung nicht gefunden.');
}

$nummer   = (string) ($row['rechnungsnummer'] ?? '');
$snapshot = rechnungSnapshotAusRow($row);
$bytes    = rechnungPdfErzeugen($snapshot, $nummer);

$firmaSafe = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($row['empfaenger_firma'] ?? 'Rechnung'));
$name = 'Rechnung_' . ($nummer !== '' ? $nummer : 'Entwurf') . '_' . $firmaSafe . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
echo $bytes;
