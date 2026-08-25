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
require_once __DIR__ . '/../../src/social_versand.php';

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

// make.com-Optimierung §2: Erster Kommentar (Link+Hashtags). POST-Wert hat Vorrang (unsaved
// edits), sonst der gespeicherte Wert am Post. Leer = Make ueberspringt den Schritt.
$ersterKommentar = trim((string) ($_POST['erster_kommentar'] ?? $post['erster_kommentar'] ?? ''));

// Gemeinsame Versandlogik (src/social_versand.php) — ein Pfad fuer Klick UND Auto-Versand-Timer.
$ergebnis = versendePost($pdo, $post, $channels, $ersterKommentar);

if (!empty($ergebnis['fallback'])) {
    postDispatchJson([
        'ok'       => false,
        'fallback' => true,
        'message'  => (string) ($ergebnis['message'] ?? 'Auto-Posting nicht verfügbar — bitte manuell posten (Buttons unten).'),
    ]);
}
if (empty($ergebnis['ok'])) {
    http_response_code((int) ($ergebnis['code'] ?? 500));
    postDispatchJson(['ok' => false, 'message' => (string) ($ergebnis['message'] ?? 'Versand fehlgeschlagen.')]);
}

postDispatchJson(['ok' => true, 'message' => (string) ($ergebnis['message'] ?? 'An Make.com übergeben.')]);
