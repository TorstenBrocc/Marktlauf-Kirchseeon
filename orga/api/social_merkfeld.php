<?php
/**
 * Social-Media-Merkfeld speichern (POST + CSRF)
 * Vereinsweites Referenz-Freitextfeld auf der Social-Media-Seite.
 * Speicherung in einstellungen (Key social_merkfeld).
 * Antwortet als JSON (fetch aus social_orchestrator.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function socialMerkfeldJson(bool $ok, string $message = ''): void {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    socialMerkfeldJson(false, 'Methode nicht erlaubt.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    socialMerkfeldJson(false, 'Ungültige Anfrage.');
}

$merkfeld = trim($_POST['merkfeld'] ?? '');
$merkfeld = mb_substr($merkfeld, 0, 5000);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    $stmt->execute([
        'key' => 'social_merkfeld',
        'value' => $merkfeld !== '' ? $merkfeld : null,
        'value2' => $merkfeld !== '' ? $merkfeld : null,
    ]);
    socialMerkfeldJson(true);
} catch (PDOException $e) {
    logError('Social-Merkfeld update error: ' . $e->getMessage());
    http_response_code(500);
    socialMerkfeldJson(false, 'Datenbankfehler.');
}
