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
        'login_max_attempts'    => 5,
        'login_lockout_minutes' => 15,
        'register_max_per_hour' => 10,
    ],
];
