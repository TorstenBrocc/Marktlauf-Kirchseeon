<?php
/**
 * Make.com-Rueckkanal (make.com-Optimierung #1/#2): meldet nach dem echten IG/FB-Post den
 * Live-Permalink je Kanal zurueck ans Dashboard (Spalten ig_permalink/fb_permalink,
 * Migration 083). Behebt das Fire-and-forget: bisher wusste das Dashboard nur, dass der
 * Webhook 2xx lieferte, nicht ob/wo wirklich gepostet wurde.
 *
 * OEFFENTLICH — Make.com ruft an, KEIN Login/CSRF. Authentifizierung per HMAC-Signatur
 * (Header `X-Signature: sha256=<hmac_sha256(rawBody, make_webhook_secret)>`) ODER, als
 * einfacher Fallback, per `secret` im JSON-Body. Ohne konfiguriertes Secret: abgelehnt
 * (kein offener Schreibzugriff auf die DB).
 *
 * Erwarteter JSON-Body: {"post_id":123,"channel":"instagram"|"facebook","permalink":"https://…","status":"ok"}
 * MO1 (Insights-Rueckkanal): zusaetzlich optional {"reichweite":1840,"likes":97} — darf im selben
 * ODER in einem spaeteren (verzoegerten) Callback kommen; es wird nur gesetzt, was mitkommt, ein
 * Insights-only-Callback loescht den Permalink NICHT.
 * Antwort: {"ok":true} / {"ok":false,"message":"…"}
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function postCallbackOut(int $code, array $daten): void
{
    http_response_code($code);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    postCallbackOut(405, ['ok' => false, 'message' => 'POST erwartet.']);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    postCallbackOut(400, ['ok' => false, 'message' => 'Ungültiger JSON-Body.']);
}

$secret = trim((string) (getConfig()['make_webhook_secret'] ?? ''));
if ($secret === '') {
    // Kein Secret gesetzt -> wir koennen den Anrufer nicht pruefen -> kein Schreibzugriff.
    logError('post_status_callback: kein make_webhook_secret konfiguriert — Callback abgelehnt.');
    postCallbackOut(503, ['ok' => false, 'message' => 'Rückkanal nicht konfiguriert.']);
}

// Auth: HMAC-Signatur bevorzugt, sonst Secret im Body. Beides zeitkonstant vergleichen.
$authOk    = false;
$sigHeader = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
if ($sigHeader !== '' && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $sigHeader)) {
    $authOk = true;
} elseif (isset($data['secret']) && is_string($data['secret']) && hash_equals($secret, $data['secret'])) {
    $authOk = true;
}
if (!$authOk) {
    logError('post_status_callback: Auth fehlgeschlagen (Signatur/Secret).');
    postCallbackOut(403, ['ok' => false, 'message' => 'Nicht autorisiert.']);
}

$postId    = (int) ($data['post_id'] ?? 0);
$channel   = (string) ($data['channel'] ?? '');
$permalink = trim((string) ($data['permalink'] ?? ''));
$status    = trim((string) ($data['status'] ?? ''));

if ($postId <= 0 || !in_array($channel, ['instagram', 'facebook'], true)) {
    postCallbackOut(422, ['ok' => false, 'message' => 'post_id/channel fehlen oder ungültig.']);
}

// Permalink defensiv: nur HTTPS-URLs auf Meta-Domains akzeptieren, sonst nicht speichern.
if ($permalink !== '' && !preg_match('#^https://([a-z0-9-]+\.)?(instagram\.com|facebook\.com|fb\.com|fb\.watch)/#i', $permalink)) {
    logError('post_status_callback: Permalink verworfen (unerwartete Domain) für Post ' . $postId);
    $permalink = '';
}

// Kanal-Praefix aus fester Whitelist (ig/fb) — sicher fuer die Spalten-Interpolation.
$chPrefix = $channel === 'instagram' ? 'ig' : 'fb';

// Nur setzen, was dieser Callback mitbringt (Permalink UND/ODER Insights koennen getrennt
// kommen — MO1 §1.4). versand_bestaetigt_am + callback_info markieren jeden Anruf.
$sets   = [
    'versand_bestaetigt_am = NOW()',
    'versand_callback_info = LEFT(CONCAT(COALESCE(versand_callback_info, \'\'), :info), 400)',
];
$params = [
    'info' => '[' . date('d.m. H:i') . ' ' . $channel . ' ' . ($status !== '' ? $status : 'ok') . '] ',
    'id'   => $postId,
];

// Permalink nur ueberschreiben, wenn einer (gueltig) mitkommt — sonst NICHT auf NULL setzen
// (ein Insights-only-Callback darf den bestehenden Permalink nicht loeschen).
if ($permalink !== '') {
    $sets[] = "`{$chPrefix}_permalink` = :permalink";
    $params['permalink'] = $permalink;
}

// make.com-Optimierung MO1: Insights (Reichweite/Likes) optional, defensiv geklemmt.
$hatReichweite = array_key_exists('reichweite', $data) && is_numeric($data['reichweite']);
$hatLikes      = array_key_exists('likes', $data) && is_numeric($data['likes']);
if ($hatReichweite) {
    $sets[] = "`{$chPrefix}_reichweite` = :reichweite";
    $params['reichweite'] = min(100000000, max(0, (int) $data['reichweite']));
}
if ($hatLikes) {
    $sets[] = "`{$chPrefix}_likes` = :likes";
    $params['likes'] = min(100000000, max(0, (int) $data['likes']));
}
if ($hatReichweite || $hatLikes) {
    $sets[] = 'versand_insights_am = NOW()';
}

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare('UPDATE post_race_contents SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        logError('post_status_callback: Post ' . $postId . ' nicht gefunden.');
    }
} catch (PDOException $e) {
    logError('post_status_callback: DB-Fehler: ' . $e->getMessage());
    postCallbackOut(500, ['ok' => false, 'message' => 'DB-Fehler.']);
}

postCallbackOut(200, ['ok' => true]);
