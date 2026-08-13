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
 * sponsor_aufgaben hat weder Fälligkeitsdatum noch Verantwortlichen
 * (migrations/006_sponsors.sql) — ein Termin wäre also erfunden. Gezeigt wird
 * deshalb der Titel ohne Datum, nicht in die Gesamtzahl gerechnet.
 */
function todosSponsorAufgaben(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT sa.id, sa.sponsor_id, sa.titel, s.firma
        FROM sponsor_aufgaben sa
        JOIN sponsors s ON s.id = sa.sponsor_id
        WHERE sa.erledigt = 0
        ORDER BY s.firma ASC, sa.id ASC
    ");
    return $stmt->fetchAll();
}

/** Fehlgeschlagene Einträge in der Sponsor-Versand-Queue. */
function todosVersandFehler(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT q.id, q.sponsor_id,
               COALESCE(NULLIF(q.firma, ''), s.firma, '(unbekannt)') AS firma,
               COALESCE(q.fehler_text, '') AS fehler
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

    // Sponsor-Aufgaben zählen nicht mit: sie haben keinen Termin und sind damit
    // nichts, was „heute" fällig wäre. Sie werden nur nachrichtlich gezeigt.
    $ergebnis['gesamt'] = count($ergebnis['wiedervorlagen'])
        + count($ergebnis['bestaetigung'])
        + count($ergebnis['versand_fehler'])
        + count($ergebnis['nie_angeschrieben'])
        + count($ergebnis['ohne_reaktion']);

    return $ergebnis;
}
