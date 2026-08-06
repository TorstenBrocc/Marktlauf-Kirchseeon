<?php
/**
 * Datei-Download aus dem geteilten Google-Laufwerk (GET) — nur eingeloggte Orga/Admin.
 * Adressiert per Drive-file-id (?fid=). Sicherheit: es werden ausschließlich Dateien
 * ausgeliefert, die IM geteilten Laufwerk liegen (sonst könnte man beliebige info@-Dateien
 * abrufen). Siehe intern/gdrive-storage-spec.md §2.5.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/google_drive.php';

$fid = trim((string) ($_GET['fid'] ?? ''));

if ($fid === '' || !driveConfigured()) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

// Security gate: only files inside our shared drive.
$meta = driveFileMeta($fid);
if ($meta === null || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

try {
    $bytes = driveDownload($fid);
} catch (RuntimeException $e) {
    http_response_code(502);
    exit('Datei konnte nicht von Google Drive geladen werden.');
}

$mime = (string) ($meta['mimeType'] ?? 'application/octet-stream');
$name = (string) ($meta['name'] ?? 'datei');

// inline=1: Bild direkt anzeigen (z. B. als <img>-Quelle des Bild-Pickers).
$inline = isset($_GET['inline']) && $_GET['inline'] === '1' && str_starts_with($mime, 'image/');

header('Content-Type: ' . $mime);
content_disposition($name, $inline ? 'inline' : 'attachment');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
echo $bytes;
