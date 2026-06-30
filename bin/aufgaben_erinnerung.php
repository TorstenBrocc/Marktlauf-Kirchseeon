#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Aufgaben-Erinnerungen versenden
 *
 * Täglich per Cron ausführen:
 * 0 8 * * * /usr/bin/php /path/to/bin/aufgaben_erinnerung.php
 *
 * Sendet E-Mails an Verantwortliche, deren Aufgaben heute fällig sind.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/channels/mail.php';
require_once __DIR__ . '/../src/logger.php';

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("
        SELECT a.id, a.titel, a.faellig_am, u.name, u.email
        FROM aufgaben a
        JOIN users u ON a.verantwortlich_user_id = u.id
        WHERE a.faellig_am = CURDATE()
          AND a.status != 'erledigt'
          AND a.erinnerung_gesendet = 0
          AND u.active = 1
    ");

    $aufgaben = $stmt->fetchAll();
    $sent = 0;
    $failed = 0;

    foreach ($aufgaben as $aufgabe) {
        $faelligFormatted = date('d.m.Y', strtotime($aufgabe['faellig_am']));

        try {
            $result = sendAufgabeErinnerung(
                $aufgabe['email'],
                $aufgabe['name'],
                $aufgabe['titel'],
                $faelligFormatted
            );

            if ($result) {
                $update = $pdo->prepare('UPDATE aufgaben SET erinnerung_gesendet = 1 WHERE id = :id');
                $update->execute(['id' => $aufgabe['id']]);
                $sent++;
                echo "✓ Erinnerung gesendet: {$aufgabe['titel']} → {$aufgabe['email']}\n";
            } else {
                $failed++;
                logError("Aufgaben-Erinnerung fehlgeschlagen für Aufgabe #{$aufgabe['id']}: Mail nicht gesendet");
                echo "✗ Fehlgeschlagen: {$aufgabe['titel']} → {$aufgabe['email']}\n";
            }
        } catch (Throwable $e) {
            $failed++;
            logError("Aufgaben-Erinnerung Exception für Aufgabe #{$aufgabe['id']}: " . $e->getMessage());
            echo "✗ Exception: {$aufgabe['titel']} → {$e->getMessage()}\n";
        }
    }

    echo "\nFertig. Gesendet: {$sent}, Fehlgeschlagen: {$failed}\n";

} catch (PDOException $e) {
    logError('Aufgaben-Erinnerung DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}
