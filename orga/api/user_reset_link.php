<?php
/**
 * Passwort-Reset-Link erzeugen (POST) — nur Admin.
 *
 * Erzeugt einen frischen Set-Passwort-Token (invite_tokens) für einen bestehenden
 * Benutzer, verschickt eine Reset-Mail und zeigt dem Admin den Link zum manuellen
 * Weitergeben. Wiederverwendung des Einladungs-Flows: das neue Passwort wird über
 * einladung.php?token=... gesetzt (dort reset-bewusst formuliert).
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
    $_SESSION['flash_error'] = 'Nur Admins können Passwörter zurücksetzen.';
    header('Location: ../benutzer.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Ungültiger Benutzer.';
    header('Location: ../benutzer.php');
    exit;
}

try {
    $pdo = getDbConnection();

    $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id');
    $userStmt->execute(['id' => $userId]);
    $targetUser = $userStmt->fetch();

    if (!$targetUser) {
        $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
        header('Location: ../benutzer.php');
        exit;
    }

    $pdo->beginTransaction();

    // Alte, noch nicht genutzte Tokens entwerten — es soll nur ein gültiger Link existieren.
    $pdo->prepare('DELETE FROM invite_tokens WHERE user_id = :id AND used_at IS NULL')
        ->execute(['id' => $userId]);

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));

    $pdo->prepare('INSERT INTO invite_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)')
        ->execute(['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);

    $pdo->commit();

    $config = getConfig();
    $appUrl = rtrim($config['app']['url'] ?? 'https://atsv-kirchseeon-marktlauf.de', '/');
    $resetLink = $appUrl . '/orga/einladung.php?token=' . urlencode($token);

    $mailOk = false;
    try {
        $mailOk = sendPasswortReset($targetUser['email'], $targetUser['name'], $resetLink);
    } catch (Throwable $e) {
        logError('Reset mail error: ' . $e->getMessage());
    }

    // Link dem Admin zum Weitergeben zeigen (unabhängig vom Mailversand).
    $_SESSION['flash_reset_link'] = $resetLink;
    $_SESSION['flash_reset_name'] = $targetUser['name'];
    $_SESSION['flash_reset_mail_ok'] = $mailOk;
    $_SESSION['flash_success'] = $mailOk
        ? ('Reset-Link für ' . $targetUser['name'] . ' erzeugt und an ' . $targetUser['email'] . ' gesendet.')
        : ('Reset-Link für ' . $targetUser['name'] . ' erzeugt. E-Mail-Versand fehlgeschlagen — bitte den Link unten manuell weitergeben.');

    header('Location: ../benutzer.php');
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('Reset link error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../benutzer.php');
    exit;
}
