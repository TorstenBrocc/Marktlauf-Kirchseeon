<?php
/**
 * Social-Media-Seiten-Einstellungen speichern (POST + CSRF), JSON-Antwort.
 * Vereinsweite Werte in `einstellungen`:
 *   - raceresult_url   : SimpleAPI-Listen-Link für den Renntag-Content
 *   - social_hashtags  : Standard-Hashtags, die an Social-Posts gehängt werden
 * Ermöglicht das Pflegen direkt auf der Social-Media-Seite (nicht nur im
 * Admin-Bereich Einstellungen).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function socialSettingsJson(bool $ok, string $message = ''): void {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    socialSettingsJson(false, 'Methode nicht erlaubt.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    socialSettingsJson(false, 'Ungültige Anfrage.');
}

$key = $_POST['key'] ?? '';
$allowed = ['raceresult_api_url', 'social_hashtags'];
if (!in_array($key, $allowed, true)) {
    http_response_code(422);
    socialSettingsJson(false, 'Unbekannter Schlüssel.');
}

$value = trim($_POST['value'] ?? '');
$value = mb_substr($value, 0, 2000);

// raceresult_api_url: leere Eingabe erlaubt (löscht); sonst URL-Validierung
if ($key === 'raceresult_api_url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
    http_response_code(422);
    socialSettingsJson(false, 'Bitte eine gültige URL eingeben.');
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    $stmt->execute([
        'key'    => $key,
        'value'  => $value !== '' ? $value : null,
        'value2' => $value !== '' ? $value : null,
    ]);
    socialSettingsJson(true);
} catch (PDOException $e) {
    logError('social_settings update error: ' . $e->getMessage());
    http_response_code(500);
    socialSettingsJson(false, 'Datenbankfehler.');
}
