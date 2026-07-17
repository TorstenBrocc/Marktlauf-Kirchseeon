<?php
/**
 * Speichert einen einzelnen JSON-Wert in der einstellungen-Tabelle.
 * Erlaubte Keys sind explizit whitegelistet.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Nur Admins.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'CSRF-Fehler.']);
    exit;
}

$erlaubteKeys = ['sponsor_branchen'];
$key   = (string) ($_POST['key'] ?? '');
$value = (string) ($_POST['value'] ?? '');

if (!in_array($key, $erlaubteKeys, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültiger Key.']);
    exit;
}

// Sicherstellen dass value valides JSON ist
$decoded = json_decode($value, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültiges JSON.']);
    exit;
}
$value = json_encode($decoded, JSON_UNESCAPED_UNICODE);

try {
    $pdo = getDbConnection();
    $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = :v2'
    )->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    logError('einstellungen_json_update: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
}
