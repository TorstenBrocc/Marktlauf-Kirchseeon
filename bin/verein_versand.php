#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Vereins-/Laufevent-Anschreiben aus der Sende-Queue versenden.
 *
 * Arbeitet die per Dashboard bestätigte Queue (verein_versand_queue, status='offen')
 * ab und versendet pro Empfänger EIN Anschreiben mit Delay dazwischen. Niemals als
 * Web-Request laufen lassen.
 *
 * Aufruf (SSH):
 *   MARKTLAUF_CLI=1 php bin/verein_versand.php
 *
 * Delay pro Mail: config['sponsor_versand_delay'] (Sekunden, Default 15) — geteilt
 * mit dem Sponsoren-Versand, damit die Strato-Drossel-Regel identisch bleibt.
 *
 * Optionen:
 *   --retry   Vor dem Lauf alle 'fehler'-Einträge auf 'offen' zurücksetzen.
 *
 * Concurrency: eigener MySQL GET_LOCK, damit parallele Läufe nicht doppelt senden.
 */

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/channels/mail.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/verein_status.php';

$config = getConfig();
$delay = (int) ($config['sponsor_versand_delay'] ?? 15);
$retry = in_array('--retry', $argv, true);

const VEREIN_VERSAND_LOCK = 'verein_versand_queue';

try {
    $pdo = getDbConnection();

    $gotLock = (int) $pdo->query("SELECT GET_LOCK('" . VEREIN_VERSAND_LOCK . "', 0)")->fetchColumn();
    if ($gotLock !== 1) {
        echo "Ein anderer Versandlauf ist bereits aktiv (Lock gesetzt). Abbruch.\n";
        exit(0);
    }

    if ($retry) {
        $reopened = $pdo->exec("UPDATE verein_versand_queue SET status = 'offen', fehler_text = NULL WHERE status = 'fehler'");
        echo "Retry: {$reopened} fehlgeschlagene Einträge auf 'offen' zurückgesetzt.\n";
    }

    $stmt = $pdo->query("
        SELECT id, verein_id, email, anrede, vorname, nachname, name, kategorie, anschreiben_typ, angefordert_von
        FROM verein_versand_queue
        WHERE status = 'offen'
        ORDER BY id ASC
    ");
    $jobs = $stmt->fetchAll();

    if (empty($jobs)) {
        echo "Keine offenen Einträge in der Sende-Queue.\n";
        exit(0);
    }

    $total = count($jobs);
    echo "Sende-Queue: {$total} Einträge, Delay {$delay}s pro Mail.\n\n";

    $sent = 0; $failed = 0; $i = 0;

    $markGesendet = $pdo->prepare("UPDATE verein_versand_queue SET status = 'gesendet', gesendet_am = NOW(), fehler_text = NULL WHERE id = :id");
    $markFehler = $pdo->prepare("UPDATE verein_versand_queue SET status = 'fehler', fehler_text = :fehler WHERE id = :id");

    foreach ($jobs as $job) {
        $i++;
        try {
            $ok = sendVereinAnschreiben(
                $job['email'],
                (string) ($job['anrede'] ?? ''),
                (string) ($job['vorname'] ?? ''),
                (string) ($job['nachname'] ?? ''),
                (string) $job['name'],
                (string) $job['kategorie'],
                (string) $job['anschreiben_typ'],
                (int) ($job['angefordert_von'] ?? 0)
            );

            if ($ok) {
                $markGesendet->execute(['id' => $job['id']]);
                vereinMarkGesendet($pdo, (int) $job['verein_id'], (string) $job['anschreiben_typ']);
                $sent++;
                echo "✓ [{$i}/{$total}] {$job['name']} → {$job['email']}\n";
            } else {
                $markFehler->execute(['id' => $job['id'], 'fehler' => 'Mail nicht gesendet']);
                $failed++;
                logError("Verein-Versand fehlgeschlagen Queue #{$job['id']}: Mail nicht gesendet");
                echo "✗ [{$i}/{$total}] {$job['name']} → {$job['email']} (fehlgeschlagen)\n";
            }
        } catch (Throwable $e) {
            $markFehler->execute(['id' => $job['id'], 'fehler' => mb_substr($e->getMessage(), 0, 500)]);
            $failed++;
            logError("Verein-Versand Exception Queue #{$job['id']}: " . $e->getMessage());
            echo "✗ [{$i}/{$total}] {$job['name']} → Exception: {$e->getMessage()}\n";
        }

        if ($i < $total && $delay > 0) {
            sleep($delay);
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('" . VEREIN_VERSAND_LOCK . "')");
    echo "\nFertig. Gesendet: {$sent}, Fehlgeschlagen: {$failed}\n";
    if ($failed > 0) {
        echo "Hinweis: {$failed} fehlgeschlagen — erneuter Versuch mit: php bin/verein_versand.php --retry\n";
    }
} catch (PDOException $e) {
    logError('Verein-Versand CLI DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}
