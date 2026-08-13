#!/usr/bin/env php
<?php
/**
 * CLI-Tool zum Generieren von Access-Tokens
 *
 * Verwendung: php bin/gen_token.php
 * Gibt einen 64-Zeichen-Hex-Token aus (32 Bytes Entropie).
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

$token = bin2hex(random_bytes(32));

echo $token . PHP_EOL;
