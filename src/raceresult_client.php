<?php
/**
 * RaceResult-Client — echter Ergebnis-Abruf über die RaceResult "Simple API".
 *
 * Event: 2. Marktlauf Kirchseeon, RaceResult-Event-ID 377952
 *   (https://events.raceresult.com/377952/ — Stand 2026-07-15 noch Testmodus).
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

const RACERESULT_EVENT_ID = 377952;

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
        // Feld-Mapping noch nicht gegen die reale Liste finalisiert -> Fallback
        return raceResultMock();
    }

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
 * RaceResult-SimpleAPI-"Liste" auf das raceResultMock()-Shape abbilden.
 *
 * OFFEN — finalisieren, sobald die SimpleAPI-Freigabe existiert UND eine
 * Beispiel-Antwort mit (Test-)Daten vorliegt: Die "Liste" liefert JSON in der
 * Form { "data": { "<Gruppe>": [ [feld1, feld2, ...], ... ] }, ... }; Auswahl
 * und Reihenfolge der Felder hängen an der konkret konfigurierten Liste. Bis das
 * gegen echte Daten verifiziert ist, wird bewusst NULL zurückgegeben, damit der
 * garantierte Mock-Fallback greift (Feldreihenfolge wird nicht geraten).
 * Redaktionelle Felder (highlight, wetter, laeufernationen) stammen nicht aus
 * der Ergebnisliste und bleiben manuell/abgeleitet.
 *
 * @param array $raw Rohe SimpleAPI-Antwort
 * @return array|null Gemapptes Shape wie raceResultMock() oder null (Fallback)
 */
function raceResultMapList(array $raw): ?array
{
    return null;
}
