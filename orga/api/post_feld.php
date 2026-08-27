<?php
/**
 * Einzelnes Post-Feld autospeichern (POST + CSRF) — nur Admin/Orga.
 * Whitelist-basiert gegen Mass-Assignment: nur explizit erlaubte Spalten von
 * post_race_contents werden geschrieben, jede mit eigener Normalisierung.
 * Muster wie api/social_prompt.php (Feld-Autosave beim Verlassen).
 * Response: {"ok":true} / {"ok":false,"message":"…"}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function postFeldJson(bool $ok, string $message = ''): void
{
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    postFeldJson(false, 'Methode nicht erlaubt.');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    postFeldJson(false, 'Ungültige Anfrage.');
}

$postId = isset($_POST['post_id']) && ctype_digit((string) $_POST['post_id']) ? (int) $_POST['post_id'] : 0;
$feld   = (string) ($_POST['feld'] ?? '');

// Whitelist erlaubter Spalten (Schluessel = Feldname, sicher fuer die Query-Interpolation).
$erlaubt = ['erster_kommentar', 'auto_versand', 'auto_versand_channels', 'geplante_uhrzeit'];
if ($postId <= 0 || !in_array($feld, $erlaubt, true)) {
    http_response_code(422);
    postFeldJson(false, 'post_id/feld ungültig.');
}

$wert = (string) ($_POST['wert'] ?? '');
switch ($feld) {
    case 'erster_kommentar':
        $wert = mb_substr(trim($wert), 0, 2000);
        $val  = $wert !== '' ? $wert : null;
        break;
    case 'auto_versand':
        // NOT NULL DEFAULT 0 — nie NULL schreiben.
        $val = $wert === '1' ? 1 : 0;
        break;
    case 'auto_versand_channels':
        $teile = array_values(array_intersect(
            array_map('trim', explode(',', $wert)),
            ['instagram', 'facebook']
        ));
        $val = $teile === [] ? null : implode(',', $teile);
        break;
    case 'geplante_uhrzeit':
        // Wunsch-Sendezeit 'HH:MM' → TIME 'HH:MM:00'; leer/ungültig = NULL (Fallback mittags).
        $wert = trim($wert);
        $val  = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $wert) ? $wert . ':00' : null;
        break;
    default:
        $val = $wert !== '' ? $wert : null;
}

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("UPDATE post_race_contents SET `$feld` = :wert, updated_at = NOW() WHERE id = :id");
    $stmt->execute(['wert' => $val, 'id' => $postId]);
    postFeldJson(true);
} catch (PDOException $e) {
    logError('post_feld: ' . $e->getMessage());
    http_response_code(500);
    postFeldJson(false, 'Datenbankfehler.');
}
