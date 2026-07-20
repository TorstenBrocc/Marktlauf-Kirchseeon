<?php
/**
 * Social-Post an Instagram/Facebook auslösen (POST + CSRF) — nur Admin/Orga.
 *
 * Ablauf: nimmt Post-Text + gerendertes PNG (Base64) + Kanäle entgegen, legt das
 * Bild öffentlich unter assets/social/ ab und übergibt {text, image_url, channels}
 * an socialDispatch() (Make.com-Webhook). Bei fehlender Config / Fehler kommt
 * 'fallback' => true zurück — das Dashboard zeigt dann den manuellen Weg.
 *
 * Response: {"ok":bool,"message":string,"image_url"?:string,"fallback"?:bool} | {"error":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/social_dispatcher.php';

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

$text     = trim((string) ($_POST['text'] ?? ''));
$imageB64 = (string) ($_POST['image_base64'] ?? '');
$channels = $_POST['channels'] ?? [];
if (!is_array($channels)) {
    $channels = [$channels];
}
$channels = array_values(array_intersect(['instagram', 'facebook'], $channels));

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Text zum Posten.']);
    exit;
}
if (empty($channels)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte mindestens einen Kanal (Instagram/Facebook) wählen.']);
    exit;
}

$imageUrl = '';

// Optionales Bild: Data-URL zerlegen, als PNG validieren, öffentlich ablegen.
if ($imageB64 !== '') {
    if (preg_match('#^data:image/png;base64,#', $imageB64)) {
        $imageB64 = substr($imageB64, strpos($imageB64, ',') + 1);
    }
    $binary = base64_decode($imageB64, true);
    if ($binary === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Bild konnte nicht gelesen werden.']);
        exit;
    }
    // Max. 8 MB
    if (strlen($binary) > 8 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'Bild zu groß (max. 8 MB).']);
        exit;
    }
    // PNG-Signatur prüfen
    if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        http_response_code(415);
        echo json_encode(['error' => 'Nur PNG-Grafiken werden akzeptiert.']);
        exit;
    }

    $dir = __DIR__ . '/../../assets/social';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        logError('social_dispatch: Verzeichnis assets/social nicht anlegbar.');
        http_response_code(500);
        echo json_encode(['error' => 'Bild-Ablage nicht verfügbar.']);
        exit;
    }

    $filename = 'post-' . date('Ymd-His') . '-' . substr(uuid(), 0, 8) . '.png';
    if (file_put_contents($dir . '/' . $filename, $binary) === false) {
        logError('social_dispatch: Bild konnte nicht geschrieben werden.');
        http_response_code(500);
        echo json_encode(['error' => 'Bild konnte nicht gespeichert werden.']);
        exit;
    }

    $baseUrl  = rtrim((string) (getConfig()['app']['url'] ?? ''), '/');
    $imageUrl = $baseUrl . '/assets/social/' . $filename;
}

$result = socialDispatch($text, $imageUrl, $channels);
if ($imageUrl !== '') {
    $result['image_url'] = $imageUrl;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
