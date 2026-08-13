#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Erinnerung „Offene ToDos Sponsoring" — an die jeweils Zuständigen.
 *
 * Läuft über .github/workflows/taegliche_erinnerung.yml (Strato hat kein crontab per SSH).
 *
 * RHYTHMUS (TT, 2026-08-13): wöchentlich der volle Überblick, dazwischen nur, wenn etwas
 * NEU dazugekommen ist. Umgesetzt über --modus:
 *   voll  Alles Offene. Läuft montags und bei jedem manuellen Aufruf.
 *   neu   Nur, was HEUTE dazugekommen ist. Ist nichts neu, geht keine Mail raus.
 *   auto  Montag = voll, sonst neu. Das nutzt der Workflow.
 *
 * „Neu" wird aus den ohnehin gelesenen Daten abgeleitet, nicht aus einer
 * Benachrichtigungs-Tabelle: heute fällig geworden, heute die 21-Tage-Schwelle erreicht,
 * heute angelegt, heute in den Status gewechselt. Das spart eine Zustandstabelle, die
 * sonst gepflegt und aufgeräumt werden müsste.
 *
 * ROUTING: Jeder bekommt nur, wofür er zuständig ist (sponsors.zustaendig_user_id).
 * Einträge ohne Zuständigen gehen an alle Admins — sichtbar als eigener Abschnitt, damit
 * sie zugeordnet werden, statt still liegenzubleiben.
 *
 * Verhältnis zu bin/aufgaben_erinnerung.php: Das dortige Skript verschickt Einzelmails für
 * HEUTE fällige Orga-Aufgaben und hat dafür ein eigenes Flag. Hier geht es ausschließlich
 * um Sponsoring — die Mengen überschneiden sich nicht.
 *
 * Optionen:
 *   --modus=voll|neu|auto   (Default: voll)
 *   --dry-run               nichts verschicken, alles ausgeben
 *   --nur-an=<mail>         nur an diese Adresse (Sichtprobe vor dem Aufmachen des Verteilers)
 *
 * Spec: intern/offene-todos-spec.md
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/offene_todos_digest.php --modus=auto"
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

$argumente = $argv ?? [];
$dryRun = in_array('--dry-run', $argumente, true);

$nurAn = '';
$modus = 'voll';
foreach ($argumente as $arg) {
    $arg = (string) $arg;
    if (str_starts_with($arg, '--nur-an=')) {
        $nurAn = trim(substr($arg, 9));
    } elseif (str_starts_with($arg, '--modus=')) {
        $modus = trim(substr($arg, 8));
    }
}
if (!in_array($modus, ['voll', 'neu', 'auto'], true)) {
    exit("ABBRUCH: --modus muss voll, neu oder auto sein.\n");
}
if ($modus === 'auto') {
    // 1 = Montag. Wochenstart = voller Überblick, sonst nur Neues.
    $modus = ((int) date('N') === 1) ? 'voll' : 'neu';
}

/**
 * Ist dieser Eintrag HEUTE dazugekommen?
 *
 * Die Gruppen liefern jeweils ein `tage`-Feld, das die passende Basis zählt
 * (Fälligkeit, Anlage, Statuswechsel) — daraus lässt sich „neu" ohne Zusatztabelle
 * ableiten. Versandfehler gelten immer als neu: sie sind selten, dringend und haben
 * keine belastbare Zeitbasis.
 */
function todoIstNeu(string $gruppe, array $zeile): bool
{
    if ($gruppe === 'versand_fehler') {
        return true;
    }
    $tage = (int) ($zeile['tage'] ?? -1);
    if ($gruppe === 'ohne_reaktion') {
        return $tage === TODO_KEINE_REAKTION_TAGE; // heute die Schwelle gerissen
    }
    return $tage === 0; // heute fällig / heute angelegt / heute gewechselt
}

