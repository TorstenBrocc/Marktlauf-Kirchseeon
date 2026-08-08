#!/usr/bin/env php
<?php
/**
 * Papierkorb-Werkzeug für das geteilte Google-Laufwerk (CLI).
 *
 * Gedacht für den Fall, dass jemand eine Datei versehentlich global gelöscht hat
 * (driveTrash = Verschieben in den Drive-Papierkorb, wiederherstellbar).
 *
 * Befehle:
 *   php bin/drive_trash.php list                 Alle Dateien im Papierkorb auflisten (id  name)
 *   php bin/drive_trash.php restore <fid> [<fid>…]  Ein oder mehrere Elemente wiederherstellen
 *
 * Auf Strato per SSH (Shell meldet cgi-fcgi statt cli):
 *   MARKTLAUF_CLI=1 php bin/drive_trash.php list
 *
 * Bewusst nur Lesen + Wiederherstellen — es löscht nichts endgültig.
 */

declare(strict_types=1);

if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/google_drive.php';

$command = $argv[1] ?? 'list';

if (!driveConfigured()) {
    fwrite(STDERR, 'Drive ist nicht konfiguriert (storage/config.php prüfen).' . PHP_EOL);
    exit(1);
}

try {
    switch ($command) {
        case 'list':
            $items = driveListTrash();
            if (empty($items)) {
                echo 'Papierkorb ist leer.' . PHP_EOL;
                exit(0);
            }
            echo count($items) . ' Element(e) im Papierkorb:' . PHP_EOL;
            foreach ($items as $f) {
                $art = $f['isFolder'] ? '[Ordner]' : '[Datei] ';
                echo '  ' . $art . '  ' . $f['id'] . '  ' . $f['name']
                    . ($f['modifiedTime'] !== '' ? '  (' . $f['modifiedTime'] . ')' : '')
                    . PHP_EOL;
            }
            exit(0);

        case 'restore':
            $fids = array_slice($argv, 2);
            if (empty($fids)) {
                fwrite(STDERR, 'Keine Datei-ID angegeben. Nutzung: restore <fid> [<fid>…]' . PHP_EOL);
                exit(1);
            }
            $ok = 0;
            foreach ($fids as $fid) {
                $fid = trim((string) $fid);
                if ($fid === '') {
                    continue;
                }
                try {
                    driveRestore($fid);
                    echo 'Wiederhergestellt: ' . $fid . PHP_EOL;
                    $ok++;
                } catch (Throwable $e) {
                    fwrite(STDERR, 'Fehlgeschlagen (' . $fid . '): ' . $e->getMessage() . PHP_EOL);
                }
            }
            echo $ok . ' von ' . count($fids) . ' wiederhergestellt.' . PHP_EOL;
            exit($ok === count($fids) ? 0 : 1);

        default:
            fwrite(STDERR, 'Unbekannter Befehl. Nutzung: list | restore <fid> [<fid>…]' . PHP_EOL);
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
