<?php
/**
 * Datenbankverbindung (PDO)
 */

declare(strict_types=1);

function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../storage/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('config.php nicht gefunden. Kopiere config.sample.php zu config.php.');
    }

    $config = require $configPath;
    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function getConfig(): array {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/../storage/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('config.php nicht gefunden.');
    }

    $config = require $configPath;
    return $config;
}
