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
 * Deutscher Wochentag zu einem ISO-Datum (locale-unabhaengig).
 *
 * date('l') liefert auf dem Server Englisch. Die Helferseite hat dadurch
 * "Sunday, 20.09.2026" gezeigt, waehrend im Einsatzplan-PDF desselben Helfers
 * "Sonntag" stand -- deshalb holen sich Seite, PDF und Orga-Board den Wochentag
 * ab jetzt hier.
 *
 * Leerer String, wenn das Datum nicht lesbar ist; die Aufrufer lassen den
 * Wochentag dann einfach weg.
 */
function helferWochentag(string $isoDate): string
{
    static $wt = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    $ts = strtotime($isoDate);

    return $ts === false ? '' : $wt[(int) date('N', $ts)];
}

/**
 * Deutsches Wochentag-Datum-Label aus einem ISO-Datum (locale-unabhaengig).
 */
function helferTagLabel(string $isoDate): string
{
    $ts = strtotime($isoDate);
    if ($ts === false) {
        return $isoDate;
    }

    return helferWochentag($isoDate) . ' · ' . date('d.m.Y', $ts);
}

/**
 * ORDER-BY-Ausdruck fuer Schichten in Anzeigereihenfolge.
 *
 * Ab Migration 077 gewinnt die Handsortierung aus dem Board (`sortierung`)
 * innerhalb eines Tages; Zeit und Titel bleiben Tie-Breaker. Solange die
 * Migration auf einem Stand noch nicht angewandt ist, faellt der Ausdruck auf
 * die bisherige reine Zeitsortierung zurueck — damit laeuft die Anwendung
 * zwischen Deploy und Migration weiter (Deploy und Migration sind hier bewusst
 * entkoppelt, siehe CLAUDE.md).
 */
function schichtenOrderBy(PDO $pdo, string $alias = '', string $tieBreak = 'titel'): string
{
    static $hatSortierung = null;

    if ($hatSortierung === null) {
        try {
            $hatSortierung = $pdo->query("SHOW COLUMNS FROM schichten LIKE 'sortierung'")->fetch() !== false;
        } catch (PDOException $e) {
            $hatSortierung = false;
        }
    }

    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $teile = ["({$p}tag IS NULL)", "{$p}tag"];
    if ($hatSortierung) {
        $teile[] = "{$p}sortierung";
    }
    $teile[] = "({$p}von IS NULL)";
    $teile[] = "{$p}von";
    $teile[] = $p . $tieBreak;

    return implode(', ', $teile);
}

/**
 * Angebotene Schichten, gruppiert nach Tag — Struktur wie das bisherige Formular
 * erwartet: [tag => ['label' => ..., 'aufgaben' => [['key','beschreibung','zeitfenster','gesperrt'], ...]]].
 * 'key' = schicht_id (string), 'beschreibung' = Schicht-Titel.
 *
 * Gezeigt werden beide sichtbaren Zustaende aus Migration 093: buchbar (1) und
 * gesperrt (2). 'gesperrt' sagt dem Formular, welche Zeile ausgegraut wird;
 * 'hat_offene' sagt ihm, ob der Tag aufgeklappt startet (ein Tag ohne einen
 * einzigen buchbaren Punkt ist nur noch Nachschlagewerk).
 *
 * Sortierung: Tage chronologisch, innerhalb eines Tages die buchbaren zuerst —
 * sonst versteckt sich der letzte offene Punkt zwischen zehn grauen Zeilen.
 */
