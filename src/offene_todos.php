<?php
/**
 * Offene ToDos Sponsoring — die eine Wahrheit darüber, was gerade Aufmerksamkeit braucht.
 *
 * Genutzt von ZWEI Verbrauchern, die deshalb nicht auseinanderdriften können:
 *   - orga/offene_todos.php       → die Seite direkt unter dem Cockpit
 *   - bin/offene_todos_digest.php → Erinnerungsmail
 *
 * BEWUSST NUR SPONSORING (TT, 2026-08-13). Helfer, Plakate und die ToDos aus dem Vault
 * sind eigene Domänen; ein domänenübergreifendes ToDo-System wäre jetzt zu viel. Die
 * Seite heißt deshalb „Offene ToDos Sponsoring" und nicht bloß „Offene ToDos" — weitere
 * Domänen können demselben Muster folgen, statt hier hineinzuwachsen.
 *
 * Spec: intern/offene-todos-spec.md
 * Sprachregelung (TT): „offene ToDos", nicht „offene Stränge".
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Ab wann gilt ein Anschreiben ohne Reaktion als liegengeblieben.
 * Bewusst eine Konstante und keine DB-Einstellung: ein Wert, den niemand laufend
 * ändert, gehört nicht in ein Konfigurationsformular. Hier ist die eine Stelle.
 */
const TODO_KEINE_REAKTION_TAGE = 21;

/**
 * Wie viele Zeilen je Gruppe angezeigt bzw. gemailt werden.
 *
 * Nötig geworden durch die Realität: Bei der ersten Prüfung gegen die Live-Daten
 * standen allein 28 unbeantwortete Anschreiben in der Liste. Ungedeckelt wäre die
 * Seite unlesbar und die Mail reines Rauschen. Die Gesamtzahl bleibt davon
 * unberührt — gedeckelt wird nur die Darstellung, nicht die Zählung.
 */
const TODO_LISTE_MAX = 8;

/** Wie viele Zeichen der letzten Notiz in der Liste erscheinen. */
const TODO_NOTIZ_LAENGE = 140;

/**
 * Wie viele Tage die Erinnerung bei Aufgaben-Fristen vorausschaut (TT, 2026-08-13:
 * „alle fristen für die nächsten 2 Tage"). Gilt nur für Aufgaben mit Frist — undatierte
 * Aufgaben bleiben nachrichtlich und tauchen in der Mail nicht auf.
 */
const TODO_FRIST_VORSCHAU_TAGE = 2;

/**
 * Wochentag für den vollen Überblick (ISO: 1 = Montag … 7 = Sonntag).
 * Freitag (TT, 2026-08-13) — zum Wochenausklang, wenn Zeit fürs Nacharbeiten ist.
 */
const TODO_WOCHENTAG_VOLL = 5;

/**
 * Empfänger der Digest-Einträge OHNE Zuständigen (TT, 2026-08-18): genau eine Person
 * statt aller Admins — die tägliche Sammel-Mail an den ganzen Admin-Kreis war Rauschen.
 * Wer namentlich zuständig ist, bekommt seine Einträge unverändert selbst; info@ liest
 * ohnehin jede Mail mit (BCC-Kette in src/channels/mail.php, mailBccAddress()).
 * Bewusst eine Konstante (Login-Mail des users-Eintrags), keine DB-Einstellung.
 */
const TODO_HERRENLOS_EMPFAENGER_EMAIL = 't.tyras@atsv-kirchseeon-marktlauf.de';

/**
 * Wochentags-Labels für die Versandtage-Schalter (ISO: 1 = Montag … 7 = Sonntag).
 * Gepflegt im Orga-UI (Einstellungen → Erinnerungs-Mails), gelesen von
 * reminderVersandtagHeute(). Freitag bleibt in jeder Kombination der volle Überblick.
 */
const REMINDER_TAGE_LABELS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

/**
 * Schnellwahl-Presets fürs UI: Label => Tagesliste. Reine Vorbelegung der Schalter
 * (Muster „Preset über Feinsteuerung", vgl. GitHub Scheduled Reminders / Slack-Modi) —
 * gespeichert wird immer nur die Tagesliste, nie der Preset-Name.
 */
