<?php
/**
 * Benutzer bearbeiten (POST) — nur Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage.']);
    exit;
}

if (!isAdminFromGuard()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nur Admins können Benutzer bearbeiten.']);
    exit;
}

$currentUser = getCurrentUserFromGuard();
$userId = (int) ($_POST['user_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Benutzer-ID.']);
    exit;
}

if ($userId === $currentUser['id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Du kannst deinen eigenen Account nicht über diesen Endpoint bearbeiten.']);
    exit;
}

if ($name === '' || mb_strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name ist erforderlich (max. 100 Zeichen).']);
    exit;
}

if ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Gültige E-Mail-Adresse erforderlich (max. 255 Zeichen).']);
    exit;
}

if ($role !== 'admin' && $role !== 'orga') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rolle muss "admin" oder "orga" sein.']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden.']);
        exit;
    }

    $emailNorm = strtolower($email);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :email AND id != :id');
    $stmt->execute(['email' => $emailNorm, 'id' => $userId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id');
    $stmt->execute([
        'name'  => $name,
        'email' => $email,
        'role'  => $role,
        'id'    => $userId,
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    logError('User update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler.']);
}
