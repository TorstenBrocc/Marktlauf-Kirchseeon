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
 * Einträge ohne Zuständigen gehen NUR an TODO_HERRENLOS_EMPFAENGER_EMAIL (TT, 2026-08-18;
 * vorher: alle Admins) — sichtbar als eigener Abschnitt, damit sie zugeordnet werden,
 * statt still liegenzubleiben. info@ liest jede Mail per BCC mit (mailBccAddress()).
 *
 * FREQUENZ: Im Modus `auto` prüft das Skript die Orga-Einstellungen `reminder_versandtage`
 * (Wochentagsliste) und `reminder_pause_bis` (Urlaubs-Pause) via reminderVersandtagHeute()
 * und schweigt an Nicht-Versandtagen. Manuelle Aufrufe (--modus=voll|neu, --dry-run)
 * laufen immer.
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
require_once __DIR__ . '/../src/sponsor_status.php';  // sponsorStatusLabel() für die Status-Spalte
require_once __DIR__ . '/../src/social_anlaesse.php'; // Themen-Labels für den Social-Fahrplan-Abschnitt

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
$modusWarAuto = ($modus === 'auto');
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

    // Frequenz-Drossel nur für den automatischen Lauf — manuelle Aufrufe bleiben ungebremst.
    if ($modusWarAuto && !reminderVersandtagHeute($pdo)) {
        echo "Heute kein Versandtag (Versandtage/Pause in den Orga-Einstellungen) — keine Mail verschickt.\n";
        exit(0);
    }

    $todos = offeneTodosAlle($pdo);

    // Gruppen in Bearbeitungsreihenfolge; je Gruppe der Titel und wie eine Zeile klingt.
    // Kurzform der Notiz: in der Praxis war die 140-Zeichen-Fassung der Hauptgrund,
    // warum die Mail unlesbar wurde. Auf der Seite bleibt sie länger.
    $notizKurz = static function (?string $notizen): string {
        $n = todoNotizStand($notizen);
        return mb_strlen($n) > 90 ? mb_substr($n, 0, 89) . '…' : $n;
    };
    // „Status / Frist" wie auf der Seite: Priorität, Status-Label, Frist.
    $statusText = static function (array $t, string $frist, bool $mitStatus = true): string {
        $teile = [];
        if ((int) ($t['prioritaet'] ?? 0) === 1) {
            $teile[] = 'Prio 1';
        }
        if ($mitStatus && isset($t['status'])) {
            $teile[] = sponsorStatusLabel((string) $t['status']);
        }
        $teile[] = $frist;
        return implode(' · ', array_filter($teile, static fn ($x) => trim((string) $x) !== ''));
    };
    $kontaktWert = static fn (array $t): string =>
        trim((string) ($t['telefon'] ?? '')) . '|' . trim((string) ($t['email'] ?? ''));

    // Titel und Beschreibung kommen aus todoGruppenMeta() (src/offene_todos.php) — dieselbe
    // Quelle wie die Seite. Hier stehen nur noch Spaltenköpfe und der Zeilenbau, denn beides
    // ist medienabhängig: die Seite hat eine Erledigt-Spalte mit Formular, die Mail nicht.
    $meta = todoGruppenMeta();
    // Social-Fahrplan ist kein Sponsoring-Thema — Meta hier lokal, nicht in offene_todos.php
    $meta['social_fahrplan'] = [
        'titel' => 'Social-Fahrplan — fällige Themen',
        'sub'   => 'Posts erstellen, prüfen und senden: Dashboard → Social-Fahrplan.',
    ];
    $vier = ['Firma', 'Info', 'Status / Frist', 'Kontakt'];
    // Frist-Text für Aufgaben: überfällig, heute, oder Vorausschau.
    $fristText = static function (?int $tage): string {
        if ($tage === null) {
            return 'ohne Frist';
        }
        if ($tage > 0) {
            return $tage . ' Tage überfällig';
        }
        if ($tage === 0) {
            return 'heute fällig';
        }
        return $tage === -1 ? 'morgen fällig' : 'in ' . abs($tage) . ' Tagen fällig';
    };
    $gruppenDef = [
        'bestaetigung' => [$vier,
            static function (array $t) use ($notizKurz, $statusText, $kontaktWert): array {
                $tage = (int) $t['tage'];
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $notizKurz($t['notizen'] ?? null), 'k' => 'info'],
                        ['t' => $statusText($t, $tage <= 0 ? 'heute zugesagt' : 'seit ' . $tage . ' Tagen', false), 'k' => 'status'],
                        ['t' => $kontaktWert($t), 'k' => 'kontakt']];
            }, 'zustaendig_user_id', false],
        'bedingungen' => [$vier,
            static function (array $t) use ($notizKurz, $statusText, $kontaktWert): array {
                $tage = (int) $t['tage'];
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $notizKurz($t['notizen'] ?? null), 'k' => 'info'],
                        ['t' => $statusText($t, $tage <= 0 ? 'seit heute' : 'seit ' . $tage . ' Tagen'), 'k' => 'status'],
                        ['t' => $kontaktWert($t), 'k' => 'kontakt']];
            }, 'zustaendig_user_id', false],
        'wiedervorlagen' => [$vier,
            static function (array $t) use ($notizKurz, $statusText, $kontaktWert): array {
                $tage = (int) $t['tage'];
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $notizKurz($t['notizen'] ?? null), 'k' => 'info'],
                        ['t' => $statusText($t, $tage <= 0 ? 'heute fällig' : $tage . ' Tage überfällig'), 'k' => 'status'],
                        ['t' => $kontaktWert($t), 'k' => 'kontakt']];
            }, 'zustaendig_user_id', false],
        'versand_fehler' => [['Firma', 'Fehler'],
            static function (array $t): array {
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $t['fehler'] !== '' ? (string) $t['fehler'] : 'Versand fehlgeschlagen', 'k' => 'info']];
            }, 'zustaendig_user_id', true],
        'nie_angeschrieben' => [$vier,
            static function (array $t) use ($notizKurz, $statusText, $kontaktWert): array {
                $tage = (int) $t['tage'];
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $notizKurz($t['notizen'] ?? null), 'k' => 'info'],
                        ['t' => $statusText($t, $tage <= 0 ? 'heute angelegt' : 'liegt ' . $tage . ' Tage', false), 'k' => 'status'],
                        ['t' => $kontaktWert($t), 'k' => 'kontakt']];
            }, 'zustaendig_user_id', false],
        // Aufgaben am Sponsor (TT, 2026-08-13): in dieselbe tägliche Mail, mit Vorausschau
        // auf TODO_FRIST_VORSCHAU_TAGE Tage. Zwei Besonderheiten:
        //   - Routing über die AUFGABE (verantwortlich_user_id), nicht über den Sponsor —
        //     wer die Aufgabe hat, bekommt sie, unabhängig davon, wer den Sponsor betreut.
        //   - 'immer' => wird in beiden Modi gezeigt. Sonst hinge die Sichtbarkeit einer Frist
        //     am Wochentag, und genau daran ist ursprünglich eine Wiedervorlage vorbeigelaufen.
        //   - Nur Aufgaben MIT Frist innerhalb des Fensters; undatierte bleiben seitenintern.
        'sponsor_aufgaben' => [
            ['Firma', 'Aufgabe', 'Frist'],
            static function (array $t) use ($fristText): array {
                $tage = $t['tage_ueberfaellig'] === null ? null : (int) $t['tage_ueberfaellig'];
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => (string) $t['titel'], 'k' => 'plain'],
                        ['t' => $fristText($tage), 'k' => 'status']];
            },
            'verantwortlich_user_id',
            true,
        ],
        'ohne_reaktion' => [$vier,
            static function (array $t) use ($notizKurz, $statusText, $kontaktWert): array {
                return [['t' => (string) $t['firma'], 'k' => 'firma'],
                        ['t' => $notizKurz($t['notizen'] ?? null), 'k' => 'info'],
                        ['t' => $statusText($t, (int) $t['tage'] . ' Tage ohne Antwort', false), 'k' => 'status'],
                        ['t' => $kontaktWert($t), 'k' => 'kontakt']];
            }, 'zustaendig_user_id', false],
        // Social-Fahrplan (Schnitt 4, social-fahrplan-redesign-spec.md): fällige Themen mit
        // Vorausschau — 'immer', damit Fristen nicht am Wochentag hängen (wie sponsor_aufgaben).
        'social_fahrplan' => [
            ['Thema', 'Stand', 'Frist'],
            static function (array $t) use ($fristText): array {
                $def   = socialAnlaesse()[$t['anlass_key']] ?? null;
                $stand = 'kein Entwurf';
                if (($t['post_status'] ?? '') === 'approved') {
                    $stand = 'freigegeben — senden';
                } elseif (trim((string) ($t['post_social'] ?? '')) !== '') {
                    $stand = 'Entwurf';
                }
                return [['t' => $def ? $def['ui'] : (string) $t['anlass_key'], 'k' => 'firma'],
                        ['t' => $stand, 'k' => 'plain'],
                        ['t' => $fristText((int) $t['tage_ueberfaellig']), 'k' => 'status']];
            },
            'zustaendig_user_id',
            true,
        ],
    ];

    // Social-Fahrplan-Zeilen in denselben Fluss geben (offene, terminierte Einträge
    // im Vorschaufenster; gesendete Einträge sind bereits 'erledigt' und fallen raus)
    $todos['social_fahrplan'] = $pdo->query("
        SELECT f.anlass_key, f.zustaendig_user_id,
               DATEDIFF(CURDATE(), f.zieldatum) AS tage_ueberfaellig,
               p.status AS post_status, p.llm_text_social AS post_social
          FROM social_fahrplan f
     LEFT JOIN post_race_contents p ON p.id = f.post_id
         WHERE f.status = 'offen' AND f.zieldatum IS NOT NULL
           AND DATEDIFF(CURDATE(), f.zieldatum) >= -" . TODO_FRIST_VORSCHAU_TAGE . "
      ORDER BY f.zieldatum
    ")->fetchAll();

    // Zeilen nach Zuständigem einsortieren. 0 = ohne Zuständigen.
    $proUser = [];
    foreach ($gruppenDef as $key => [$kopf, $bauer, $schluessel, $immer]) {
        foreach (($todos[$key] ?? []) as $zeile) {
            // Aufgaben ohne Frist gehören nicht in die Mail — sie sind zu keinem Zeitpunkt
            // fällig und würden jeden Tag gleich dastehen. Mit Frist gilt das Vorschaufenster.
            if ($key === 'sponsor_aufgaben') {
                if (empty($zeile['faellig_am'])) {
                    continue;
                }
                if ((int) $zeile['tage_ueberfaellig'] < -TODO_FRIST_VORSCHAU_TAGE) {
                    continue;
                }
            }
            if (!$immer && $modus === 'neu' && !todoIstNeu($key, $zeile)) {
                continue;
            }
            $uid = (int) ($zeile[$schluessel] ?? 0);
            $proUser[$uid][$key][] = ['zellen' => $bauer($zeile)];
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

    // Einträge ohne Zuständigen gehen als eigener Abschnitt an genau eine Person
    // (TODO_HERRENLOS_EMPFAENGER_EMAIL) — damit sie zugeordnet werden statt still
    // liegenzubleiben, aber ohne den ganzen Admin-Kreis täglich zu fluten.
    $herrenlos = $proUser[0] ?? [];
    unset($proUser[0]);

    $deckeln = static function (array $zeilen): array {
        $rest = count($zeilen) - TODO_LISTE_MAX;
        if ($rest <= 0) {
            return $zeilen;
        }
        $gekuerzt = array_slice($zeilen, 0, TODO_LISTE_MAX);
        // Über alle Spalten laufende Zeile — kein Eintrag, nur ein Verweis.
        $gekuerzt[] = ['mehr' => '… und ' . $rest . ' weitere auf der Seite'];
        return $gekuerzt;
    };

    $sent = 0;
    $failed = 0;
    $uebersprungen = 0;

    foreach ($empfaenger as $e) {
        $uid = (int) $e['id'];
        $meine = $proUser[$uid] ?? [];
        $bekommtHerrenlose = (strcasecmp(trim((string) $e['email']), TODO_HERRENLOS_EMPFAENGER_EMAIL) === 0);

        // Gruppen in definierter Reihenfolge zusammenbauen
        $gruppen = [];
        $anzahl = 0;
        foreach ($gruppenDef as $key => [$kopf, $_, $__, $___]) {
            if (!empty($meine[$key])) {
                $anzahl += count($meine[$key]);
                $gruppen[] = [
                    'titel'      => $meta[$key]['titel'],
                    'sub'        => $meta[$key]['sub'],
                    'ist_fehler' => ($meta[$key]['ton'] ?? '') === 'fehler',
                    'kopf'       => $kopf,
                    'zeilen'     => $deckeln($meine[$key]),
                ];
            }
        }
        if ($bekommtHerrenlose && $herrenlos !== []) {
            foreach ($gruppenDef as $key => [$kopf, $_, $__, $___]) {
                if (!empty($herrenlos[$key])) {
                    $anzahl += count($herrenlos[$key]);
                    $gruppen[] = [
                        'titel'      => 'Ohne Zuständigen — bitte zuordnen: ' . $meta[$key]['titel'],
                        'sub'        => $meta[$key]['sub'],
                        'ist_fehler' => ($meta[$key]['ton'] ?? '') === 'fehler',
                        'kopf'       => $kopf,
                        'zeilen'     => $deckeln($herrenlos[$key]),
                    ];
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
                    if (isset($z['mehr'])) {
                        echo '    ' . $z['mehr'] . "\n";
                        continue;
                    }
                    $werte = [];
                    foreach ($z['zellen'] as $zelle) {
                        $t = trim(str_replace('|', ' ', $zelle['t']));
                        if ($t !== '') {
                            $werte[] = $t;
                        }
                    }
                    echo '    • ' . implode(' · ', $werte) . "\n";
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
