<?php
/**
 * LLM-Provider in einstellungen speichern (POST + CSRF) — nur Admin/Orga.
 * Response: {"ok":true} oder {"error":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültige Anfrage.']);
    exit;
}

$provider = $_POST['provider'] ?? '';
if (!in_array($provider, ['gemini', 'mistral'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiger Provider.']);
    exit;
}

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO einstellungen (`key`, `value`) VALUES ('llm_provider', :v)
         ON DUPLICATE KEY UPDATE `value` = :v2"
    );
    $stmt->execute(['v' => $provider, 'v2' => $provider]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    logError('social_provider: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler.']);
}
