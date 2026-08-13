#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Überblick „Offene ToDos Sponsoring" per Mail
 *
 * Läuft täglich über .github/workflows/taegliche_erinnerung.yml (Strato hat kein
 * crontab per SSH). Empfänger sind alle aktiven Nutzer mit Rolle admin oder orga.
 *
 * Verhältnis zu bin/aufgaben_erinnerung.php: Das dortige Skript verschickt
 * Einzelmails für HEUTE fällige Aufgaben und führt dafür ein eigenes
 * erinnerung_gesendet-Flag. Dieser Digest deckt ÜBERFÄLLIGE Aufgaben ab
 * (faellig_am < CURDATE()) — die Mengen sind disjunkt, niemand bekommt dieselbe
 * Aufgabe zweimal gemeldet.
 *
 * Bewusst ohne Idempotenz-Flag: ein Tagesüberblick soll täglich kommen, solange
 * etwas offen ist. Gibt es nichts zu tun, wird gar nichts verschickt.
 *
 * Spec: intern/offene-todos-spec.md
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/offene_todos_digest.php"
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/offene_todos.php';
require_once __DIR__ . '/../src/channels/mail.php';
require_once __DIR__ . '/../src/logger.php';

// --dry-run: alles berechnen und ausgeben, aber nichts verschicken. Dient dazu,
// den Digest gefahrlos gegen echte Daten prüfen zu können, ohne dem gesamten
// Orga-Team eine Testmail zu schicken.
$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    $pdo = getDbConnection();
    $todos = offeneTodosAlle($pdo);

    if ($todos['gesamt'] === 0) {
        echo "Keine offenen ToDos — keine Mail verschickt.\n";
        exit(0);
    }

    // Zeilen für die Mail aufbereiten. Bewusst schlicht: die Mail ist der Anstoß,
    // gearbeitet wird im Cockpit. Lange Gruppen werden wie im Cockpit gedeckelt —
    // eine Tagesmail mit 28 Zeilen liest niemand zweimal.
    $gruppen = [];
    $deckeln = static function (array $zeilen): array {
        $rest = count($zeilen) - TODO_LISTE_MAX;
        if ($rest <= 0) {
            return $zeilen;
        }
        $gekuerzt = array_slice($zeilen, 0, TODO_LISTE_MAX);
        $gekuerzt[] = '… und ' . $rest . ' weitere im Cockpit';
        return $gekuerzt;
    };

    $zeilen = [];
    foreach ($todos['bestaetigung'] as $t) {
        $zeilen[] = $t['firma'] . ' — seit ' . (int) $t['tage'] . ' Tagen zugesagt, Bestätigung noch nicht raus';
    }
    $gruppen[] = ['titel' => 'Bestätigung offen', 'zeilen' => $deckeln($zeilen)];

    $zeilen = [];
    foreach ($todos['wiedervorlagen'] as $t) {
        $tage = (int) $t['tage'];
        $zeilen[] = $t['firma'] . ' — ' . ($tage <= 0 ? 'heute fällig' : 'seit ' . $tage . ' Tagen überfällig')
            . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '')
            . (todoNotizStand($t['notizen']) !== '' ? ' | ' . todoNotizStand($t['notizen']) : '');
    }
    $gruppen[] = ['titel' => 'Wiedervorlage fällig', 'zeilen' => $deckeln($zeilen)];

    $zeilen = [];
    foreach ($todos['versand_fehler'] as $t) {
        $zeilen[] = $t['firma'] . ' — ' . ($t['fehler'] !== '' ? $t['fehler'] : 'Versand fehlgeschlagen');
    }
    $gruppen[] = ['titel' => 'Versand-Queue: Fehler', 'zeilen' => $deckeln($zeilen)];

    $zeilen = [];
    foreach ($todos['nie_angeschrieben'] as $t) {
        $zeilen[] = $t['firma'] . ' — liegt seit ' . (int) $t['tage'] . ' Tagen unangeschrieben'
            . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '');
    }
    $gruppen[] = ['titel' => 'Noch nie angeschrieben', 'zeilen' => $deckeln($zeilen)];

    $zeilen = [];
    foreach ($todos['ohne_reaktion'] as $t) {
        $zeilen[] = $t['firma'] . ' — seit ' . (int) $t['tage'] . ' Tagen keine Antwort'
            . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '')
            . (todoNotizStand($t['notizen']) !== '' ? ' | ' . todoNotizStand($t['notizen']) : '');
    }
    $gruppen[] = ['titel' => 'Angeschrieben ohne Reaktion', 'zeilen' => $deckeln($zeilen)];

    $empfaenger = $pdo->query("
        SELECT name, email FROM users
        WHERE active = 1 AND role IN ('admin','orga') AND NULLIF(TRIM(email),'') IS NOT NULL
        ORDER BY name
    ")->fetchAll();

    if ($empfaenger === []) {
        echo "Keine aktiven Orga-/Admin-Empfänger gefunden — nichts verschickt.\n";
        exit(0);
    }

    if ($dryRun) {
        echo "TROCKENLAUF — es wird nichts verschickt.\n\n";
        echo "Offene ToDos gesamt: {$todos['gesamt']}\n";
        foreach ($gruppen as $g) {
            if (empty($g['zeilen'])) {
                continue;
            }
            echo "\n" . $g['titel'] . ' (' . count($g['zeilen']) . ")\n";
            foreach ($g['zeilen'] as $z) {
                echo '  • ' . $z . "\n";
            }
        }
        echo "\nEmpfänger wären (" . count($empfaenger) . '): '
            . implode(', ', array_column($empfaenger, 'email')) . "\n";
        exit(0);
    }

    $sent = 0;
    $failed = 0;
    foreach ($empfaenger as $e) {
        try {
            if (sendOffeneTodosDigest((string) $e['email'], (string) $e['name'], $todos['gesamt'], $gruppen)) {
                $sent++;
                echo "✓ Digest gesendet an {$e['email']}\n";
            } else {
                $failed++;
                logError("Offene-ToDos-Digest: Mail an {$e['email']} nicht gesendet");
                echo "✗ Fehlgeschlagen: {$e['email']}\n";
            }
        } catch (Throwable $ex) {
            $failed++;
            logError("Offene-ToDos-Digest Exception für {$e['email']}: " . $ex->getMessage());
            echo "✗ Exception: {$e['email']} — {$ex->getMessage()}\n";
        }
    }

    echo "\nFertig. {$todos['gesamt']} offene ToDos. Gesendet: {$sent}, Fehlgeschlagen: {$failed}\n";
} catch (PDOException $e) {
    logError('Offene-ToDos-Digest DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}
