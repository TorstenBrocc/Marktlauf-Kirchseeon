<?php
/**
 * Stellt eine Datei/einen Ordner aus dem Papierkorb wieder her (Orga/Admin, CSRF).
 * Sicherheit: nur Elemente im geteilten Laufwerk.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$fid = trim((string) ($_POST['fid'] ?? ''));
if ($fid === '' || !driveConfigured()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

if (!driveInSharedDrive($fid)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Element nicht im Laufwerk.']);
    exit;
}

try {
    driveRestore($fid);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    logError('trash_restore: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Wiederherstellen fehlgeschlagen.']);
}
