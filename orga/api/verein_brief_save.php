<?php
/**
 * Vereins-/Laufevent-Vorlage speichern (POST + CSRF).
 * Speichert Betreff + Markdown-Körper je Slug in verein_briefvorlagen.
 * Leeres Feld => NULL => beim Rendern greift der Code-Default.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/verein_brief.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine_briefe.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../vereine_briefe.php');
    exit;
}

$slug = (string) ($_POST['slug'] ?? '');
if (!vereinBriefSlugValid($slug)) {
    $_SESSION['flash_error'] = 'Unbekannte Vorlage.';
    header('Location: ../vereine_briefe.php');
    exit;
}

$betreff = mb_substr(trim((string) ($_POST['betreff'] ?? '')), 0, 255);
$koerper = mb_substr(trim((string) ($_POST['koerper_md'] ?? '')), 0, 20000);

$defaults = vereinBriefDefaults();
$name = $defaults[$slug]['name'] ?? $slug;
$userId = getCurrentUserFromGuard()['id'] ?? null;

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO verein_briefvorlagen (slug, name, betreff, koerper_md, aktualisiert_am, aktualisiert_von)
        VALUES (:slug, :name, :betreff, :koerper, NOW(), :uid)
        ON DUPLICATE KEY UPDATE
            betreff = :betreff2,
            koerper_md = :koerper2,
            aktualisiert_am = NOW(),
            aktualisiert_von = :uid2
    ');
    $stmt->execute([
        'slug'     => $slug,
        'name'     => $name,
        'betreff'  => $betreff !== '' ? $betreff : null,
        'koerper'  => $koerper !== '' ? $koerper : null,
        'uid'      => $userId,
        'betreff2' => $betreff !== '' ? $betreff : null,
        'koerper2' => $koerper !== '' ? $koerper : null,
        'uid2'     => $userId,
    ]);
    $_SESSION['flash_success'] = 'Vorlage gespeichert.';
} catch (PDOException $e) {
    logError('Verein-Briefvorlage speichern: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Speichern.';
}

header('Location: ../vereine_briefe.php?slug=' . urlencode($slug));
exit;
