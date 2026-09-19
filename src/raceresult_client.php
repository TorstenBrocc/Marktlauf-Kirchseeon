<?php
/**
 * RaceResult-Client — echter Ergebnis-Abruf über die RaceResult "Simple API".
 *
 * Event: Marktlauf Kirchseeon 2026, RaceResult-Event-ID 412617
 *   (https://events.raceresult.com/412617/).
 *
 * Datenquelle ist eine in RaceResult angelegte SimpleAPI-"Freigabe" vom Typ
 * "Liste"; deren statischer Link wird in den Einstellungen (Key `raceresult_api_url`)
 * hinterlegt — eigener Key, NICHT der bestehende `raceresult_url` (der eine andere
 * Funktion in den Admin-Einstellungen hat). raceResultData() ruft diesen Link ab und liefert dasselbe
 * Array-Shape wie raceResultMock(). Solange kein Link konfiguriert ist, der
 * Abruf scheitert oder (vor dem Renntag) keine Daten liefert, fällt die Funktion
 * automatisch auf raceResultMock() zurück — der Social-Flow bleibt damit
 * jederzeit funktionsfähig. Löst die im Mock skizzierte Interface-Idee
 * raceResultFetch(eventId, listId) ab (die SimpleAPI arbeitet mit statischem
 * Link statt eventId/listId-Parametern).
 */

declare(strict_types=1);

require_once __DIR__ . '/raceresult_mock.php';
require_once __DIR__ . '/logger.php';

const RACERESULT_EVENT_ID = 412617;

/**
 * Ergebnis-Daten für den Social-Flow. Versucht den echten Abruf, fällt bei
 * jedem Problem (nicht konfiguriert, Netzfehler, leer, Mapping offen) auf den
 * Mock zurück.
 */
function raceResultData(?PDO $pdo = null): array
{
    $url = raceResultConfiguredUrl($pdo);
    if ($url === '') {
        return raceResultMock();
    }

    $raw = raceResultFetchRaw($url);
    if ($raw === null) {
        return raceResultMock();
    }

    $mapped = raceResultMapList($raw);
    if ($mapped === null) {
        // Antwortstruktur unerwartet -> Fallback, nichts raten
        return raceResultMock();
    }

    // Name/Datum liefert die Ergebnisliste nicht; sie kommen aus den Einstellungen.
    $mapped['event'] = raceResultEventMeta($pdo);

    return $mapped;
}

/** SimpleAPI-Listen-URL aus den Einstellungen lesen (Key raceresult_api_url). */
function raceResultConfiguredUrl(?PDO $pdo): string
{
    if (!$pdo instanceof PDO) {
        return '';
    }
    try {
        $stmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
        $stmt->execute(['key' => 'raceresult_api_url']);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    } catch (PDOException $e) {
        logError('raceResultConfiguredUrl: ' . $e->getMessage());
        return '';
    }
}

/** SimpleAPI-Link abrufen und JSON dekodieren. Null bei Fehler/leerer Antwort. */
function raceResultFetchRaw(string $url): ?array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'MarktlaufKirchseeon/1.0',
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        logError('raceResultFetchRaw: HTTP ' . $status . ' ' . $err);
        return null;
    }
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || $decoded === []) {
        return null;
    }
    return $decoded;
}

/**
 * Veranstaltungs-Metadaten. Die Ergebnis-API liefert sie nicht mit, deshalb
 * kommen sie aus den Orga-Einstellungen. Nicht ermittelbare Werte bleiben leer
 * (bewusst kein Rueckgriff auf raceResultMock()).
 */
function raceResultEventMeta(?PDO $pdo): array
{
    $meta = ['name' => '', 'datum' => '', 'ort' => ''];
    if (!$pdo instanceof PDO) {
        return $meta;
    }
    try {
        $stmt  = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('veranstaltungsname','renntag_datum')");
        $werte = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $meta['name']  = trim((string) ($werte['veranstaltungsname'] ?? ''));
        $meta['datum'] = trim((string) ($werte['renntag_datum'] ?? ''));
    } catch (PDOException $e) {
        logError('raceResultEventMeta: ' . $e->getMessage());
    }

    return $meta;
}

/**
 * Spaltenindizes der SimpleAPI-Antwort.
 *
 * Die Reihenfolge ist die des &fields=-Ausdrucks der Freigabe "Social-Pipeline"
 * (RaceResult 412617, Grundeinstellungen -> Zugriffsrechte/Freigabe, Typ
 * "Benutzerdefiniert"):
 *
 *   data/list?lang=de&listformat=JSON&fields=Contest,Contest.Name,Bib,Firstname,
 *   Lastname,Gender,Club,Nation,AgeGroup.Name,Status,MWPl,AKPl,Ziel.Chip
 *
 * Wer den Ausdruck dort aendert, MUSS diese Konstanten mitziehen.
 * Am 18.09.2026 gegen die echte Antwort verifiziert: 95 Zeilen, 13 Spalten,
 * flaches Array von Arrays (keine { "data": { ... } }-Huelle).
 */
const RR_SPALTEN      = 13;
const RR_CONTEST      = 0;
const RR_CONTEST_NAME = 1;
const RR_BIB          = 2;
const RR_FIRSTNAME    = 3;
const RR_LASTNAME     = 4;
const RR_GENDER       = 5;
const RR_CLUB         = 6;
const RR_NATION       = 7;
const RR_AGEGROUP     = 8;
const RR_STATUS       = 9;
const RR_MWPL         = 10;
const RR_AKPL         = 11;
const RR_ZEIT_NETTO   = 12;

