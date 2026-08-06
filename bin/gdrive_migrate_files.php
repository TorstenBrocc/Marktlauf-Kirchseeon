#!/usr/bin/env php
<?php
/**
 * One-time migration: move existing local files (storage/files/*) into the shared
 * Google drive (intern/gdrive-storage-spec.md, Paket 4). Idempotent: only rows with
 * provider='local' and an existing local file are processed, so it is safe to re-run.
 * After a successful upload the row is flipped to provider='drive' + drive_file_id and
 * the local copy is removed (pass --keep to retain local copies; --dry-run to preview).
 *
 *   MARKTLAUF_CLI=1 php bin/gdrive_migrate_files.php [--keep] [--dry-run]
 */

declare(strict_types=1);

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/google_drive.php';

// Strato runs bin/ scripts under the CGI SAPI, where STDOUT/STDERR are undefined.
// Map them to the output stream so fwrite() works there just like under real CLI.
if (!defined('STDOUT')) {
    define('STDOUT', fopen('php://output', 'w'));
}
if (!defined('STDERR')) {
    define('STDERR', fopen('php://output', 'w'));
}

$keep   = in_array('--keep', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

if (!driveConfigured()) {
    fwrite(STDERR, "Google Drive ist nicht konfiguriert (storage/config.php fehlt Keys). Abbruch.\n");
    exit(1);
}

$pdo  = getDbConnection();
$rows = $pdo->query("
    SELECT id, bereich, kategorie, jahr, dateiname, originalname, mimetype
    FROM dateien
    WHERE provider = 'local' AND (drive_file_id IS NULL OR drive_file_id = '')
    ORDER BY id ASC
")->fetchAll();

$done = 0;
$skipped = 0;
$failed = 0;

foreach ($rows as $r) {
    $path = __DIR__ . '/../storage/files/' . $r['bereich'] . '/' . $r['dateiname'];
    $kat  = ((string) ($r['kategorie'] ?? '')) ?: 'allgemein';
    $jahr = (int) ($r['jahr'] ?? 0) ?: driveAktivesJahr($pdo);

    if (!is_file($path)) {
        fwrite(STDOUT, "SKIP  #{$r['id']} {$r['originalname']} (lokale Datei fehlt)\n");
        $skipped++;
        continue;
    }
    if ($dryRun) {
        fwrite(STDOUT, "DRY   #{$r['id']} {$r['originalname']} -> {$r['bereich']}/{$jahr}/{$kat}\n");
        continue;
    }

    try {
        $fileId = driveUpload($pdo, (string) $r['bereich'], $jahr, $kat, $path, (string) $r['originalname'], (string) $r['mimetype']);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "FAIL  #{$r['id']} {$r['originalname']}: " . $e->getMessage() . "\n");
        $failed++;
        continue;
    }

    $pdo->prepare("UPDATE dateien SET drive_file_id = ?, provider = 'drive' WHERE id = ?")
        ->execute([$fileId, (int) $r['id']]);
    if (!$keep) {
        @unlink($path);
    }
    fwrite(STDOUT, "OK    #{$r['id']} {$r['originalname']} -> {$fileId}\n");
    $done++;
}

fwrite(STDOUT, "\nFertig: $done migriert, $skipped übersprungen, $failed fehlgeschlagen.\n");
exit($failed > 0 ? 1 : 0);
