<?php
/**
 * Sponsoring-Bedingungen-PDF ausliefern (Vorschau im Briefvorlagen-Editor).
 * Rendert deterministisch aus dem Code (kein Datei-Cache); Jahr aus driveRennJahr().
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/sponsor_bedingungen_pdf.php';

$bytes = sponsorBedingungenPdfBytes();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Sponsoring-Bedingungen.pdf"');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
echo $bytes;
