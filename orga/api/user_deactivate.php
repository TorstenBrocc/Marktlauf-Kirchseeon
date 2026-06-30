<?php
/**
 * Benutzer deaktivieren (POST) — nur Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';

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
    $_SESSION['flash_error'] = 'Nur Admins können Benutzer deaktivieren.';
    header('Location: ../benutzer.php');
    exit;
}

$currentUser = getCurrentUserFromGuard();
$userId = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? 'deactivate';

if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../benutzer.php');
    exit;
}

if ($userId === $currentUser['id']) {
    $_SESSION['flash_error'] = 'Du kannst dich nicht selbst deaktivieren.';
    header('Location: ../benutzer.php');
    exit;
}

try {
    $pdo = getDbConnection();

    if ($action === 'activate') {
        $stmt = $pdo->prepare('UPDATE users SET active = 1 WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $_SESSION['flash_success'] = 'Benutzer aktiviert.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET active = 0 WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $_SESSION['flash_success'] = 'Benutzer deaktiviert.';
    }

    header('Location: ../benutzer.php');
    exit;

} catch (PDOException $e) {
    error_log('User deactivate error: ' . $e->getMessage(), 3, __DIR__ . '/../../storage/logs/error.log');
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../benutzer.php');
    exit;
}
