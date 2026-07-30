<?php
/**
 * Briefvorlage als persönlichen Entwurf speichern (POST + CSRF, JSON-Antwort).
 * Schreibt in briefvorlagen_entwurf (user-scoped), nicht in den gemeinsamen Master.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_brief.php';
require_once __DIR__ . '/../../src/verein_brief.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'CSRF-Fehler']);
    exit;
}

$vorlageArt = (string) ($_POST['vorlage_art'] ?? '');
if (!in_array($vorlageArt, ['sponsor', 'verein'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Unbekannte Vorlagenart']);
    exit;
}

$slug = (string) ($_POST['slug'] ?? '');
$slugGueltig = $vorlageArt === 'sponsor'
    ? sponsorBriefSlugValid($slug)
    : vereinBriefSlugValid($slug);

if (!$slugGueltig) {
    echo json_encode(['ok' => false, 'error' => 'Unbekannte Vorlage']);
    exit;
}

$betreff = mb_substr(trim((string) ($_POST['betreff'] ?? '')), 0, 255);
$koerper = mb_substr(trim((string) ($_POST['koerper_md'] ?? '')), 0, 20000);
$userId  = (int) (getCurrentUserFromGuard()['id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Kein Benutzer']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO briefvorlagen_entwurf (user_id, vorlage_art, slug, betreff, koerper_md, gespeichert_am)
        VALUES (:uid, :art, :slug, :betreff, :koerper, NOW())
        ON DUPLICATE KEY UPDATE
            betreff        = :betreff2,
            koerper_md     = :koerper2,
            gespeichert_am = NOW()
    ');
    $stmt->execute([
        'uid'      => $userId,
        'art'      => $vorlageArt,
        'slug'     => $slug,
        'betreff'  => $betreff !== '' ? $betreff : null,
        'koerper'  => $koerper !== '' ? $koerper : null,
        'betreff2' => $betreff !== '' ? $betreff : null,
        'koerper2' => $koerper !== '' ? $koerper : null,
    ]);
    $stmt2 = $pdo->prepare('SELECT gespeichert_am FROM briefvorlagen_entwurf WHERE user_id = :uid AND vorlage_art = :art AND slug = :slug');
    $stmt2->execute(['uid' => $userId, 'art' => $vorlageArt, 'slug' => $slug]);
    $ts = (string) ($stmt2->fetchColumn() ?: date('Y-m-d H:i:s'));
    echo json_encode(['ok' => true, 'gespeichert_am' => $ts]);
} catch (PDOException $e) {
    logError('draft_save: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Datenbankfehler']);
}
