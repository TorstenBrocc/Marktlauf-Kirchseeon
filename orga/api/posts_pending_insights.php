<?php
/**
 * make.com-Optimierung Stage C — Liste der Posts, fuer die der Insights-Sammler Reichweite/Likes
 * nachladen soll (Spec intern/make-com-optimierung-spec.md §7).
 *
 * OEFFENTLICH — das geplante make-Szenario ruft an, KEIN Login/CSRF. Auth wie der Rueckkanal:
 * Header `X-Signature: sha256=hmac_sha256(rawBody, make_webhook_secret)` ODER `secret` im JSON-Body.
 * Ohne konfiguriertes Secret: abgelehnt. Liest nur (einzige Schreibaktion: Lebenszeichen-Stempel fuer
 * src/make_waechter.php), liefert nur IDs + Media-IDs (keine
 * personenbezogenen Daten).
 *
 * Antwort: {"ok":true,"posts":[{"post_id":123,"channel":"instagram","media_id":"…"}, …]}
 * Kriterium: status='gesendet', gesendet_am in den letzten 7 Tagen, Media-ID vorhanden, und
 * Insights noch nicht (frisch) geholt (versand_insights_am NULL oder aelter als 6 h).
 * Nachhol-Lauf: optional {"tage":30,"versuche_ignorieren":true} im Body (siehe unten).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/make_waechter.php';
require_once __DIR__ . '/../../src/social_insights.php';

header('Content-Type: application/json; charset=utf-8');

function pendingInsightsOut(int $code, array $daten): void
{
    http_response_code($code);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pendingInsightsOut(405, ['ok' => false, 'message' => 'POST erwartet.']);
}

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$secret = trim((string) (getConfig()['make_webhook_secret'] ?? ''));
if ($secret === '') {
    logError('posts_pending_insights: kein make_webhook_secret konfiguriert — abgelehnt.');
    pendingInsightsOut(503, ['ok' => false, 'message' => 'Nicht konfiguriert.']);
}

$authOk    = false;
$sigHeader = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
if ($sigHeader !== '' && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $sigHeader)) {
    $authOk = true;
} elseif (isset($data['secret']) && is_string($data['secret']) && hash_equals($secret, $data['secret'])) {
    $authOk = true;
}
if (!$authOk) {
    logError('posts_pending_insights: Auth fehlgeschlagen.');
    pendingInsightsOut(403, ['ok' => false, 'message' => 'Nicht autorisiert.']);
}

// Optional backfill switches (one-off catch-up runs, e.g. after a collector outage):
// "tage" widens the 7-day window (1–90), "versuche_ignorieren" also returns posts that
// already hit the failure limit. Defaults keep the regular daily behaviour unchanged.
$tage               = 7;
if (isset($data['tage']) && is_numeric($data['tage'])) {
    $tage = min(90, max(1, (int) $data['tage']));
}
$versucheIgnorieren = !empty($data['versuche_ignorieren']);

try {
    $pdo  = getDbConnection();
    makeInsightsHeartbeat($pdo);
    // Failure limit per channel (migration 110): the make error handler reports failures via
    // post_status_callback.php (insights_status=failed), which counts them up per channel. A
    // channel at INSIGHTS_MAX_VERSUCHE drops out quietly (deleted/invalid media id) without
    // taking the other channel of the same post with it.
    $stmt = $pdo->prepare(
        "SELECT id, ig_media_id, fb_post_id, ig_insights_versuche, fb_insights_versuche
           FROM post_race_contents
          WHERE status = 'gesendet'
            AND gesendet_am >= (NOW() - INTERVAL :tage DAY)
            AND (ig_media_id IS NOT NULL OR fb_post_id IS NOT NULL)
            AND (versand_insights_am IS NULL OR versand_insights_am < (NOW() - INTERVAL 6 HOUR))
          ORDER BY gesendet_am DESC
          LIMIT 100"
    );
    $stmt->execute(['tage' => $tage]);

    $posts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $postId = (int) $row['id'];
        $igOffen = $versucheIgnorieren || (int) $row['ig_insights_versuche'] < INSIGHTS_MAX_VERSUCHE;
        $fbOffen = $versucheIgnorieren || (int) $row['fb_insights_versuche'] < INSIGHTS_MAX_VERSUCHE;
        if (!empty($row['ig_media_id']) && $igOffen) {
            $posts[] = ['post_id' => $postId, 'channel' => 'instagram', 'media_id' => (string) $row['ig_media_id']];
        }
        if (!empty($row['fb_post_id']) && $fbOffen) {
            $posts[] = ['post_id' => $postId, 'channel' => 'facebook', 'media_id' => (string) $row['fb_post_id']];
        }
    }

    pendingInsightsOut(200, ['ok' => true, 'posts' => $posts]);
} catch (PDOException $e) {
    logError('posts_pending_insights: DB-Fehler: ' . $e->getMessage());
    pendingInsightsOut(500, ['ok' => false, 'message' => 'DB-Fehler.']);
}
