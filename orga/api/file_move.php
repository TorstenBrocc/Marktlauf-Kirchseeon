<?php
/**
 * Datei/Ordner im geteilten Google-Laufwerk verschieben (POST, JSON-Antwort).
 * Von Drag & Drop im Ordner-Browser genutzt. Sicherheit: Quelle und Ziel müssen
 * im Laufwerk liegen, das Ziel ein Ordner, kein Verschieben in sich selbst.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

function moveFail(string $msg): never
{
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    moveFail('Ungültige Anfrage.');
}
if (!driveConfigured()) {
    moveFail('Google Drive nicht konfiguriert.');
}

$fid    = trim((string) ($_POST['fid'] ?? ''));      // zu verschieben
$target = trim((string) ($_POST['target'] ?? ''));   // neuer Elternordner
$source = trim((string) ($_POST['source'] ?? ''));   // aktueller Elternordner

if ($fid === '' || $target === '' || $source === '' || $fid === $target) {
    moveFail('Ungültiges Verschiebe-Ziel.');
}

$fMeta = driveFileMeta($fid);
$tMeta = driveFileMeta($target);
if ($fMeta === null || (string) ($fMeta['driveId'] ?? '') !== driveSharedDriveId()) {
    moveFail('Element nicht gefunden.');
}
if ($tMeta === null
    || (string) ($tMeta['driveId'] ?? '') !== driveSharedDriveId()
    || (string) ($tMeta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
    moveFail('Zielordner ungültig.');
}

try {
    driveMove($fid, $target, $source);
    echo json_encode(['ok' => true]);
} catch (RuntimeException $e) {
    logError('file_move Drive: ' . $e->getMessage());
    moveFail('Verschieben fehlgeschlagen (evtl. in einen Unterordner von sich selbst?).');
}
