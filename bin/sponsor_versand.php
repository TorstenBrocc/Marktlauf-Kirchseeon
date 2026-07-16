#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Sponsor-Anschreiben aus der Sende-Queue versenden
 * Grundlage: intern/sponsor-crm-ausbau.md §5
 *
 * Arbeitet die per Dashboard bestätigte Queue (sponsor_versand_queue, status='offen')
 * ab und versendet pro Empfänger EIN Anschreiben mit Delay dazwischen, damit Strato
 * nicht drosselt. Niemals als Web-Request laufen lassen.
 *
 * Aufruf (SSH):
 *   MARKTLAUF_CLI=1 php bin/sponsor_versand.php
 *
 * Delay pro Mail: config['sponsor_versand_delay'] (Sekunden, Default 15).
 *
 * Optionen:
 *   --retry   Vor dem Lauf alle 'fehler'-Einträge auf 'offen' zurücksetzen
 *             (erneuter Versandversuch für zuvor fehlgeschlagene Mails).
 *
 * Concurrency: sichert sich per MySQL GET_LOCK ab, damit zwei parallele Läufe
 * (z.B. Cron + manuell) nicht dieselben Mails doppelt versenden.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/channels/mail.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/sponsor_status.php';

$config = getConfig();
$delay = (int) ($config['sponsor_versand_delay'] ?? 15);
$retry = in_array('--retry', $argv, true);

const SPONSOR_VERSAND_LOCK = 'sponsor_versand_queue';

try {
    $pdo = getDbConnection();

    // Nur ein Versandlauf gleichzeitig (Cron + manuell dürfen sich nicht überholen).
    $gotLock = (int) $pdo->query("SELECT GET_LOCK('" . SPONSOR_VERSAND_LOCK . "', 0)")->fetchColumn();
    if ($gotLock !== 1) {
        echo "Ein anderer Versandlauf ist bereits aktiv (Lock gesetzt). Abbruch.\n";
        exit(0);
    }

    if ($retry) {
        $reopened = $pdo->exec("UPDATE sponsor_versand_queue SET status = 'offen', fehler_text = NULL WHERE status = 'fehler'");
        echo "Retry: {$reopened} fehlgeschlagene Einträge auf 'offen' zurückgesetzt.\n";
    }

    $stmt = $pdo->query("
        SELECT id, sponsor_id, email, anrede, vorname, nachname, firma, paket, anschreiben_typ, angefordert_von
        FROM sponsor_versand_queue
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

    $sent = 0;
    $failed = 0;
    $i = 0;

    $markGesendet = $pdo->prepare("UPDATE sponsor_versand_queue SET status = 'gesendet', gesendet_am = NOW(), fehler_text = NULL WHERE id = :id");
    $markFehler = $pdo->prepare("UPDATE sponsor_versand_queue SET status = 'fehler', fehler_text = :fehler WHERE id = :id");

    foreach ($jobs as $job) {
        $i++;
        try {
            $ok = sendSponsorAnschreiben(
                $job['email'],
                $job['anrede'],
                (string) ($job['vorname'] ?? ''),
                $job['nachname'],
                $job['firma'],
                $job['anschreiben_typ'],
                (string) ($job['paket'] ?? ''),
                (int) ($job['angefordert_von'] ?? 0)
            );

            if ($ok) {
                $markGesendet->execute(['id' => $job['id']]);
                sponsorMarkGesendet($pdo, (int) $job['sponsor_id'], $job['anschreiben_typ']);
                $sent++;
                echo "✓ [{$i}/{$total}] {$job['firma']} → {$job['email']}\n";
            } else {
                $markFehler->execute(['id' => $job['id'], 'fehler' => 'Mail nicht gesendet']);
                $failed++;
                logError("Sponsor-Versand fehlgeschlagen Queue #{$job['id']}: Mail nicht gesendet");
                echo "✗ [{$i}/{$total}] {$job['firma']} → {$job['email']} (fehlgeschlagen)\n";
            }
        } catch (Throwable $e) {
            $markFehler->execute(['id' => $job['id'], 'fehler' => mb_substr($e->getMessage(), 0, 500)]);
            $failed++;
            logError("Sponsor-Versand Exception Queue #{$job['id']}: " . $e->getMessage());
            echo "✗ [{$i}/{$total}] {$job['firma']} → Exception: {$e->getMessage()}\n";
        }

        // Delay nur zwischen den Mails, nicht nach der letzten
        if ($i < $total && $delay > 0) {
            sleep($delay);
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('" . SPONSOR_VERSAND_LOCK . "')");
    echo "\nFertig. Gesendet: {$sent}, Fehlgeschlagen: {$failed}\n";
    if ($failed > 0) {
        echo "Hinweis: {$failed} fehlgeschlagen — erneuter Versuch mit: php bin/sponsor_versand.php --retry\n";
    }
} catch (PDOException $e) {
    logError('Sponsor-Versand CLI DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}
