<?php
/**
 * Themenbasierten Prompt UND Fakten speichern (POST + CSRF), JSON-Antwort.
 * Speichert je Anlass Prompt + Fakten in `einstellungen` unter dem Key
 * `social_prompts` (JSON {anlass: {prompt, fakten}}). Nur übergebene Felder
 * werden aktualisiert; Read-modify-write hält die anderen Anlässe/Felder erhalten.
 * Alt-Format (reiner Prompt-String je Anlass) wird beim Schreiben migriert.
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
// prompt und/oder fakten (nur übergebene Felder werden aktualisiert)
$hasPrompt = array_key_exists('prompt', $_POST);
$hasFakten = array_key_exists('fakten', $_POST);
$prompt = mb_substr(trim($_POST['prompt'] ?? ''), 0, 3000);
$fakten = mb_substr(trim($_POST['fakten'] ?? ''), 0, 4000);

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $stmt->execute(['key' => 'social_prompts']);
    $raw = (string) ($stmt->fetchColumn() ?: '');
    $store = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($store)) {
        $store = [];
    }

    // Bestehenden Eintrag normalisieren (Alt-Format = reiner Prompt-String)
    $entry = $store[$anlass] ?? [];
    if (is_string($entry)) {
        $entry = ['prompt' => $entry, 'fakten' => ''];
    } elseif (!is_array($entry)) {
        $entry = [];
    }
    if ($hasPrompt) { $entry['prompt'] = $prompt; }
    if ($hasFakten) { $entry['fakten'] = $fakten; }
    $entry = array_filter($entry, static fn ($v) => $v !== '' && $v !== null);

    if ($entry === []) {
        unset($store[$anlass]);
    } else {
        $store[$anlass] = $entry;
    }

    $encoded = $store === [] ? null : json_encode($store, JSON_UNESCAPED_UNICODE);
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
