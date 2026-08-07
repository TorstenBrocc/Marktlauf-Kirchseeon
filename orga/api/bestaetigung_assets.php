<?php
/**
 * Liefert die Dateien des designierten Bestätigungs-Anhang-Ordners (JSON) —
 * für die Opt-out-Liste beim Versand einer Sponsoring-Bestätigung. Nur eingeloggte Orga/Admin.
 * Stateless: keine Speicherung, jeder Versand startet mit allen Dateien vorausgewählt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

if (!driveConfigured()) {
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

try {
    $pdo      = getDbConnection();
    $folderId = driveBestaetigungAssetsFolderId($pdo);
    if ($folderId === null) {
        echo json_encode(['ok' => true, 'items' => [], 'configured' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $items = [];
    foreach (driveListChildren($folderId) as $c) {
        if ($c['isFolder'] || $c['id'] === '') {
            continue;
        }
        $items[] = ['id' => $c['id'], 'name' => $c['name']];
    }
    echo json_encode(['ok' => true, 'items' => $items, 'configured' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logError('bestaetigung_assets: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'items' => []]);
}
