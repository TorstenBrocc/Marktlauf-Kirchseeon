<?php
/**
 * Benutzer einladen (POST) — nur Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/channels/mail.php';
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
    $_SESSION['flash_error'] = 'Nur Admins können Benutzer einladen.';
    header('Location: ../benutzer.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'orga';

if ($name === '' || $email === '') {
    $_SESSION['flash_error'] = 'Name und E-Mail sind Pflichtfelder.';
    header('Location: ../benutzer.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Ungültige E-Mail-Adresse.';
    header('Location: ../benutzer.php');
    exit;
}

if (!in_array($role, ['admin', 'orga'], true)) {
    $role = 'orga';
}

try {
    $pdo = getDbConnection();

    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email)');
    $checkStmt->execute(['email' => $email]);
    if ($checkStmt->fetch()) {
        $_SESSION['flash_error'] = 'Diese E-Mail-Adresse ist bereits registriert.';
        header('Location: ../benutzer.php');
        exit;
    }

    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('
        INSERT INTO users (name, email, pass_hash, role, active)
        VALUES (:name, :email, :pass_hash, :role, 0)
    ');
    $userStmt->execute([
        'name'      => $name,
        'email'     => $email,
        'pass_hash' => '',
        'role'      => $role,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $tokenStmt = $pdo->prepare('
        INSERT INTO invite_tokens (user_id, token, expires_at)
        VALUES (:user_id, :token, :expires_at)
    ');
    $tokenStmt->execute([
        'user_id'    => $userId,
        'token'      => $token,
        'expires_at' => $expiresAt,
    ]);

    $pdo->commit();

    $config = getConfig();
    $appUrl = rtrim($config['app']['url'] ?? 'https://atsv-kirchseeon-marktlauf.de', '/');
    $inviteLink = $appUrl . '/orga/einladung.php?token=' . urlencode($token);

    try {
        sendUserInvite($email, $name, $inviteLink, $role);
        $_SESSION['flash_success'] = 'Einladung an ' . htmlspecialchars($email) . ' versendet.';
    } catch (Throwable $e) {
        logError('Invite mail error: ' . $e->getMessage());
        $_SESSION['flash_success'] = 'Benutzer angelegt. E-Mail konnte nicht gesendet werden (siehe Log).';
    }

    header('Location: ../benutzer.php');
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('User invite error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../benutzer.php');
    exit;
}