const REMINDER_TAGE_PRESETS = [
    'Täglich'   => [1, 2, 3, 4, 5, 6, 7],
    'Werktags'  => [1, 2, 3, 4, 5],
    'Di + Fr'   => [2, 5],
    'Nur Fr'    => [5],
];

/**
 * Gespeicherte Versandtage als Liste von ISO-Wochentagen.
 *
 * Wertelogik von `reminder_versandtage`: fehlt der Key oder ist der Inhalt unlesbar
 * => alle Tage (eine kaputte Einstellung darf Erinnerungen nie stumm schalten).
 * Der Sonderwert 'keine' (bewusst alle Schalter aus) => leere Liste = Digest aus.
 */
function reminderVersandtage(?string $wert): array
{
    $wert = trim((string) $wert);
    if ($wert === 'keine') {
        return [];
    }
    $tage = array_values(array_unique(array_filter(
        array_map('intval', explode(',', $wert)),
        static fn (int $t): bool => $t >= 1 && $t <= 7
    )));
    return $tage !== [] ? $tage : array_keys(REMINDER_TAGE_LABELS);
}

/**
 * Ist heute laut Einstellungen ein Versandtag für den ToDo-Digest?
 *
 * Der Cron im Workflow läuft weiterhin täglich; gedrosselt wird hier im Skript, damit
 * der Zeitplan ohne Repo-Änderung im Orga-UI umstellbar ist. Zwei Stellschrauben:
 *   - `reminder_versandtage`  Wochentagsliste (siehe reminderVersandtage())
 *   - `reminder_pause_bis`    Urlaubs-Pause, einschließlich dieses Datums (leer = aktiv)
 * DB-Fehler => senden. An Nicht-Versandtagen geht Neues nicht verloren — spätestens
 * der volle Freitags-Überblick (TODO_WOCHENTAG_VOLL) listet alles Offene.
 */
function reminderVersandtagHeute(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen
                             WHERE `key` IN ('reminder_versandtage', 'reminder_pause_bis')");
        $werte = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return true;
    }
    $pauseBis = trim((string) ($werte['reminder_pause_bis'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pauseBis) && date('Y-m-d') <= $pauseBis) {
        return false;
    }
    return in_array((int) date('N'), reminderVersandtage($werte['reminder_versandtage'] ?? null), true);
}

/**
 * Status, die kein ToDo mehr erzeugen. `bestaetigt` fehlt hier bewusst nicht —
 * ab dort übernimmt die Rechnungs-/Abrechnungsstrecke.
 */
const TODO_ABGESCHLOSSEN = ['bestaetigt', 'abgerechnet', 'bezahlt', 'abgelehnt'];

/**
 * Letzte Notiz eines Sponsors auf Listenlänge kürzen.
 *
 * Der Stand aus dem Notizfeld ist der eigentliche Grund, warum eine Anrufliste
 * brauchbar ist („Herrn Wurm verlangen", „meldet sich zurück"). Ohne ihn sieht man
 * nur, DASS etwas offen ist, aber nicht, was zu tun wäre. Genommen wird der ZULETZT
 * angehängte Absatz, weil neue Einträge unten drangehängt werden.
 */
function todoNotizStand(?string $notizen): string
{
    $text = trim((string) $notizen);
    if ($text === '') {
        return '';
    }
    $absaetze = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', $text) ?: [])));

    // Datenpflege-Notizen überspringen. Die stehen zwar zuletzt im Feld, beantworten
    // aber nicht die Frage „was ist als Nächstes zu tun" — „Firmiert als Radlarzt UG"
    // hilft beim Anruf nicht weiter, „auf Mailbox gesprochen" schon. Gesucht ist der
    // letzte Absatz, der KEINE Datenpflege ist; gibt es nur solche, zeigen wir eben den.
    $istDatenpflege = static function (string $absatz): bool {
        return (bool) preg_match('/^(Datenpflege|Recherche|Umbenannt|Import)\b/iu', $absatz);
    };
    $letzter = '';
    foreach (array_reverse($absaetze) as $absatz) {
        if (!$istDatenpflege($absatz)) {
            $letzter = $absatz;
            break;
        }
    }
    if ($letzter === '') {
        $letzter = (string) (end($absaetze) ?: $text);
    }
    $letzter = trim(preg_replace('/\s+/', ' ', $letzter) ?? $letzter);
    if (mb_strlen($letzter) > TODO_NOTIZ_LAENGE) {
        $letzter = mb_substr($letzter, 0, TODO_NOTIZ_LAENGE - 1) . '…';
    }
    return $letzter;
}

