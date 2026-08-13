<?php
/**
 * Offene ToDos — die eine Wahrheit darüber, was gerade Aufmerksamkeit braucht.
 *
 * Genutzt von ZWEI Verbrauchern, die deshalb nicht auseinanderdriften können:
 *   - orga/index.php              → Block „Offene ToDos" oben im Cockpit
 *   - bin/offene_todos_digest.php → tägliche Erinnerungsmail
 *
 * Spec: intern/offene-todos-spec.md
 *
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
 * standen allein 28 unbeantwortete Anschreiben in der Liste (Anschreiben-Wellen vom
 * 08. und 16.07.). Ungedeckelt wäre der Cockpit-Block unlesbar und die Tagesmail
 * für neun Empfänger reines Rauschen. Die Gesamtzahl bleibt davon unberührt —
 * gedeckelt wird nur die Darstellung, nicht die Zählung.
 */
const TODO_LISTE_MAX = 8;

/**
 * Status, die als „erledigt/abgeschlossen" gelten und nie ein ToDo erzeugen.
 * Abgelehnt zählt dazu — wer abgesagt hat, braucht keine Wiedervorlage mehr.
 */
const TODO_ABGESCHLOSSEN = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt', 'abgelehnt'];

/**
 * Sponsoren mit fälliger oder überfälliger Wiedervorlage.
 *
 * @return array<int,array{id:int,firma:string,wiedervorlage:string,status:string,tage:int,telefon:string,email:string}>
 */
function todosWiedervorlagen(PDO $pdo): array
{
    $platzhalter = implode(',', array_fill(0, count(TODO_ABGESCHLOSSEN), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.firma, s.wiedervorlage, s.status,
               DATEDIFF(CURDATE(), s.wiedervorlage) AS tage,
               (SELECT ap.telefon FROM sponsor_ansprechpartner ap
                 WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.telefon),'') IS NOT NULL
                 ORDER BY ap.id LIMIT 1) AS telefon,
               (SELECT ap.email FROM sponsor_ansprechpartner ap
                 WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.email),'') IS NOT NULL
                 ORDER BY ap.id LIMIT 1) AS email
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
 * Orga-Aufgaben, deren Fälligkeit in der Vergangenheit liegt.
 *
 * Bewusst NUR überfällige: die heute fälligen verschickt bereits
 * bin/aufgaben_erinnerung.php als Einzelmail. So überschneiden sich die beiden
 * Wege nicht und niemand bekommt dieselbe Aufgabe zweimal gemeldet.
 *
 * @return array<int,array{id:int,titel:string,faellig_am:string,status:string,tage:int,verantwortlich:string}>
 */
function todosUeberfaelligeAufgaben(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT a.id, a.titel, a.faellig_am, a.status,
               DATEDIFF(CURDATE(), a.faellig_am) AS tage,
               COALESCE(u.name, '') AS verantwortlich
        FROM aufgaben a
        LEFT JOIN users u ON u.id = a.verantwortlich_user_id
        WHERE a.faellig_am IS NOT NULL
          AND a.faellig_am < CURDATE()
          AND a.status != 'erledigt'
        ORDER BY a.faellig_am ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Angeschrieben, aber seit TODO_KEINE_REAKTION_TAGE Tagen ohne Reaktion.
 *
 * „Ohne Reaktion" heißt hier: Status steht noch auf 'angefragt'. Sobald jemand
 * den Status weiterdreht (in Klärung, zugesagt, abgelehnt …), ist es keine
 * liegengebliebene Anfrage mehr und fällt automatisch aus der Liste.
 *
 * @return array<int,array{id:int,firma:string,gesendet_am:string,tage:int,telefon:string,email:string}>
 */
function todosOhneReaktion(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT s.id, s.firma, s.gesendet_am,
               DATEDIFF(CURDATE(), DATE(s.gesendet_am)) AS tage,
               (SELECT ap.telefon FROM sponsor_ansprechpartner ap
                 WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.telefon),'') IS NOT NULL
                 ORDER BY ap.id LIMIT 1) AS telefon,
               (SELECT ap.email FROM sponsor_ansprechpartner ap
                 WHERE ap.sponsor_id = s.id AND NULLIF(TRIM(ap.email),'') IS NOT NULL
                 ORDER BY ap.id LIMIT 1) AS email
        FROM sponsors s
        WHERE s.status = 'angefragt'
          AND s.kein_kontakt = 0
          AND s.gesendet_am IS NOT NULL
          AND DATEDIFF(CURDATE(), DATE(s.gesendet_am)) >= :tage
          AND (s.wiedervorlage IS NULL OR s.wiedervorlage > CURDATE())
        ORDER BY s.gesendet_am ASC, s.firma ASC
    ");
    $stmt->execute(['tage' => TODO_KEINE_REAKTION_TAGE]);
    return $stmt->fetchAll();
}