/**
 * Wettbewerbe, aus denen nur der VORNAME veroeffentlicht wird (Inhaber-Entscheid
 * 2026-09-19): die Schuelerlaeufe 1 km und 2 km. Begruendung: Es sind Kinder, und
 * ihre Nachnamen sollen nicht in oeffentliche Beitraege oder Grafiken geraten.
 * Greift ueberall, wo raceResultData() gelesen wird -- also auch im Prompt-Text
 * des Renntags-Nachberichts, nicht nur auf der Grafik.
 *
 * Werte sind die Wettbewerbs-IDs aus RaceResult (Contest):
 *   1 = 500 m Bambini, 2 = 1 km Schueler, 3 = 2 km Schueler, 4 = 5 km, 5 = 10 km
 *
 * Bambini (1) ist mitaufgenommen, obwohl dort mangels Zeitnahme nie eine
 * Platzierung und damit nie ein Name entsteht: Es sind die juengsten Kinder der
 * Veranstaltung, und sollte dort jemals gemessen werden, greift die Regel ohne
 * weiteres Zutun.
 *
 * NICHT erfasst: die Jugendklassen (U16/U18) im 5-km-Lauf. Dort laufen Kinder
 * und Erwachsene im selben Wettbewerb, eine Regel je Wettbewerb greift also zu
 * grob -- das braeuchte eine Regel je Teilnehmer ueber die Altersklasse.
 * Deren Altersklassen-Sieger erscheinen weiterhin mit vollem Namen. Offen.
 */
const RR_NUR_VORNAME_WETTBEWERBE = [1, 2, 3];

/**
 * Anzeigename eines Teilnehmers fuer die Veroeffentlichung.
 *
 * In den Wettbewerben aus RR_NUR_VORNAME_WETTBEWERBE wird der Nachname
 * weggelassen. Sonst "Vorname Nachname".
 */
function raceResultName(string $vorname, string $nachname, int $wettbewerb): string
{
    $vorname  = trim($vorname);
    $nachname = trim($nachname);
    if (in_array($wettbewerb, RR_NUR_VORNAME_WETTBEWERBE, true)) {
        // Faellt der Vorname aus, lieber gar nichts als den Nachnamen allein.
        return $vorname;
    }

    return trim($vorname . ' ' . $nachname);
}

/**
 * SimpleAPI-Antwort auf das raceResultMock()-Shape abbilden.
 *
 * Erwartet ein flaches Array von Zeilen mit je RR_SPALTEN Werten. Weicht die
 * Struktur davon ab, wird NULL geliefert, damit der Mock-Fallback greift statt
 * halb gemappter Daten.
 *
 * Nicht befuellt werden 'wetter' und 'highlight': die stammen nicht aus
 * RaceResult. Sie bleiben leer und werden NICHT aus raceResultMock() ergaenzt --
 * der Mock-Highlight ist eine erfundene Tatsachenbehauptung und darf in keinen
 * echten Nachbericht geraten.
 *
 * @param array $raw Rohe SimpleAPI-Antwort
 * @return array|null Gemapptes Shape ohne 'event' oder null (Fallback)
 */
function raceResultMapList(array $raw): ?array
{
    $zeilen = [];
    foreach ($raw as $zeile) {
        if (!is_array($zeile) || count($zeile) !== RR_SPALTEN) {
            return null;
        }
        $zeilen[] = array_values($zeile);
    }
    if ($zeilen === []) {
        return null;
    }

    $rennen   = [];
    $finisher = 0;
    $nationen = [];

    foreach ($zeilen as $z) {
        $cid = (string) $z[RR_CONTEST];
        if (!isset($rennen[$cid])) {
            $rennen[$cid] = [
                'kategorie'  => (string) $z[RR_CONTEST_NAME],
                'teilnehmer' => 0,
                // immer vorhanden: social_generate.php greift ungeprueft auf
                // $r['sieger']['name'] zu.
                'sieger'     => ['name' => '', 'zeit' => '', 'verein' => ''],
                'ak_sieger'  => [],
            ];
        }
        $rennen[$cid]['teilnehmer']++;

        $zeit = trim((string) $z[RR_ZEIT_NETTO]);
        if ($zeit !== '') {
            $finisher++;
        }

        $nation = trim((string) $z[RR_NATION]);
        if ($nation !== '') {
            $nationen[$nation] = true;
        }

        $name = raceResultName(
            (string) $z[RR_FIRSTNAME],
            (string) $z[RR_LASTNAME],
            (int) $z[RR_CONTEST]
        );
        $verein = trim((string) $z[RR_CLUB]);

        // Vor dem Zieleinlauf stehen die Platzierungen auf -1, nicht auf 0/leer.
        if ((int) $z[RR_MWPL] === 1) {
            $key = strtolower(trim((string) $z[RR_GENDER])) === 'f' ? 'siegerin' : 'sieger';
            $rennen[$cid][$key] = ['name' => $name, 'zeit' => $zeit, 'verein' => $verein];
        }
        if ((int) $z[RR_AKPL] === 1) {
            $rennen[$cid]['ak_sieger'][] = [
                'ak'   => (string) $z[RR_AGEGROUP],
                'name' => $name,
                'zeit' => $zeit,
            ];
        }
    }

    ksort($rennen, SORT_NUMERIC);
    foreach ($rennen as &$r) {
        if ($r['ak_sieger'] === []) {
            unset($r['ak_sieger']);
        }
    }
    unset($r);

    return [
        'gesamt' => [
            'teilnehmer'      => count($zeilen),
            'finisher'        => $finisher,
            'laeufernationen' => count($nationen),
            'wetter'          => '',
        ],
        'rennen'    => array_values($rennen),
        'highlight' => '',
    ];
}