/**
 * Titel und Beschreibung je ToDo-Gruppe — die eine Wahrheit für Seite UND Mail.
 *
 * Vorgeschichte: Diese Texte standen zweimal, im Markup von orga/offene_todos.php und
 * im Digest. Als eine Session die Titel schärfte („Bestätigung offen" →
 * „Bestätigungs-Mail offen"), wusste die Mail davon nichts und sah anders aus als die
 * Seite — genau die Beanstandung von TT am 13.08.2026. Seither leben sie hier.
 *
 * Bewusst NICHT hier: die Spaltenköpfe. Die sind medienabhängig — die Seite hat in
 * „Aufgaben am Sponsor" eine Erledigt-Spalte mit Formular, die eine Mail nicht haben kann.
 * Strukturen bleiben beim jeweiligen Renderer, nur Wortlaute sind gemeinsam.
 *
 * `link` ist ein optionaler Absprung, den nur die Seite rendert: In der Mail wäre er
 * überflüssig, dort führt ohnehin ein Button auf die Seite.
 *
 * @return array<string, array{titel:string, sub:string, ton?:string,
 *         link?:array{vor:string, label:string, href:string}}>
 */
function todoGruppenMeta(): array
{
    return [
        'bestaetigung' => [
            'titel' => 'Bestätigungs-Mail offen',
            'sub'   => 'Hat zugesagt — die Bestätigung mit den Sponsoring-Bedingungen ist noch nicht raus.',
            'link'  => ['vor' => 'Läuft über', 'label' => 'Bestätigungen', 'href' => 'bestaetigungen.php'],
        ],
        'bedingungen' => [
            'titel' => 'Sponsoring-Bedingungen nicht bestätigt',
            'sub'   => 'Bestätigung ist raus, die Sponsoring-Bedingungen sind aber noch nicht gegengezeichnet. '
                     . 'Erfassen in der Einzelmaske (wann, auf welchem Weg, Beleg im Ordner).',
        ],
        'wiedervorlagen' => [
            'titel' => 'Wiedervorlage fällig',
            'sub'   => 'Termin gesetzt und erreicht — hier war jemand schon dran und wollte nachfassen.',
        ],
        'versand_fehler' => [
            'titel' => 'Versand-Queue: Fehler',
            'sub'   => 'Ein Anschreiben ist nicht rausgegangen. Betrifft den Job, der automatisch läuft — '
                     . 'bleibt sonst unbemerkt liegen.',
            'ton'   => 'fehler',
        ],
        'nie_angeschrieben' => [
            'titel' => 'Noch nie angeschrieben',
            'sub'   => 'Steht im Bestand, wurde aber nie angesprochen.',
            'link'  => ['vor' => 'Anschreiben läuft über', 'label' => 'Erstanschreiben', 'href' => 'erstanschreiben.php'],
        ],
        'ohne_reaktion' => [
            'titel' => 'Angeschrieben ohne Reaktion',
            'sub'   => 'Seit mindestens ' . TODO_KEINE_REAKTION_TAGE . ' Tagen keine Rückmeldung und kein Termin '
                     . 'gesetzt. Sobald der Status weitergedreht oder eine Wiedervorlage eingetragen wird, fällt '
                     . 'der Eintrag von selbst heraus.',
        ],
        'bedingungen_beleg' => [
            'titel' => 'Sponsoring-Bedingungen bestätigt — Beleg fehlt',
            'sub'   => 'Inhaltlich erledigt, nur die Rückmeldung liegt nicht im Sponsor-Ordner. '
                     . 'Zählt deshalb nicht in die Gesamtzahl.',
            'ton'   => 'nachrichtlich',
        ],
        'sponsor_aufgaben' => [
            'titel' => 'Aufgaben am Sponsor',
            // Wortlaut folgt der Entscheidung von TT (13.08.2026): terminierte Aufgaben zählen
            // mit und stehen in derselben täglichen Mail, mit Vorausschau auf zwei Tage.
            'sub'   => 'Frist und Verantwortliche sind freiwillig — ohne Frist bleibt eine Aufgabe '
                     . 'nachrichtlich und zählt nicht in die Gesamtzahl. Mit Frist zählt sie mit und '
                     . 'steht in der täglichen Erinnerung, sobald die Frist höchstens '
                     . TODO_FRIST_VORSCHAU_TAGE . ' Tage entfernt ist.',
            'ton'   => 'nachrichtlich',
        ],
    ];
}

