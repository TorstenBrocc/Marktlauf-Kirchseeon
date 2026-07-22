<?php
/**
 * Helfer-Aufgaben = im Anmeldeformular angebotene Schichten.
 *
 * Quelle ist ab Migration 026 die schichten-Tabelle (in_anmeldung = 1) — NICHT
 * mehr ein hart kodierter Katalog. Damit sind Anmeldeformular und Einsatzplan
 * dieselbe Wahrheit: Legt die Orga im Einsatzplan eine Schicht an (mit Haken
 * "in Anmeldung zeigen"), erscheint sie automatisch hier.
 *
 * Die Funktionsnamen/-signaturen bleiben stabil, damit Formular und
 * Registrierungs-Handler unveraendert damit arbeiten. Der frueher genutzte
 * String-"key" ist jetzt die schicht_id.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Deutsches Wochentag-Datum-Label aus einem ISO-Datum (locale-unabhaengig).
 */
function helferTagLabel(string $isoDate): string
{
    static $wt = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    $ts = strtotime($isoDate);
    if ($ts === false) {
        return $isoDate;
    }
    return $wt[(int) date('N', $ts)] . ' · ' . date('d.m.Y', $ts);
}

/**
 * Angebotene Schichten, gruppiert nach Tag — Struktur wie das bisherige Formular
 * erwartet: [tag => ['label' => ..., 'aufgaben' => [['key','beschreibung','zeitfenster'], ...]]].
 * 'key' = schicht_id (string), 'beschreibung' = Schicht-Titel.
 */
function helferAufgabenKatalog(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $pdo = getDbConnection();
    $rows = $pdo->query('
        SELECT id, titel, tag, von, bis, zeitfenster
        FROM schichten
        WHERE in_anmeldung = 1
        ORDER BY (tag IS NULL), tag, (von IS NULL), von, id
    ')->fetchAll();

    $katalog = [];
    foreach ($rows as $r) {
        $tag = (string) ($r['tag'] ?? '');
        if (!isset($katalog[$tag])) {
            $katalog[$tag] = [
                'label'    => $tag !== '' ? helferTagLabel($tag) : 'Termin nach Absprache',
                'aufgaben' => [],
            ];
        }
        $katalog[$tag]['aufgaben'][] = [
            'key'         => (string) $r['id'],
            'beschreibung' => (string) $r['titel'],
            'zeitfenster' => helferSchichtZeitfenster($r),
        ];
    }

    return $cache = $katalog;
}

/**
 * Zeitfenster-Anzeige einer Schicht: bevorzugt feste Uhrzeit (von/bis),
 * sonst das Freitext-Label, sonst leer.
 */
function helferSchichtZeitfenster(array $s): string
{
    if (!empty($s['von'])) {
        $z = substr((string) $s['von'], 0, 5);
        if (!empty($s['bis'])) {
            $z .= '–' . substr((string) $s['bis'], 0, 5);
        }
        return $z;
    }
    return (string) ($s['zeitfenster'] ?? '');
}

/**
 * Angebotene Schicht per Key (= schicht_id) aufloesen. null wenn unbekannt oder
 * nicht (mehr) im Formular angeboten. Rueckgabe kompatibel zum bisherigen
 * Katalog: ['tag','zeitfenster','beschreibung'] (+ 'schicht_id').
 */
function helferAufgabeByKey(string $key): ?array
{
    if ($key === '' || !ctype_digit($key)) {
        return null;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        SELECT id, titel, tag, von, bis, zeitfenster
        FROM schichten
        WHERE id = :id AND in_anmeldung = 1
    ');
    $stmt->execute(['id' => (int) $key]);
    $r = $stmt->fetch();
    if (!$r) {
        return null;
    }

    return [
        'schicht_id'  => (int) $r['id'],
        'tag'         => (string) $r['tag'],
        'zeitfenster' => helferSchichtZeitfenster($r),
        'beschreibung' => (string) $r['titel'],
    ];
}
