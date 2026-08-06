<?php
/**
 * Liefert die Unterordner eines Ordners im geteilten Laufwerk (JSON) — für das
 * lazy-Aufklappen des Ordnerbaums im Dateien-Browser. Nur eingeloggte Orga/Admin.
 * Sicherheit: nur Ordner innerhalb des geteilten Laufwerks.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

$parent = trim((string) ($_GET['parent'] ?? ''));

if ($parent === '' || !driveConfigured()) {
    echo json_encode(['ok' => false, 'folders' => []]);
    exit;
}

$meta = driveFileMeta($parent);
if ($meta === null
    || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()
    || (string) ($meta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
    echo json_encode(['ok' => false, 'folders' => []]);
    exit;
}

try {
    $folders = [];
    foreach (driveListChildren($parent) as $c) {
        if ($c['isFolder']) {
            $folders[] = ['id' => $c['id'], 'name' => $c['name']];
        }
    }
    echo json_encode(['ok' => true, 'folders' => $folders], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logError('folder_children: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'folders' => []]);
}
