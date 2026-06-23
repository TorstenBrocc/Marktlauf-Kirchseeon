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
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $config = getConfig();
    $trustedProxy = $config['security']['trusted_proxy'] ?? null;

    if ($trustedProxy !== null && $remoteAddr === $trustedProxy) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
    }

    return $remoteAddr;
}

function getRateLimitKey(): string {
    $ip = getClientIp();
    $binary = @inet_pton($ip);

    if ($binary === false) {
        return $ip;
    }

    if (strlen($binary) === 16) {
        $binary = substr($binary, 0, 8) . str_repeat("\0", 8);
        return inet_ntop($binary);
    }

    return $ip;
}

function normalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function isLoginRateLimited(string $email): bool {
    $pdo = getDbConnection();
    $config = getConfig();

    $maxAttempts = $config['security']['login_max_attempts'] ?? 5;
    $maxAttemptsPerIp = $config['security']['login_max_attempts_per_ip'] ?? 20;
    $lockoutMinutes = $config['security']['login_lockout_minutes'] ?? 15;
    $ipKey = getRateLimitKey();
    $emailNorm = normalizeEmail($email);

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM login_attempts
        WHERE ip = :ip AND email = :email
          AND ts > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
    ');
    $stmt->execute([
        'ip'      => $ipKey,
        'email'   => $emailNorm,
        'minutes' => $lockoutMinutes,
    ]);
    $countPerEmail = (int) $stmt->fetchColumn();

    if ($countPerEmail >= $maxAttempts) {
        return true;
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM login_attempts
        WHERE ip = :ip
          AND ts > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
    ');
    $stmt->execute([
        'ip'      => $ipKey,
        'minutes' => $lockoutMinutes,
    ]);
    $countPerIp = (int) $stmt->fetchColumn();

    return $countPerIp >= $maxAttemptsPerIp;
}

function recordLoginAttempt(string $email): void {
    $pdo = getDbConnection();
    $ipKey = getRateLimitKey();
    $emailNorm = normalizeEmail($email);

    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (:ip, :email)');
    $stmt->execute(['ip' => $ipKey, 'email' => $emailNorm]);

    if (random_int(1, 100) === 1) {
        cleanupOldLoginAttempts();
    }
}

function cleanupOldLoginAttempts(): void {
    $pdo = getDbConnection();
    $pdo->exec('DELETE FROM login_attempts WHERE ts < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
}

function attemptLogin(string $email, string $password): ?array {
    $emailNorm = normalizeEmail($email);

    if (isLoginRateLimited($emailNorm)) {
        return null;
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = :email AND active = 1');
    $stmt->execute(['email' => $emailNorm]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['pass_hash'])) {
        recordLoginAttempt($emailNorm);
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
    $ipKey = getRateLimitKey();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM register_attempts
        WHERE ip = :ip AND ts > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ');
    $stmt->execute(['ip' => $ipKey]);

    $count = (int) $stmt->fetchColumn();

    return $count >= $maxPerHour;
}

function recordRegisterAttempt(): void {
    $pdo = getDbConnection();
    $ipKey = getRateLimitKey();

    $stmt = $pdo->prepare('INSERT INTO register_attempts (ip) VALUES (:ip)');
    $stmt->execute(['ip' => $ipKey]);

    if (random_int(1, 100) === 1) {
        cleanupOldRegisterAttempts();
    }
}

function cleanupOldRegisterAttempts(): void {
    $pdo = getDbConnection();
    $pdo->exec('DELETE FROM register_attempts WHERE ts < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
}
