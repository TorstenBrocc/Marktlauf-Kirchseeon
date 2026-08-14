<?php
/**
 * Post veroeffentlichen (POST + CSRF) — nur Admin/Orga.
 * Sendet Text + gespeicherte Grafik (bild_pfad) des Posts ueber den
 * Make.com-Webhook (src/social_dispatcher.php). Erfolg wird als Versand-Log
 * am Post festgehalten (status 'gesendet'); der verknuepfte Fahrplan-Eintrag
 * wird erledigt bzw. rueckt bei Wiederkehr aufs naechste Datum vor.
 * Instagram braucht ein Bild (JPEG) — die PNG-Master-Datei wird dafuer
 * deterministisch nach post-<id>-send.jpg gewandelt (eine Datei je Post).
 * Response: {"ok":true,...} / {"ok":false,"fallback":true,...} / {"ok":false,"message":...}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/social_dispatcher.php';
require_once __DIR__ . '/../../src/social_anlaesse.php';
require_once __DIR__ . '/../../src/channels/mail.php';

header('Content-Type: application/json; charset=utf-8');

function postDispatchJson(array $daten): void {
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    postDispatchJson(['ok' => false, 'message' => 'Methode nicht erlaubt.']);
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    postDispatchJson(['ok' => false, 'message' => 'Ungültige Anfrage.']);
}

$postId   = (int) ($_POST['post_id'] ?? 0);
$channels = array_values(array_intersect((array) ($_POST['channels'] ?? []), ['instagram', 'facebook']));
if ($postId <= 0 || $channels === []) {
    http_response_code(422);
    postDispatchJson(['ok' => false, 'message' => 'Post oder Kanäle fehlen.']);
}

$pdo  = getDbConnection();
$stmt = $pdo->prepare('SELECT * FROM post_race_contents WHERE id = :id');
$stmt->execute(['id' => $postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    http_response_code(404);
    postDispatchJson(['ok' => false, 'message' => 'Post nicht gefunden.']);
}

$text = trim((string) ($post['llm_text_social'] ?? ''));
if ($text === '') {
    http_response_code(422);
    postDispatchJson(['ok' => false, 'message' => 'Kein Social-Text am Post — bitte zuerst Schritt 1.']);
}

$bildPfad = trim((string) ($post['bild_pfad'] ?? ''));
if (in_array('instagram', $channels, true) && $bildPfad === '') {
    http_response_code(422);
    postDispatchJson(['ok' => false, 'message' => 'Instagram braucht ein Bild — bitte zuerst Schritt 3 (oder Instagram abwählen).']);
}

// Bild fuer den Versand: PNG-Master -> JPEG (Instagram akzeptiert nur JPEG),
// deterministischer Name = eine Versanddatei je Post, kein Muellberg.
$imageUrl = '';
if ($bildPfad !== '') {
    $dir     = __DIR__ . '/../../assets/social';
    $quelle  = $dir . '/' . basename($bildPfad);
    if (!is_file($quelle)) {
        http_response_code(422);
        postDispatchJson(['ok' => false, 'message' => 'Bilddatei fehlt auf dem Server — bitte Grafik in Schritt 3 neu übernehmen.']);
    }
    $sendDatei = 'post-' . $postId . '-send.jpg';
    $ok = false;
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string) file_get_contents($quelle));
        if ($src !== false) {
            $w = imagesx($src);
            $h = imagesy($src);
            $canvas = imagecreatetruecolor($w, $h);
            $white  = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
            imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
            $ok = imagejpeg($canvas, $dir . '/' . $sendDatei, 90);
            imagedestroy($src);
            imagedestroy($canvas);
        }
    }
    if (!$ok) {
        // Ohne GD: PNG direkt senden (Facebook ok; Instagram lehnt evtl. ab -> Fehler kommt von Make)
        $sendDatei = basename($bildPfad);
        logError('post_dispatch: GD nicht verfügbar — sende PNG statt JPEG (Post ' . $postId . ').');
    }
    $baseUrl  = rtrim((string) (getConfig()['app']['url'] ?? ''), '/');
    $imageUrl = $baseUrl . '/assets/social/' . $sendDatei;
}

$ergebnis = socialDispatch($text, $imageUrl, $channels);

if (!empty($ergebnis['fallback'])) {
    postDispatchJson([
        'ok'       => false,
        'fallback' => true,
        'message'  => (string) ($ergebnis['message'] ?? 'Auto-Posting nicht verfügbar — bitte manuell posten (Buttons unten).'),
    ]);
}

// Versand-Log am Post + Fahrplan-Eintrag abschliessen (Wiederkehr rueckt vor)
$fahrplanRefId = 0;
try {
    $pdo->prepare(
        "UPDATE post_race_contents
            SET status = 'gesendet', gesendet_am = NOW(),
                gesendet_kanaele = :kanaele, gesendet_ergebnis = :ergebnis
          WHERE id = :id"
    )->execute([
        'kanaele'  => implode(',', $channels),
        'ergebnis' => mb_substr((string) ($ergebnis['message'] ?? 'an Make.com übergeben'), 0, 255),
        'id'       => $postId,
    ]);

    $stmt = $pdo->prepare("SELECT id, zieldatum, frequenz_tage, ende FROM social_fahrplan WHERE post_id = :pid AND status = 'offen' LIMIT 1");
    $stmt->execute(['pid' => $postId]);
    if ($eintrag = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fahrplanRefId = (int) $eintrag['id'];
        $vorgerueckt = false;
        if ($eintrag['frequenz_tage'] && $eintrag['zieldatum']) {
            $naechstes = (new DateTime($eintrag['zieldatum']))
                ->modify('+' . (int) $eintrag['frequenz_tage'] . ' days')
                ->format('Y-m-d');
            if ($eintrag['ende'] === null || $naechstes <= $eintrag['ende']) {
                $pdo->prepare('UPDATE social_fahrplan SET zieldatum = :d, post_id = NULL WHERE id = :id')
                    ->execute(['d' => $naechstes, 'id' => $eintrag['id']]);
                $vorgerueckt = true;
            }
        }
        if (!$vorgerueckt) {
            $pdo->prepare("UPDATE social_fahrplan SET status = 'erledigt' WHERE id = :id")
                ->execute(['id' => $eintrag['id']]);
        }
    }
} catch (PDOException $e) {
    logError('post_dispatch: Log/Fahrplan-Update fehlgeschlagen: ' . $e->getMessage());
}

// "Post ist live"-Mail an alle aktiven Orga/Admins: Verstaerker-Handgriffe der
// ersten Stunde (Inhaber-Entscheidung 2026-08-14). Fire-and-forget, Fehler nur ins Log.
try {
    $def   = socialAnlaesse()[(string) ($post['anlass_key'] ?? '')] ?? null;
    $thema = $def ? $def['ui'] : 'Social-Post';
    $textVorschau = mb_substr($text, 0, 200) . (mb_strlen($text) > 200 ? '…' : '');
    $mailText = "Gerade veröffentlicht auf " . implode(' + ', $channels) . ": {$thema}\n\n"
        . "„{$textVorschau}\"\n\n"
        . "So hilfst du dem Post jetzt (erste Stunde zählt am meisten):\n"
        . "1. Post liken und mit 1 Kommentar anschieben (Frage/Emoji reicht).\n"
        . "2. In deine Instagram-Story teilen.\n"
        . "3. Link an Familie/Lauffreunde weiterschicken (\"Sends\" zählen beim Algorithmus am stärksten).\n"
        . "4. Falls du in lokalen Facebook-Gruppen bist: dort teilen (Regeln beachten, eigener Anmoderationssatz).\n"
        . "5. Kommentare, die du siehst: kurz beantworten — schnell und freundlich.\n\n"
        . ($fahrplanRefId > 0 ? "Post im Dashboard: https://atsv-kirchseeon-marktlauf.de/orga/social_post.php?fahrplan=" . $fahrplanRefId : "Dashboard: https://atsv-kirchseeon-marktlauf.de/orga/social_fahrplan.php");
    $body = marktlaufMailBody($mailText);
    $orgaMails = $pdo->query("
        SELECT name, email FROM users
        WHERE active = 1 AND role IN ('admin','orga') AND NULLIF(TRIM(email),'') IS NOT NULL
    ")->fetchAll();
    foreach ($orgaMails as $o) {
        sendMail((string) $o['email'], 'Social-Post ist live: ' . $thema, $body['text'], $body['html']);
    }
} catch (Throwable $e) {
    logError('post_dispatch: Live-Mail fehlgeschlagen: ' . $e->getMessage());
}

postDispatchJson(['ok' => true, 'message' => (string) ($ergebnis['message'] ?? 'An Make.com übergeben.')]);
