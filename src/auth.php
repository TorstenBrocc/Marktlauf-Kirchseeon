<?php
/**
 * Authentifizierung und Session-Management
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function initSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

function generateCsrfToken(): string {
    initSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    initSession();

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isLoginRateLimited(string $email): bool {
    $pdo = getDbConnection();
    $config = getConfig();

    $maxAttempts = $config['security']['login_max_attempts'] ?? 5;
    $lockoutMinutes = $config['security']['login_lockout_minutes'] ?? 15;
    $ip = getClientIp();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM login_attempts
        WHERE ip = :ip AND email = :email
          AND ts > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
    ');
    $stmt->execute([
        'ip'      => $ip,
        'email'   => $email,
        'minutes' => $lockoutMinutes,
    ]);

    $count = (int) $stmt->fetchColumn();

    return $count >= $maxAttempts;
}

function recordLoginAttempt(string $email): void {
    $pdo = getDbConnection();
    $ip = getClientIp();

    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (:ip, :email)');
    $stmt->execute(['ip' => $ip, 'email' => $email]);
}

function cleanupOldLoginAttempts(): void {
    $pdo = getDbConnection();
    $pdo->exec('DELETE FROM login_attempts WHERE ts < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
}

function attemptLogin(string $email, string $password): ?array {
    if (isLoginRateLimited($email)) {
        return null;
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND active = 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['pass_hash'])) {
        recordLoginAttempt($email);
        return null;
    }

    initSession();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();

    return $user;
}

function isLoggedIn(): bool {
    initSession();
    return !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    initSession();

    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function requireLogin(): array {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    return getCurrentUser();
}

function requireRole(string $role): array {
    $user = requireLogin();

    if ($user['role'] !== $role && $user['role'] !== 'admin') {
        http_response_code(403);
        exit('Zugriff verweigert.');
    }

    return $user;
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user !== null && $user['role'] === 'admin';
}

function logout(): void {
    initSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function isRegisterRateLimited(): bool {
    $pdo = getDbConnection();
    $config = getConfig();

    $maxPerHour = $config['security']['register_max_per_hour'] ?? 10;
    $ip = getClientIp();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM register_attempts
        WHERE ip = :ip AND ts > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ');
    $stmt->execute(['ip' => $ip]);

    $count = (int) $stmt->fetchColumn();

    return $count >= $maxPerHour;
}

function recordRegisterAttempt(): void {
    $pdo = getDbConnection();
    $ip = getClientIp();

    $stmt = $pdo->prepare('INSERT INTO register_attempts (ip) VALUES (:ip)');
    $stmt->execute(['ip' => $ip]);
}

function cleanupOldRegisterAttempts(): void {
    $pdo = getDbConnection();
    $pdo->exec('DELETE FROM register_attempts WHERE ts < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
}
