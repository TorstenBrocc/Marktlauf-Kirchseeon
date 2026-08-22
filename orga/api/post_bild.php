<?php
/**
 * Grafik am Post speichern (POST + CSRF) — nur Admin/Orga.
 * Nimmt das im Vorlagen-Werk gerenderte PNG (Data-URL), legt es unter
 * assets/social/ ab (PNG als verlustfreier Master; JPEG-Wandlung passiert
 * erst beim Versand) und schreibt bild_pfad an post_race_contents.
 * Response: {"ok":true,"pfad":"assets/social/..."} oder {"ok":false,"message":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function postBildJson(bool $ok, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    postBildJson(false, 'Methode nicht erlaubt.');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    postBildJson(false, 'Ungültige Anfrage.');
}

$postId   = (int) ($_POST['post_id'] ?? 0);
$imageB64 = (string) ($_POST['image_base64'] ?? '');
if ($postId <= 0 || $imageB64 === '') {
    http_response_code(422);
    postBildJson(false, 'Post oder Bild fehlt.');
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare('SELECT id, bild_pfad FROM post_race_contents WHERE id = :id');
$stmt->execute(['id' => $postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    http_response_code(404);
    postBildJson(false, 'Post nicht gefunden.');
}

if (preg_match('#^data:image/[a-z0-9.+-]+;base64,#i', $imageB64)) {
    $imageB64 = substr($imageB64, strpos($imageB64, ',') + 1);
}
$binary = base64_decode($imageB64, true);
if ($binary === false) {
    http_response_code(400);
    postBildJson(false, 'Bild konnte nicht gelesen werden.');
}
if (strlen($binary) > 8 * 1024 * 1024) {
    http_response_code(413);
    postBildJson(false, 'Bild zu groß (max. 8 MB).');
}
// PNG (Vorlagen-Grafik) ODER JPEG (Weg "nur Foto") akzeptieren.
$istPng  = substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n";
$istJpeg = substr($binary, 0, 3) === "\xFF\xD8\xFF";
if (!$istPng && !$istJpeg) {
    http_response_code(415);
    postBildJson(false, 'Nur PNG- oder JPEG-Bilder werden akzeptiert.');
}
$ext = $istJpeg ? 'jpg' : 'png';

$dir = __DIR__ . '/../../assets/social';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    logError('post_bild: Verzeichnis assets/social nicht anlegbar.');
    http_response_code(500);
    postBildJson(false, 'Bild-Ablage nicht verfügbar.');
}

$filename = 'post-' . $postId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
if (file_put_contents($dir . '/' . $filename, $binary) === false) {
    logError('post_bild: Bild konnte nicht geschrieben werden.');
    http_response_code(500);
    postBildJson(false, 'Bild konnte nicht gespeichert werden.');
}

// Vorheriges Bild dieses Posts wegraeumen (nur innerhalb assets/social)
$alt = (string) ($post['bild_pfad'] ?? '');
if ($alt !== '' && str_starts_with($alt, 'assets/social/')) {
    $altDatei = $dir . '/' . basename($alt);
    if (is_file($altDatei)) {
        @unlink($altDatei);
    }
}

$pfad = 'assets/social/' . $filename;
try {
    $pdo->prepare('UPDATE post_race_contents SET bild_pfad = :pfad WHERE id = :id')
        ->execute(['pfad' => $pfad, 'id' => $postId]);
} catch (PDOException $e) {
    logError('post_bild: ' . $e->getMessage());
    http_response_code(500);
    postBildJson(false, 'Datenbankfehler.');
}

postBildJson(true, '', ['pfad' => $pfad]);