try {
    $pdo = getDbConnection();
    $todos = offeneTodosAlle($pdo);

    // Gruppen in Bearbeitungsreihenfolge; je Gruppe der Titel und wie eine Zeile klingt.
    $gruppenDef = [
        'bestaetigung' => ['Bestätigung offen', static function (array $t): string {
            return $t['firma'] . ' — seit ' . (int) $t['tage'] . ' Tagen zugesagt, Bestätigung noch nicht raus';
        }],
        'bedingungen' => ['Bedingungen nicht bestätigt', static function (array $t): string {
            return $t['firma'] . ' — Bedingungen seit ' . (int) $t['tage'] . ' Tagen nicht gegengezeichnet';
        }],
        'wiedervorlagen' => ['Wiedervorlage fällig', static function (array $t): string {
            $tage = (int) $t['tage'];
            return $t['firma'] . ' — ' . ($tage <= 0 ? 'heute fällig' : 'seit ' . $tage . ' Tagen überfällig')
                . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '')
                . (todoNotizStand($t['notizen']) !== '' ? ' | ' . todoNotizStand($t['notizen']) : '');
        }],
        'versand_fehler' => ['Versand-Queue: Fehler', static function (array $t): string {
            return $t['firma'] . ' — ' . ($t['fehler'] !== '' ? $t['fehler'] : 'Versand fehlgeschlagen');
        }],
        'nie_angeschrieben' => ['Noch nie angeschrieben', static function (array $t): string {
            return $t['firma'] . ' — liegt seit ' . (int) $t['tage'] . ' Tagen unangeschrieben'
                . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '');
        }],
        'ohne_reaktion' => ['Angeschrieben ohne Reaktion', static function (array $t): string {
            return $t['firma'] . ' — seit ' . (int) $t['tage'] . ' Tagen keine Antwort'
                . (trim((string) $t['telefon']) !== '' ? ' · Tel. ' . $t['telefon'] : '')
                . (todoNotizStand($t['notizen']) !== '' ? ' | ' . todoNotizStand($t['notizen']) : '');
        }],
    ];

    // Zeilen nach Zuständigem einsortieren. 0 = ohne Zuständigen.
    $proUser = [];
    foreach ($gruppenDef as $key => [$titel, $bauer]) {
        foreach (($todos[$key] ?? []) as $zeile) {
            if ($modus === 'neu' && !todoIstNeu($key, $zeile)) {
                continue;
            }
            $uid = (int) ($zeile['zustaendig_user_id'] ?? 0);
            $proUser[$uid][$key][] = $bauer($zeile);
        }
    }

    if ($proUser === []) {
        echo $modus === 'neu'
            ? "Heute nichts Neues — keine Mail verschickt.\n"
            : "Keine offenen ToDos — keine Mail verschickt.\n";
        exit(0);
    }

    $empfaenger = $pdo->query("
        SELECT id, name, email, role FROM users
        WHERE active = 1 AND role IN ('admin','orga') AND NULLIF(TRIM(email),'') IS NOT NULL
        ORDER BY name
    ")->fetchAll();

    // Einträge ohne Zuständigen gehen an alle Admins — als eigener Abschnitt, damit sie
    // zugeordnet werden statt still liegenzubleiben.
    $herrenlos = $proUser[0] ?? [];
    unset($proUser[0]);

    $deckeln = static function (array $zeilen): array {
        $rest = count($zeilen) - TODO_LISTE_MAX;
        if ($rest <= 0) {
            return $zeilen;
        }
        $gekuerzt = array_slice($zeilen, 0, TODO_LISTE_MAX);
        $gekuerzt[] = '… und ' . $rest . ' weitere im Cockpit';
        return $gekuerzt;
    };

    $sent = 0;
    $failed = 0;
    $uebersprungen = 0;

    foreach ($empfaenger as $e) {
        $uid = (int) $e['id'];
        $meine = $proUser[$uid] ?? [];
        $istAdmin = ($e['role'] === 'admin');

        // Gruppen in definierter Reihenfolge zusammenbauen
        $gruppen = [];
        $anzahl = 0;
        foreach ($gruppenDef as $key => [$titel, $_]) {
            if (!empty($meine[$key])) {
                $anzahl += count($meine[$key]);
                $gruppen[] = ['titel' => $titel, 'zeilen' => $deckeln($meine[$key])];
            }
        }
        if ($istAdmin && $herrenlos !== []) {
            foreach ($gruppenDef as $key => [$titel, $_]) {
                if (!empty($herrenlos[$key])) {
                    $anzahl += count($herrenlos[$key]);
                    $gruppen[] = ['titel' => 'OHNE ZUSTÄNDIGEN — bitte zuordnen: ' . $titel,
                                  'zeilen' => $deckeln($herrenlos[$key])];
                }
            }
        }

        if ($gruppen === []) {
            $uebersprungen++;
            continue;
        }
        if ($nurAn !== '' && strcasecmp(trim((string) $e['email']), $nurAn) !== 0) {
            $uebersprungen++;
            continue;
        }

        if ($dryRun) {
            echo "\n=== {$e['name']} <{$e['email']}> — {$anzahl} ToDos (Modus: {$modus})\n";
            foreach ($gruppen as $g) {
                echo '  ' . $g['titel'] . ' (' . count($g['zeilen']) . ")\n";
                foreach ($g['zeilen'] as $z) {
                    echo '    • ' . $z . "\n";
                }
            }
            $sent++;
            continue;
        }

        try {
            if (sendOffeneTodosDigest((string) $e['email'], (string) $e['name'], $anzahl, $gruppen, $modus)) {
                $sent++;
                echo "✓ {$e['email']} ({$anzahl} ToDos)\n";
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

    if ($dryRun) {
        echo "\nTROCKENLAUF (Modus: {$modus}) — nichts verschickt. Empfänger mit Inhalt: {$sent}, ohne: {$uebersprungen}\n";
        exit(0);
    }
    echo "\nFertig (Modus: {$modus}). Gesendet: {$sent}, Fehlgeschlagen: {$failed}, ohne eigene ToDos: {$uebersprungen}\n";
} catch (PDOException $e) {
    logError('Offene-ToDos-Digest DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}