/**
 * Wählbare Fassung einer Telefonnummer für einen tel:-Link.
 *
 * Nötig, weil in einem Datensatz „Tel. 08091 2038" stand — reines Leerzeichen-Entfernen
 * hätte daraus „Tel.080912038" und damit einen toten Link gemacht. Behalten werden nur
 * Ziffern und ein führendes Plus; die Anzeige bleibt unverändert formatiert.
 */
function todoTelefonHref(string $telefon): string
{
    $plus = str_starts_with(ltrim($telefon), '+') ? '+' : '';
    return $plus . preg_replace('/\D+/', '', $telefon);
}

/**
 * Gemeinsame SELECT-Bausteine für Sponsor-Zeilen: erster hinterlegter Kontakt und
 * die Notiz. Unterabfragen statt JOIN, damit ein Sponsor mit drei Ansprechpartnern
 * nicht dreimal in der Liste steht.
 */
function todoSponsorSpalten(): string
{
    return "
        (SELECT ap.telefon FROM sponsor_ansprechpartner ap
          WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.telefon),'') IS NOT NULL
          ORDER BY ap.id LIMIT 1) AS telefon,
        (SELECT ap.email FROM sponsor_ansprechpartner ap
          WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.email),'') IS NOT NULL
          ORDER BY ap.id LIMIT 1) AS email,
        s.notizen, s.prioritaet, s.zustaendig_user_id,
        (SELECT u.name FROM users u WHERE u.id = s.zustaendig_user_id) AS zustaendig";
}

