<?php
/**
 * Briefvorlage speichern (POST + CSRF).
 * Speichert Betreff + Markdown-Körper je Slug in sponsor_briefvorlagen.
 * Leeres Feld => NULL => beim Rendern greift der Code-Default.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_brief.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsor_briefe.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsor_briefe.php');
    exit;
}

$slug = (string) ($_POST['slug'] ?? '');
if (!sponsorBriefSlugValid($slug)) {
    $_SESSION['flash_error'] = 'Unbekannte Vorlage.';
    header('Location: ../sponsor_briefe.php');
    exit;
}

$betreff = trim((string) ($_POST['betreff'] ?? ''));
$betreff = mb_substr($betreff, 0, 255);
$koerper = trim((string) ($_POST['koerper_md'] ?? ''));
$koerper = mb_substr($koerper, 0, 20000);

$defaults = sponsorBriefDefaults();
$name = $defaults[$slug]['name'] ?? $slug;
$userId = getCurrentUserFromGuard()['id'] ?? null;

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO sponsor_briefvorlagen (slug, name, betreff, koerper_md, aktualisiert_am, aktualisiert_von)
        VALUES (:slug, :name, :betreff, :koerper, NOW(), :uid)
        ON DUPLICATE KEY UPDATE
            betreff = :betreff2,
            koerper_md = :koerper2,
            aktualisiert_am = NOW(),
            aktualisiert_von = :uid2
    ');
    $stmt->execute([
        'slug'    => $slug,
        'name'    => $name,
        'betreff' => $betreff !== '' ? $betreff : null,
        'koerper' => $koerper !== '' ? $koerper : null,
        'uid'     => $userId,
        'betreff2' => $betreff !== '' ? $betreff : null,
        'koerper2' => $koerper !== '' ? $koerper : null,
        'uid2'    => $userId,
    ]);
    $_SESSION['flash_success'] = 'Briefvorlage gespeichert.';
} catch (PDOException $e) {
    logError('Sponsor-Briefvorlage speichern: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Speichern.';
}

header('Location: ../sponsor_briefe.php?slug=' . urlencode($slug));
exit;
