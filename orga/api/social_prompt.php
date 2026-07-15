<?php
/**
 * Themenbasierten Prompt speichern (POST + CSRF), JSON-Antwort.
 * Speichert je Anlass einen wiederverwendbaren Prompt-Text in `einstellungen`
 * unter dem Key `social_prompts` (ein JSON-Objekt {anlass: prompt}).
 * Read-modify-write, damit die anderen Anlässe erhalten bleiben.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function socialPromptJson(bool $ok, string $message = ''): void {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    socialPromptJson(false, 'Methode nicht erlaubt.');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    socialPromptJson(false, 'Ungültige Anfrage.');
}

$validAnlass = ['renntag', 'ankuendigung', 'countdown', 'sponsoren_dank', 'helfer', 'allgemein'];
$anlass = $_POST['anlass'] ?? '';
if (!in_array($anlass, $validAnlass, true)) {
    http_response_code(422);
    socialPromptJson(false, 'Unbekannter Anlass.');
}
$prompt = trim($_POST['prompt'] ?? '');
$prompt = mb_substr($prompt, 0, 3000);

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $stmt->execute(['key' => 'social_prompts']);
    $raw = (string) ($stmt->fetchColumn() ?: '');
    $prompts = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($prompts)) {
        $prompts = [];
    }

    if ($prompt === '') {
        unset($prompts[$anlass]);
    } else {
        $prompts[$anlass] = $prompt;
    }

    $encoded = $prompts === [] ? null : json_encode($prompts, JSON_UNESCAPED_UNICODE);
    $up = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    $up->execute(['key' => 'social_prompts', 'value' => $encoded, 'value2' => $encoded]);

    socialPromptJson(true);
} catch (PDOException $e) {
    logError('social_prompt update error: ' . $e->getMessage());
    http_response_code(500);
    socialPromptJson(false, 'Datenbankfehler.');
}