/** Sponsoren mit fälliger oder überfälliger Wiedervorlage. */
function todosWiedervorlagen(PDO $pdo): array
{
    $platzhalter = implode(',', array_fill(0, count(TODO_ABGESCHLOSSEN), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.firma, s.wiedervorlage, s.status,
               DATEDIFF(CURDATE(), s.wiedervorlage) AS tage,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.wiedervorlage IS NOT NULL
          AND s.wiedervorlage <= CURDATE()
          AND s.kein_kontakt = 0
          AND s.status NOT IN ({$platzhalter})
        ORDER BY s.wiedervorlage ASC, s.firma ASC
    ");
    $stmt->execute(TODO_ABGESCHLOSSEN);
    return $stmt->fetchAll();
}

/**
 * Zugesagt, aber Bestätigung noch nicht raus.
 *
 * Deckt sich mit der Zielgruppe der Seite `bestaetigungen.php`
 * (src/sponsor_zielgruppen.php: status = 'zugesagt'). Sobald die Bestätigung
 * versendet ist, rutscht der Sponsor auf 'bestaetigt' und fällt von selbst heraus —
 * die Liste ist die offene Arbeit, nicht das Archiv.
 */
function todosBestaetigungOffen(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT s.id, s.firma, s.paket, s.summe, s.status,
               DATEDIFF(CURDATE(), DATE(s.updated_at)) AS tage,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.status = 'zugesagt'
        ORDER BY s.updated_at ASC, s.firma ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Noch nie angeschrieben — Status 'neu'.
 *
 * Ein Sponsor, den niemand je angesprochen hat, ist das offensichtlichste offene
 * ToDo überhaupt. Bisher tauchte er nirgends auf und konnte monatelang liegen.
 */
function todosNieAngeschrieben(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT s.id, s.firma, s.status,
               DATEDIFF(CURDATE(), DATE(s.created_at)) AS tage,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.status = 'neu'
          AND s.kein_kontakt = 0
        ORDER BY (s.prioritaet IS NULL), s.prioritaet ASC, s.created_at ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Angeschrieben, aber seit TODO_KEINE_REAKTION_TAGE Tagen ohne Reaktion.
 *
 * „Ohne Reaktion" heißt: Status steht noch auf 'angefragt'. Sobald jemand den Status
 * weiterdreht, fällt der Eintrag automatisch heraus. Wer eine Wiedervorlage hat, ist
 * nicht liegengeblieben, sondern terminiert — ein künftiger Termin ist geplant, ein
 * fälliger steht schon in der Wiedervorlage-Gruppe.
 */
function todosOhneReaktion(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT s.id, s.firma, s.gesendet_am, s.status,
               DATEDIFF(CURDATE(), DATE(s.gesendet_am)) AS tage,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.status = 'angefragt'
          AND s.kein_kontakt = 0
          AND s.gesendet_am IS NOT NULL
          AND DATEDIFF(CURDATE(), DATE(s.gesendet_am)) >= :tage
          AND s.wiedervorlage IS NULL
        ORDER BY (s.prioritaet IS NULL), s.prioritaet ASC, s.gesendet_am ASC
    ");
    $stmt->execute(['tage' => TODO_KEINE_REAKTION_TAGE]);
    return $stmt->fetchAll();
}

/**
 * Offene Aufgaben, die am Sponsor hängen.
 *
 * Datenbasis ist seit Migration 063 die vollwertige `aufgaben`-Tabelle, gebunden über
 * kontext_typ='sponsor'. Die frühere `sponsor_aufgaben` kannte nur Titel + erledigt —
 * ohne Frist und Verantwortlichen fiel nie auf, wenn etwas liegen blieb.
 *
 * Frist und Verantwortlicher sind **optional** (TT 2026-08-13): schnell erfassen soll
 * möglich bleiben. Ob ein Termin gesetzt ist, sagt `faellig_am` (NULL = ohne Frist) —
 * ein zusätzliches Flag wäre nur dessen Negation und damit toter Vorrat.
 *
 * Die Gesamtzahl lässt diese Gruppe weiterhin außen vor (`offeneTodosAlle()`); ob
 * terminierte Aufgaben künftig mitzählen, ist eine offene Frage an TT.
 */
function todosSponsorAufgaben(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT a.id, a.kontext_id AS sponsor_id, a.titel, a.notiz, a.faellig_am,
               DATEDIFF(CURDATE(), a.faellig_am) AS tage_ueberfaellig,
               s.firma, a.verantwortlich_user_id, u.name AS verantwortlich_name
        FROM aufgaben a
        JOIN sponsors s ON s.id = a.kontext_id
        LEFT JOIN users u ON u.id = a.verantwortlich_user_id
        WHERE a.kontext_typ = 'sponsor'
          AND a.status <> 'erledigt'
        ORDER BY (a.faellig_am IS NULL), a.faellig_am ASC, s.firma ASC, a.id ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Bedingungen verschickt, aber vom Sponsor noch nicht bestätigt.
 *
 * Datenbasis ist Migration 062 (bedingungen_bestaetigt_am / _weg / _beleg). „Benötigt"
 * ist die Bestätigung erst ab verschickter Bestätigung — die Status-Liste hier spiegelt
 * bewusst sponsorBedingungenBenoetigt() aus src/sponsor_status.php; wird die dort
 * erweitert, gehört sie hier nachgezogen.
 *
 * Fehlt die Spalte (Migration noch nicht gefahren), wirft die Abfrage — offeneTodosAlle()
 * fängt das ab und lässt die Gruppe leer, statt die Seite abzuschießen.
 */
function todosBedingungenOffen(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT s.id, s.firma, s.status, s.bedingungen_beleg,
               DATEDIFF(CURDATE(), DATE(s.updated_at)) AS tage,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.status IN ('bestaetigt','abgerechnet','bezahlt')
          AND s.bedingungen_bestaetigt_am IS NULL
        ORDER BY s.updated_at ASC, s.firma ASC
    ");
    return $stmt->fetchAll();
}

/** Bedingungen bestätigt, aber die Rückmeldung liegt nicht im Sponsor-Ordner. */
function todosBedingungenBelegFehlt(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT s.id, s.firma, s.status, s.bedingungen_weg,
               DATE(s.bedingungen_bestaetigt_am) AS bestaetigt_am,
               " . todoSponsorSpalten() . "
        FROM sponsors s
        WHERE s.bedingungen_bestaetigt_am IS NOT NULL
          AND s.bedingungen_beleg = 0
        ORDER BY s.bedingungen_bestaetigt_am ASC, s.firma ASC
    ");
    return $stmt->fetchAll();
}

/** Fehlgeschlagene Einträge in der Sponsor-Versand-Queue. */
function todosVersandFehler(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT q.id, q.sponsor_id,
               COALESCE(NULLIF(q.firma, ''), s.firma, '(unbekannt)') AS firma,
               COALESCE(q.fehler_text, '') AS fehler,
               s.zustaendig_user_id,
               (SELECT u.name FROM users u WHERE u.id = s.zustaendig_user_id) AS zustaendig
        FROM sponsor_versand_queue q
        LEFT JOIN sponsors s ON s.id = q.sponsor_id
        WHERE q.status = 'fehler'
        ORDER BY q.id ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Alle ToDo-Gruppen auf einmal, in Bearbeitungsreihenfolge.
 *
 * Jede Gruppe läuft in try/catch: eine fehlende Tabelle darf nur ihre eigene Gruppe
 * leer lassen und nicht die Seite abschießen — dasselbe Prinzip wie bei den
 * KPI-Closures in _nav.php.
 */
function offeneTodosAlle(PDO $pdo): array
{
    $gruppen = [
        'wiedervorlagen'   => 'todosWiedervorlagen',
        'bestaetigung'     => 'todosBestaetigungOffen',
        'bedingungen'      => 'todosBedingungenOffen',
        'bedingungen_beleg' => 'todosBedingungenBelegFehlt',
        'versand_fehler'   => 'todosVersandFehler',
        'nie_angeschrieben' => 'todosNieAngeschrieben',
        'ohne_reaktion'    => 'todosOhneReaktion',
        'sponsor_aufgaben' => 'todosSponsorAufgaben',
    ];

    $ergebnis = [];
    foreach ($gruppen as $key => $fn) {
        try {
            $ergebnis[$key] = $fn($pdo);
        } catch (PDOException $e) {
            if (function_exists('logError')) {
                logError('Offene ToDos (' . $key . '): ' . $e->getMessage());
            }
            $ergebnis[$key] = [];
        }
    }

    // Sponsor-Aufgaben MIT Frist zählen mit (TT, 2026-08-13): eine Aufgabe mit
    // Fälligkeit verhält sich wie eine Wiedervorlage. Ohne Frist bleibt sie
    // nachrichtlich — sie ist nichts, was zu einem Zeitpunkt fällig wäre.
    // Der fehlende Beleg zählt nicht mit: die Sache ist inhaltlich erledigt, es fehlt
    // nur die Ablage. Sichtbar ja, als Druckmittel in der Gesamtzahl nein.
    $ergebnis['gesamt'] = count($ergebnis['wiedervorlagen'])
        + count($ergebnis['bestaetigung'])
        + count($ergebnis['bedingungen'])
        + count($ergebnis['versand_fehler'])
        + count($ergebnis['nie_angeschrieben'])
        + count($ergebnis['ohne_reaktion'])
        + count(array_filter(
            $ergebnis['sponsor_aufgaben'],
            static fn (array $a): bool => !empty($a['faellig_am'])
        ));

    return $ergebnis;
}
