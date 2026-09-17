#!/usr/bin/env php
<?php
/**
 * Seed for the staging stage ("Buehne S", Claudex): creates/refreshes ONE synthetic
 * admin user so the headless UI/UX gate can log into orga/.
 *
 * Safety: refuses to run unless storage/config.php says app.environment === 'staging'.
 * Never run against production data — this is the only guard between seed and CRM.
 *
 * Usage (on the server):  cd <Staging> && MARKTLAUF_CLI=1 /bin/php bin/seed_staging.php
 * The generated password is written ONLY to storage/.staging-admin.txt (chmod 600),
 * never to stdout.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

$root   = dirname(__DIR__);
$config = require $root . '/storage/config.php';

if (($config['app']['environment'] ?? '') !== 'staging') {
    // STDERR is undefined under Strato's cgi-fcgi SAPI — open it explicitly.
    fwrite(fopen('php://stderr', 'w'), "ABBRUCH: app.environment ist nicht 'staging' — Seed laeuft nur auf der Buehne.\n");
    exit(2);
}

$db  = $config['db'];
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], (int) $db['port'], $db['name'], $db['charset']);
$pdo = new PDO($dsn, $db['user'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$email = 'claudex-admin@staging.invalid';   // RFC 2606 reserved TLD: can never receive mail
$name  = 'Claudex Test-Admin';
$pw    = getenv('STAGING_ADMIN_PASSWORD') ?: rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
$hash  = password_hash($pw, PASSWORD_ARGON2ID);

$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, pass_hash, role, active) VALUES (:n, :e, :h, "admin", 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), pass_hash = VALUES(pass_hash), role = "admin", active = 1'
);
$stmt->execute([':n' => $name, ':e' => $email, ':h' => $hash]);

$secretFile = $root . '/storage/.staging-admin.txt';
file_put_contents($secretFile, "email={$email}\npassword={$pw}\n");
chmod($secretFile, 0600);

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "Seed ok: Test-Admin {$email} angelegt/aktualisiert. users gesamt: {$count}. Passwort in storage/.staging-admin.txt (600).\n";
