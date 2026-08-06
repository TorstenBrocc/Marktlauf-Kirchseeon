<?php
/**
 * Listet die Bilder aus dem designierten „Bilder-Ordner" des geteilten Laufwerks (JSON)
 * für den Bild-Picker der Share-Grafik/Vorlagen. Nur eingeloggte Orga/Admin.
 * Ordner wird in „Dateien" per Button „Als Bilder-Ordner" festgelegt (bilder_folder_id).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo      = getDbConnection();
    $folderId = driveConfigured() ? driveBilderFolderId($pdo) : null;
    $images   = [];
    if ($folderId !== null) {
        foreach (driveListChildren($folderId) as $c) {
            if ($c['isFolder'] || !str_starts_with($c['mimeType'], 'image/')) {
                continue;
            }
            $images[] = [
                'id'   => $c['id'],
                'name' => $c['name'],
                'url'  => 'api/file_download.php?fid=' . rawurlencode($c['id']) . '&inline=1',
            ];
        }
    }
    echo json_encode(['ok' => true, 'images' => $images], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logError('dateien_images error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Fehler beim Laden der Bilder.']);
}
