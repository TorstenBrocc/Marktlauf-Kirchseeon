<?php
/**
 * Benutzer / eigenes Profil speichern (POST)
 * - Admin kann alle Benutzer bearbeiten (inkl. Rolle)
 * - Jeder Benutzer kann sein eigenes Profil bearbeiten (ohne Rollenwechsel)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../benutzer_edit.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../benutzer_edit.php');
    exit;
}

$currentUser = getCurrentUserFromGuard();
$isAdmin     = isAdminFromGuard();
$userId      = (int) ($_POST['user_id'] ?? 0);
$isSelf      = $userId === $currentUser['id'];

if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Benutzer-ID.';
    header('Location: ../benutzer_edit.php');
    exit;
}

if (!$isAdmin && !$isSelf) {
    $_SESSION['flash_error'] = 'Keine Berechtigung.';
    header('Location: ../index.php');
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$telefon = trim($_POST['telefon'] ?? '');
$aufgabe = trim($_POST['aufgabe'] ?? '');
$role    = trim($_POST['role']    ?? '');

if ($name === '' || mb_strlen($name) > 100) {
    $_SESSION['flash_error'] = 'Name ist erforderlich (max. 100 Zeichen).';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;
}

if ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Gültige E-Mail-Adresse erforderlich.';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;
}

if (mb_strlen($telefon) > 50) {
    $_SESSION['flash_error'] = 'Telefonnummer zu lang (max. 50 Zeichen).';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;
}

if (mb_strlen($aufgabe) > 150) {
    $_SESSION['flash_error'] = 'Aufgabe zu lang (max. 150 Zeichen).';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;
}

if ($isAdmin && !$isSelf && $role !== 'admin' && $role !== 'orga') {
    $_SESSION['flash_error'] = 'Rolle muss "admin" oder "orga" sein.';
    header('Location: ../benutzer_edit.php?id=' . $userId);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
        header('Location: ../benutzer.php');
        exit;
    }

    $emailNorm = strtolower($email);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :email AND id != :id');
    $stmt->execute(['email' => $emailNorm, 'id' => $userId]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet.';
        header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
        exit;
    }

    if ($isAdmin && !$isSelf && in_array($role, ['admin','orga'], true)) {
        $stmt = $pdo->prepare('UPDATE users SET name=:name, email=:email, telefon=:telefon, aufgabe=:aufgabe, role=:role WHERE id=:id');
        $stmt->execute(['name'=>$name,'email'=>$email,'telefon'=>$telefon ?: null,'aufgabe'=>$aufgabe ?: null,'role'=>$role,'id'=>$userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET name=:name, email=:email, telefon=:telefon, aufgabe=:aufgabe WHERE id=:id');
        $stmt->execute(['name'=>$name,'email'=>$email,'telefon'=>$telefon ?: null,'aufgabe'=>$aufgabe ?: null,'id'=>$userId]);
    }

    if ($isSelf) {
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
    }

    $_SESSION['flash_success'] = $isSelf ? 'Profil gespeichert.' : 'Benutzer gespeichert.';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;

} catch (PDOException $e) {
    logError('User update error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../benutzer_edit.php' . ($isSelf ? '' : '?id=' . $userId));
    exit;
}