/**
 * Fehlgeschlagene Einträge in der Sponsor-Versand-Queue.
 * Betrifft den einzigen Job, der wirklich dauerhaft automatisch läuft — ein
 * Fehler dort bleibt sonst unbemerkt liegen.
 *
 * @return array<int,array{id:int,firma:string,fehler:string}>
 */
function todosVersandFehler(PDO $pdo): array
{
    // firma steht als Momentaufnahme in der Queue selbst; der Join dient nur als
    // Rückfallebene, falls die Zeile ohne Firmennamen angelegt wurde.
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
 * Offene Sponsor-Aufgaben — nur als Zähler je Sponsor.
 *
 * sponsor_aufgaben hat weder Fälligkeitsdatum noch Verantwortlichen
 * (migrations/006_sponsors.sql). Ein Termin wäre also erfunden; deshalb bewusst
 * nur „N offen" statt einer Terminliste. Siehe Spec, Entscheidung von TT.
 *
 * @return array<int,array{sponsor_id:int,firma:string,anzahl:int}>
 */
function todosSponsorAufgaben(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT sa.sponsor_id, s.firma, COUNT(*) AS anzahl
        FROM sponsor_aufgaben sa
        JOIN sponsors s ON s.id = sa.sponsor_id
        WHERE sa.erledigt = 0
        GROUP BY sa.sponsor_id, s.firma
        ORDER BY s.firma ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Alle ToDo-Gruppen auf einmal.
 *
 * Jede Gruppe läuft in try/catch: eine fehlende Tabelle (z. B. weil eine
 * Migration noch aussteht) darf nur ihre eigene Gruppe leer lassen und nicht das
 * ganze Cockpit abschießen — dasselbe Prinzip wie bei den KPI-Closures in _nav.php.
 *
 * @return array{
 *   wiedervorlagen: array<int,array<string,mixed>>,
 *   aufgaben: array<int,array<string,mixed>>,
 *   ohne_reaktion: array<int,array<string,mixed>>,
 *   versand_fehler: array<int,array<string,mixed>>,
 *   sponsor_aufgaben: array<int,array<string,mixed>>,
 *   gesamt: int
 * }
 */
function offeneTodosAlle(PDO $pdo): array
{
    $gruppen = [
        'wiedervorlagen'   => 'todosWiedervorlagen',
        'aufgaben'         => 'todosUeberfaelligeAufgaben',
        'ohne_reaktion'    => 'todosOhneReaktion',
        'versand_fehler'   => 'todosVersandFehler',
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

    // Sponsor-Aufgaben zählen nicht in die Gesamtzahl: sie haben keinen Termin und
    // sind damit nichts, was „heute" fällig wäre. Sie werden nur nachrichtlich gezeigt.
    $ergebnis['gesamt'] = count($ergebnis['wiedervorlagen'])
        + count($ergebnis['aufgaben'])
        + count($ergebnis['ohne_reaktion'])
        + count($ergebnis['versand_fehler']);

    return $ergebnis;
}
