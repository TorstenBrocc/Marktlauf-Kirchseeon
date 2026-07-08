#!/usr/bin/env php
<?php
/**
 * Migration-Runner (Route 2)
 *
 * Wendet die SQL-Dateien aus migrations/ nachvollziehbar und idempotent an.
 * Angewendete Migrationen werden in der Tabelle `schema_migrations` vermerkt.
 *
 * Befehle:
 *   php bin/migrate.php status     Angewendete + offene Migrationen anzeigen
 *   php bin/migrate.php migrate    Alle offenen migrations/*.sql anwenden (Default)
 *   php bin/migrate.php baseline   ALLE vorhandenen Migrationen als angewendet
 *                                  markieren, OHNE sie auszuführen — einmalig bei
 *                                  Übernahme auf einer bereits migrierten DB (001–012)
 *
 * Auf Strato per SSH (Shell meldet cgi-fcgi statt cli):
 *   MARKTLAUF_CLI=1 php bin/migrate.php status
 *
 * Grenzen: DDL (CREATE/ALTER/DROP) committet auf MySQL implizit — kein
 * automatisches Rollback bei Fehler mitten in einer Datei. Vor destruktiven
 * Migrationen Backup ziehen. Migrationsdateien enthalten reines SQL (DDL/DML),
 * keine SELECT-Ausgaben.
 */

declare(strict_types=1);

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';

$command = $argv[1] ?? 'migrate';
$migrationsDir = __DIR__ . '/../migrations';

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB-Verbindung fehlgeschlagen: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Tracking-Tabelle sicherstellen
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename   VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// Nur echte Migrationsdateien (*.sql, nicht *.sql.sample), alphabetisch sortiert
$paths = glob($migrationsDir . '/*.sql') ?: [];
$files = array_map('basename', $paths);
sort($files, SORT_STRING);

$applied = array_flip($pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$pending = array_values(array_filter($files, static fn($f) => !isset($applied[$f])));

switch ($command) {
    case 'status':
        foreach ($files as $f) {
            echo (isset($applied[$f]) ? '  [x] ' : '  [ ] ') . $f . PHP_EOL;
        }
        echo PHP_EOL . 'Offen: ' . count($pending) . PHP_EOL;
        exit(0);

    case 'baseline':
        $ins = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (:f)');
        foreach ($files as $f) {
            $ins->execute(['f' => $f]);
        }
        echo 'Baseline gesetzt: ' . count($files) . ' Migrationen als angewendet markiert (nicht ausgeführt).' . PHP_EOL;
        exit(0);

    case 'migrate':
        if (!$pending) {
            echo 'Keine offenen Migrationen.' . PHP_EOL;
            exit(0);
        }
        $record = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:f)');
        foreach ($pending as $f) {
            $sql = file_get_contents($migrationsDir . '/' . $f);
            if ($sql === false || trim($sql) === '') {
                fwrite(STDERR, "Übersprungen (leer/nicht lesbar): $f" . PHP_EOL);
                continue;
            }
            echo "-> $f ... ";
            try {
                $pdo->exec($sql);
                $record->execute(['f' => $f]);
                echo 'ok' . PHP_EOL;
            } catch (Throwable $e) {
                echo 'FEHLER' . PHP_EOL;
                logError('migrate: ' . $f . ' — ' . $e->getMessage());
                fwrite(STDERR, "Migration $f fehlgeschlagen: " . $e->getMessage() . PHP_EOL);
                fwrite(STDERR, 'Achtung: DDL committet implizit — kein Auto-Rollback. Zustand prüfen.' . PHP_EOL);
                exit(1);
            }
        }
        echo 'Fertig.' . PHP_EOL;
        exit(0);

    default:
        fwrite(STDERR, "Unbekannter Befehl: $command (status|migrate|baseline)" . PHP_EOL);
        exit(2);
}
