#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Erinnerung „Offene ToDos Sponsoring" — an die jeweils Zuständigen.
 *
 * Läuft über .github/workflows/taegliche_erinnerung.yml (Strato hat kein crontab per SSH).
 *
 * RHYTHMUS (TT, 2026-08-13): wöchentlich der volle Überblick, dazwischen nur, wenn etwas
 * NEU dazugekommen ist. Umgesetzt über --modus:
 *   voll  Alles Offene. Läuft freitags und bei jedem manuellen Aufruf.
 *   neu   Nur, was HEUTE dazugekommen ist. Ist nichts neu, geht keine Mail raus.
 *   auto  Freitag = voll, sonst neu. Das nutzt der Workflow.
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
    // 5 = Freitag (TT, 2026-08-13): der volle Überblick kommt zum Wochenausklang,
    // wenn Zeit fürs Nacharbeiten ist. An den anderen Tagen nur Neues.
    $modus = ((int) date('N') === TODO_WOCHENTAG_VOLL) ? 'voll' : 'neu';
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
    // Kurzform der Notiz für die Mail: in der Praxis war die 140-Zeichen-Fassung der
    // Hauptgrund, warum die Mail unlesbar wurde. Auf der Seite bleibt sie länger.
    $notizKurz = static function (?string $notizen): string {
        $n = todoNotizStand($notizen);
        return mb_strlen($n) > 90 ? mb_substr($n, 0, 89) . '…' : $n;
    };

    // Je Gruppe: Titel, ob Fehlerzustand, und wie eine Zeile in Felder zerlegt wird.
    // Struktur statt fertiger Strings, damit Mail-HTML echte Spalten bauen kann.
    $gruppenDef = [
        'bestaetigung' => ['Bestätigung offen', false, static function (array $t) use ($notizKurz): array {
            $tage = (int) $t['tage'];
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => $tage <= 0 ? 'heute zugesagt' : 'zugesagt vor ' . $tage . ' Tagen',
                'telefon' => (string) ($t['telefon'] ?? ''),
                'notiz'   => $notizKurz($t['notizen'] ?? null),
            ];
        }],
        'bedingungen' => ['Bedingungen nicht bestätigt', false, static function (array $t) use ($notizKurz): array {
            $tage = (int) $t['tage'];
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => $tage <= 0 ? 'seit heute offen' : 'seit ' . $tage . ' Tagen offen',
                'telefon' => (string) ($t['telefon'] ?? ''),
                'notiz'   => $notizKurz($t['notizen'] ?? null),
            ];
        }],
        'wiedervorlagen' => ['Wiedervorlage fällig', false, static function (array $t) use ($notizKurz): array {
            $tage = (int) $t['tage'];
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => $tage <= 0 ? 'heute fällig' : $tage . ' Tage überfällig',
                'telefon' => (string) ($t['telefon'] ?? ''),
                'notiz'   => $notizKurz($t['notizen'] ?? null),
            ];
        }],
        'versand_fehler' => ['Versand-Queue: Fehler', true, static function (array $t): array {
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => $t['fehler'] !== '' ? (string) $t['fehler'] : 'Versand fehlgeschlagen',
                'telefon' => '',
                'notiz'   => '',
            ];
        }],
        'nie_angeschrieben' => ['Noch nie angeschrieben', false, static function (array $t) use ($notizKurz): array {
            $tage = (int) $t['tage'];
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => $tage <= 0 ? 'heute angelegt' : 'liegt ' . $tage . ' Tage',
                'telefon' => (string) ($t['telefon'] ?? ''),
                'notiz'   => $notizKurz($t['notizen'] ?? null),
            ];
        }],
        'ohne_reaktion' => ['Angeschrieben ohne Reaktion', false, static function (array $t) use ($notizKurz): array {
            return [
                'firma'   => (string) $t['firma'],
                'frist'   => (int) $t['tage'] . ' Tage ohne Antwort',
                'telefon' => (string) ($t['telefon'] ?? ''),
                'notiz'   => $notizKurz($t['notizen'] ?? null),
            ];
        }],
    ];

    // Zeilen nach Zuständigem einsortieren. 0 = ohne Zuständigen.
    $proUser = [];
    foreach ($gruppenDef as $key => [$titel, $istFehler, $bauer]) {
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
        // Als eigene Zeile markiert, damit die Mail sie ohne Spalten rendert.
        $gekuerzt[] = ['firma' => '… und ' . $rest . ' weitere auf der Seite', 'mehr' => true];
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
        foreach ($gruppenDef as $key => [$titel, $istFehler, $_]) {
            if (!empty($meine[$key])) {
                $anzahl += count($meine[$key]);
                $gruppen[] = ['titel' => $titel, 'ist_fehler' => $istFehler,
                              'zeilen' => $deckeln($meine[$key])];
            }
        }
        if ($istAdmin && $herrenlos !== []) {
            foreach ($gruppenDef as $key => [$titel, $istFehler, $_]) {
                if (!empty($herrenlos[$key])) {
                    $anzahl += count($herrenlos[$key]);
                    $gruppen[] = ['titel' => 'Ohne Zuständigen — bitte zuordnen: ' . $titel,
                                  'ist_fehler' => $istFehler,
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
                    $teile = array_filter([
                        $z['firma'] ?? '',
                        trim((string) ($z['frist'] ?? '')),
                        trim((string) ($z['telefon'] ?? '')) !== '' ? 'Tel. ' . $z['telefon'] : '',
                    ], static fn ($t) => $t !== '');
                    echo '    • ' . implode(' · ', $teile) . "\n";
                    if (trim((string) ($z['notiz'] ?? '')) !== '') {
                        echo '        ' . $z['notiz'] . "\n";
                    }
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
