<?php
/**
 * Benutzer bearbeiten (POST)
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

$currentUser = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$targetUserId = (int) ($_POST['user_id'] ?? 0);
$isSelf = $targetUserId === $currentUser['id'];

if ($targetUserId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../benutzer.php');
    exit;
}

if (!$isAdmin && !$isSelf) {
    $_SESSION['flash_error'] = 'Keine Berechtigung.';
    header('Location: ../benutzer.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? null;

if ($name === '' || $email === '') {
    $_SESSION['flash_error'] = 'Name und E-Mail sind Pflichtfelder.';
    header('Location: ../benutzer_edit.php?id=' . $targetUserId);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
    header('Location: ../benutzer_edit.php?id=' . $targetUserId);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $targetUserId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
        header('Location: ../benutzer.php');
        exit;
    }

    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) AND id != :id');
    $checkStmt->execute(['email' => $email, 'id' => $targetUserId]);
    if ($checkStmt->fetch()) {
        $_SESSION['flash_error'] = 'Diese E-Mail-Adresse wird bereits verwendet.';
        header('Location: ../benutzer_edit.php?id=' . $targetUserId);
        exit;
    }

    $newRole = $targetUser['role'];
    if ($role !== null && in_array($role, ['admin', 'orga'], true)) {
        if ($isAdmin && !$isSelf) {
            $newRole = $role;
        }
    }

    $updateStmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id');
    $updateStmt->execute([
        'name'  => $name,
        'email' => $email,
        'role'  => $newRole,
        'id'    => $targetUserId,
    ]);

    if ($isSelf) {
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
    }

    $_SESSION['flash_success'] = 'Benutzer aktualisiert.';

    if ($isSelf && !$isAdmin) {
        header('Location: ../index.php');
    } else {
        header('Location: ../benutzer_edit.php?id=' . $targetUserId);
    }
    exit;

} catch (PDOException $e) {
    logError('User update error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../benutzer_edit.php?id=' . $targetUserId);
    exit;
}
