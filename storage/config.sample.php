<?php
/**
 * Konfiguration für ATSV Kirchseeon Marktlauf Dashboard
 *
 * ANLEITUNG:
 * 1. Kopiere diese Datei zu config.php
 * 2. Fülle die echten Werte ein
 * 3. config.php niemals committen!
 */

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'marktlauf_db',
        'user'     => 'marktlauf_user',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'app' => [
        'url'         => 'https://atsv-kirchseeon-marktlauf.de',
        'environment' => 'production',
    ],

    'mail' => [
        'from_address' => 'info@atsv-kirchseeon-marktlauf.de',
        'from_name'    => 'ATSV Kirchseeon Marktlauf',
    ],

    'security' => [
        'login_max_attempts'        => 5,
        'login_max_attempts_per_ip' => 20,
        'login_lockout_minutes'     => 15,
        'register_max_per_hour'     => 10,

        // Nur setzen, wenn hinter einem Reverse-Proxy (z.B. Cloudflare, nginx).
        // Dann X-Forwarded-For vom Proxy auswerten. Sonst REMOTE_ADDR nutzen.
        // 'trusted_proxy' => '127.0.0.1',
    ],
];