function helferAufgabenKatalog(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $pdo = getDbConnection();
    $rows = $pdo->query('
        SELECT id, titel, tag, von, bis, zeitfenster, in_anmeldung
        FROM schichten
        WHERE in_anmeldung IN (1, 2)
        ORDER BY (tag IS NULL), tag, (in_anmeldung <> 1), (von IS NULL), von, id
    ')->fetchAll();

    $katalog = [];
    foreach ($rows as $r) {
        $tag = (string) ($r['tag'] ?? '');
        if (!isset($katalog[$tag])) {
            $katalog[$tag] = [
                'label'      => $tag !== '' ? helferTagLabel($tag) : 'Termin nach Absprache',
                'hat_offene' => false,
                'aufgaben'   => [],
            ];
        }
        $gesperrt = (int) $r['in_anmeldung'] === 2;
        if (!$gesperrt) {
            $katalog[$tag]['hat_offene'] = true;
        }
        $katalog[$tag]['aufgaben'][] = [
            'key'         => (string) $r['id'],
            'beschreibung' => (string) $r['titel'],
            'zeitfenster' => helferSchichtZeitfenster($r),
            'gesperrt'    => $gesperrt,
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
 * nicht (mehr) buchbar. Rueckgabe kompatibel zum bisherigen Katalog:
 * ['tag','zeitfenster','beschreibung'] (+ 'schicht_id').
 *
 * WICHTIG: das `in_anmeldung = 1` unten ist der eigentliche Riegel fuer gesperrte
 * Schichten (2). Das `disabled` im Formular ist reine Optik und per DevTools in
 * einer Sekunde weg — erst diese Abfrage verhindert die Buchung.
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

/**
 * Erreichbarkeiten am Renntag, in Anzeigereihenfolge.
 *
 * EINE Quelle fuer beide Ausgaben: die Kontakt-Kachel auf helfer/zugang.php und
 * der Kasten "Fragen vor Ort oder vorher?" im persoenlichen Einsatzplan-PDF
 * (src/einsatzplan_pdf.php). Liefen die auseinander, haette der Helfer auf dem
 * Handy eine andere Nummer als auf dem Ausdruck in der Jackentasche.
 *
 * Die Werte stehen bewusst NICHT im Code, sondern in storage/config.php unter
 * orga.renntag_kontakte: das Repo ist oeffentlich, private Handynummern gehoeren
 * nicht in die Git-Historie (CLAUDE.md, "Oeffentliches Repo, Datenhygiene").
 * Struktur und Beispiel: storage/config.sample.php.
 *
 * Ist nichts konfiguriert, kommt eine leere Liste zurueck und beide Ausgaben
 * lassen den Block weg — lieber keine Nummer als eine falsche.
 *
 * @return list<array{rolle:string,name:string,wann:string,tel:string,tel_e164:string}>
 */
function renntagKontakte(): array
{
    try {
        $config = getConfig();
    } catch (Throwable $e) {
        return [];
    }

    $roh = $config['orga']['renntag_kontakte'] ?? [];
    if (!is_array($roh)) {
        return [];
    }

    $kontakte = [];
    foreach ($roh as $k) {
        if (!is_array($k)) {
            continue;
        }
        $tel = trim((string) ($k['tel'] ?? ''));
        if ($tel === '') {
            continue; // ohne Nummer ist der Eintrag wertlos
        }
        $kontakte[] = [
            'rolle'    => trim((string) ($k['rolle'] ?? '')),
            'name'     => trim((string) ($k['name'] ?? '')),
            'wann'     => trim((string) ($k['wann'] ?? '')),
            'tel'      => $tel,
            'tel_e164' => renntagTelE164($tel, (string) ($k['tel_e164'] ?? '')),
        ];
    }

    return $kontakte;
}

/**
 * Waehlbare Fassung einer Nummer fuer href="tel:".
 *
 * Nimmt, was in der Config steht, wenn dort eine internationale Fassung
 * hinterlegt ist. Sonst deutsche Ableitung: Leerzeichen und Trennzeichen raus,
 * fuehrende 0 durch +49 ersetzen. Passt die Nummer in kein bekanntes Muster,
 * bleibt sie unveraendert — ein Link, der den Waehler oeffnet, ist immer noch
 * besser als gar keiner.
 */
function renntagTelE164(string $tel, string $vorgabe = ''): string
{
    $vorgabe = preg_replace('/[^\d+]/', '', $vorgabe) ?? '';
    if ($vorgabe !== '') {
        return $vorgabe;
    }

    $ziffern = preg_replace('/[^\d+]/', '', $tel) ?? '';
    if (str_starts_with($ziffern, '+')) {
        return $ziffern;
    }
    if (str_starts_with($ziffern, '00')) {
        return '+' . substr($ziffern, 2);
    }
    if (str_starts_with($ziffern, '0')) {
        return '+49' . substr($ziffern, 1);
    }

    return $ziffern;
}
