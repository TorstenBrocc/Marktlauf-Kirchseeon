<?php
/**
 * make.com-Optimierung Stage C — Liste der Posts, fuer die der Insights-Sammler Reichweite/Likes
 * nachladen soll (Spec intern/make-com-optimierung-spec.md §7).
 *
 * OEFFENTLICH — das geplante make-Szenario ruft an, KEIN Login/CSRF. Auth wie der Rueckkanal:
 * Header `X-Signature: sha256=hmac_sha256(rawBody, make_webhook_secret)` ODER `secret` im JSON-Body.
 * Ohne konfiguriertes Secret: abgelehnt. READ-ONLY, liefert nur IDs + Media-IDs (keine
 * personenbezogenen Daten).
 *
 * Antwort: {"ok":true,"posts":[{"post_id":123,"channel":"instagram","media_id":"…"}, …]}
 * Kriterium: status='gesendet', gesendet_am in den letzten 7 Tagen, Media-ID vorhanden, und
 * Insights noch nicht (frisch) geholt (versand_insights_am NULL oder aelter als 6 h).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

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

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->query(
        // insights_versuche < 3: dauerhaftes Aufgeben nach zu vielen Fehlversuchen (Migration
        // 090). Der Make-Error-Handler meldet Fehlschlaege via post_status_callback.php
        // (insights_status=failed) zurueck, das den Zaehler hochsetzt — so verschwindet ein
        // nicht abrufbarer Post (ungueltige/geloeschte Media-ID) leise aus der Wiedervorlage,
        // statt den Sammler-Lauf jeden Durchgang scheitern zu lassen. Schwelle = 3, muss zur
        // INSIGHTS_MAX_VERSUCHE-Logik im Callback passen.
        "SELECT id, ig_media_id, fb_post_id
           FROM post_race_contents
          WHERE status = 'gesendet'
            AND gesendet_am >= (NOW() - INTERVAL 7 DAY)
            AND (ig_media_id IS NOT NULL OR fb_post_id IS NOT NULL)
            AND (versand_insights_am IS NULL OR versand_insights_am < (NOW() - INTERVAL 6 HOUR))
            AND insights_versuche < 3
          ORDER BY gesendet_am DESC
          LIMIT 100"
    );

    $posts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $postId = (int) $row['id'];
        if (!empty($row['ig_media_id'])) {
            $posts[] = ['post_id' => $postId, 'channel' => 'instagram', 'media_id' => (string) $row['ig_media_id']];
        }
        if (!empty($row['fb_post_id'])) {
            $posts[] = ['post_id' => $postId, 'channel' => 'facebook', 'media_id' => (string) $row['fb_post_id']];
        }
    }

    pendingInsightsOut(200, ['ok' => true, 'posts' => $posts]);
} catch (PDOException $e) {
    logError('posts_pending_insights: DB-Fehler: ' . $e->getMessage());
    pendingInsightsOut(500, ['ok' => false, 'message' => 'DB-Fehler.']);
}
