#!/usr/bin/env php
<?php
/**
 * EINMALIGES read-only Diagnose-Skript: zählt vor dem Rückbau, was in der Alt-Tabelle
 * `dateien` steht (Gesamt + Aufschlüsselung nach provider/bereich). Nur SELECT, ändert
 * nichts. Wird nach der Bilanz wieder entfernt.
 *   MARKTLAUF_CLI=1 php bin/dateien_count.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

foreach (['dateien', 'drive_kategorie_ordner'] as $t) {
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo str_pad($t, 24) . ': ' . $n . " Zeilen\n";
    } catch (Throwable $e) {
        echo str_pad($t, 24) . ": (nicht vorhanden)\n";
    }
}

try {
    echo "\n dateien nach provider:\n";
    foreach ($pdo->query('SELECT provider, COUNT(*) c FROM dateien GROUP BY provider') as $r) {
        echo '   ' . str_pad((string) $r['provider'], 10) . ': ' . $r['c'] . "\n";
    }
    echo " dateien nach bereich:\n";
    foreach ($pdo->query('SELECT bereich, COUNT(*) c FROM dateien GROUP BY bereich') as $r) {
        echo '   ' . str_pad((string) $r['bereich'], 10) . ': ' . $r['c'] . "\n";
    }
} catch (Throwable $e) {
    // dateien evtl. bereits entfernt
}
