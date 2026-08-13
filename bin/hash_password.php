#!/usr/bin/env php
<?php
/**
 * CLI-Tool zum Generieren von argon2id Passwort-Hashes
 *
 * Verwendung: php bin/hash_password.php
 * Das Passwort wird ohne Echo eingelesen (sicher).
 * Nur der Hash wird auf stdout ausgegeben.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

function readPasswordSecure(string $prompt): string {
    echo $prompt;

    $sttyAvailable = false;
    $originalSettings = '';

    if (function_exists('shell_exec')) {
        $originalSettings = shell_exec('stty -g 2>/dev/null');
        if ($originalSettings !== null) {
            shell_exec('stty -echo 2>/dev/null');
            $sttyAvailable = true;
        }
    }

    $password = fgets(STDIN);

    if ($sttyAvailable) {
        shell_exec('stty ' . escapeshellarg(trim($originalSettings)) . ' 2>/dev/null');
    }

    echo PHP_EOL;

    return trim($password);
}

$password = readPasswordSecure('Passwort eingeben: ');

if (strlen($password) < 8) {
    fwrite(STDERR, "Fehler: Passwort muss mindestens 8 Zeichen lang sein.\n");
    exit(1);
}

$confirm = readPasswordSecure('Passwort bestätigen: ');

if ($password !== $confirm) {
    fwrite(STDERR, "Fehler: Passwörter stimmen nicht überein.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_ARGON2ID);

echo $hash . PHP_EOL;
