<?php
/**
 * Listet den Papierkorb des geteilten Laufwerks (Orga/Admin). Read-only.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

if (!driveConfigured()) {
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}

try {
    echo json_encode(['ok' => true, 'items' => driveListTrash()]);
} catch (Throwable $e) {
    logError('trash_list: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'items' => []]);
}
