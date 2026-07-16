<?php
/**
 * Benutzer löschen (POST) — nur Admin.
 *
 * Gedacht für falsch angelegte / nie aktivierte Einladungen. Aktive Benutzer werden
 * über die UI erst gar nicht zum Löschen angeboten (dort nur Deaktivieren) — als
 * zweite Sicherung wird hier zusätzlich geprüft.
 *
 * FK-Verhalten (siehe migrations): invite_tokens → ON DELETE CASCADE,
 * aufgaben.verantwortlich_user_id → ON DELETE SET NULL, dateien.hochgeladen_von →
 * RESTRICT. Ein Löschen mit verknüpften Dateien schlägt daher bewusst fehl und wird
 * hier abgefangen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../benutzer.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../benutzer.php');
    exit;
}

if (!isAdminFromGuard()) {
    $_SESSION['flash_error'] = 'Nur Admins können Benutzer löschen.';
    header('Location: ../benutzer.php');
    exit;
}

$currentUser = getCurrentUserFromGuard();
$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../benutzer.php');
    exit;
}

if ($userId === $currentUser['id']) {
    $_SESSION['flash_error'] = 'Du kannst dich nicht selbst löschen.';
    header('Location: ../benutzer.php');
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id, name, active FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $target = $stmt->fetch();

    if (!$target) {
        $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
        header('Location: ../benutzer.php');
        exit;
    }

    if ((int) $target['active'] === 1) {
        $_SESSION['flash_error'] = 'Aktive Benutzer können nicht gelöscht werden. Bitte zuerst deaktivieren.';
        header('Location: ../benutzer.php');
        exit;
    }

    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $del->execute(['id' => $userId]);

    $_SESSION['flash_success'] = 'Benutzer „' . $target['name'] . '" gelöscht.';
    header('Location: ../benutzer.php');
    exit;

} catch (PDOException $e) {
    logError('User delete error: ' . $e->getMessage());
    // 23000 = Integrity constraint violation (z. B. verknüpfte Dateien)
    if ($e->getCode() === '23000') {
        $_SESSION['flash_error'] = 'Benutzer hat noch verknüpfte Daten (z. B. hochgeladene Dateien) und kann nicht gelöscht werden. Bitte stattdessen deaktivieren.';
    } else {
        $_SESSION['flash_error'] = 'Datenbankfehler.';
    }
    header('Location: ../benutzer.php');
    exit;
}
