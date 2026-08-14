<?php
/**
 * Anlaesse/Themen der Social-Media-Strecke — EINE Quelle fuer Maske und APIs.
 * Fahrplan (orga/social_fahrplan.php) und Post-Detail (orga/social_post.php)
 * rendern hieraus, social_generate.php nimmt die Prompt-Beschreibung,
 * social_prompt.php validiert gegen die Keys, der Digest nimmt die UI-Labels.
 * 'presse' => true markiert pressefaehige Anlaesse (Presse-Feld + Presse-LLM-Call).
 * 'fakten' = editierbare Vorbelegung der Fakten/Stichpunkte im Post-Detail —
 * gespeicherte Nutzerwerte (einstellungen.social_prompts) gewinnen immer.
 * Neue Anlaesse nur hier ergaenzen.
 */

declare(strict_types=1);

/**
 * Default-Hashtags — gegrillter 5er-Satz (Inhaber-Go 2026-08-14, Recherche best-hashtags):
 * 1 Marken-Tag (Wiedererkennung) + 2 lokale (Sweetspots) + 2 thematische.
 * Anlassbezogen darf Platz 5 tauschen (#bambinilauf, #energieumwelttag).
 */
function socialHashtagsDefault(): string
{
    return '#marktlaufkirchseeon #kirchseeon #ebersberg #volkslauf #laufenverbindet';
}

/**
 * Verbindliche Event-Eckdaten fuer jeden Text-Prompt — aus den Einstellungen
 * (renntag_datum, veranstaltungsname), damit das LLM keine Daten erfindet.
 */
function socialEckdaten(PDO $pdo): string
{
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('renntag_datum', 'veranstaltungsname')");
        $werte = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return '';
    }
    $name  = trim((string) ($werte['veranstaltungsname'] ?? '')) ?: 'Marktlauf Kirchseeon';
    $datum = trim((string) ($werte['renntag_datum'] ?? ''));
    if ($datum === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        return '';
    }
    $wochentage = [1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $ts = strtotime($datum);
    return 'Veranstaltung: ' . $name . ' — Renntag: ' . $wochentage[(int) date('N', $ts)] . ', ' . date('d.m.Y', $ts)
        . ' — Ort: JEK, Westring 6, Kirchseeon';
}

/**
 * @return array<string, array{gruppe: string, ui: string, prompt: string, fakten: string, presse?: bool}>
 */
function socialAnlaesse(): array
{
    return [
        // Standard
        'allgemein' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Allgemeiner Beitrag',
            'prompt' => 'Allgemeiner Vereins-/Event-Beitrag',
            'fakten' => "Marktlauf Kirchseeon · Sonntag, 20. September 2026 · Start ab 10:00 Uhr\nStart & Ziel: JEK, Westring 6, Kirchseeon\nLäufe: Bambini 500 m · Schüler 1 & 2 km · 5 km & 10 km\nAnmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'ankuendigung' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Ankündigung des Events',
            'prompt' => 'Ankündigung des Events (Vorschau, Aufruf zur Anmeldung)',
            'fakten' => "Marktlauf Kirchseeon · Sonntag, 20. September 2026 · Start ab 10:00 Uhr\nStart & Ziel: JEK, Westring 6, Kirchseeon\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)\nTeil des Energie- & Umwelttags 2026 · Medaillen & Urkunden für alle Finisher\nAnmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'countdown' => [
            'gruppe' => 'Standard',
            'ui'     => 'Countdown / Vorfreude',
            'prompt' => 'Countdown / Vorfreude vor dem Event',
            'fakten' => "Renntag: Sonntag, 20.09.2026, Start ab 10:00 Uhr\nAlle Distanzen: 500 m bis 10 km\nJetzt anmelden: atsv-kirchseeon-marktlauf.de",
        ],
        'sponsoren_dank' => [
            'gruppe' => 'Standard',
            'ui'     => 'Dank an Sponsoren & Partner',
            'prompt' => 'Dank an Sponsoren und Partner',
            'fakten' => "Dank an alle Sponsoren & Partner des Marktlaufs 2026\nSie machen Medaillen, Urkunden und Verpflegung möglich\n(konkrete Sponsorennamen hier einsetzen und im Bild taggen)",
        ],
        'helfer' => [
            'gruppe' => 'Standard',
            'ui'     => 'Helfer-Aufruf / -Dank',
            'prompt' => 'Helfer-Aufruf / Dank an Helfer',
            'fakten' => "Helfer für den Renntag 20.09.2026 gesucht\nEinsatzbereiche: Streckenposten, Start & Ziel, Verpflegung, Auf- & Abbau\nMelden über atsv-kirchseeon-marktlauf.de oder direkt bei der Orga",
        ],
        'renntag' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Renntag-Nachbericht (nutzt RaceResult-Daten)',
            'prompt' => 'Nachbericht zum Renntag (Ergebnisdaten siehe unten)',
            'fakten' => "(Ergebnisdaten kommen automatisch aus RaceResult)\nHier ergänzen: Wetter, Stimmung, besondere Momente, Dank an Helfer/Sponsoren",
        ],
        // Contentplan 2026
        'save_the_date' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Save the Date',
            'prompt' => 'Save the Date — Termin des Marktlaufs ankündigen, Vorfreude wecken',
            'fakten' => "Save the Date: Marktlauf Kirchseeon am Sonntag, 20. September 2026\nJEK, Westring 6, Kirchseeon · Start ab 10:00 Uhr\nLäufe für alle: Bambini bis 10 km",
        ],
        'warum_mitlaufen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Warum mitlaufen? (5 Gründe)',
            'prompt' => 'Warum mitlaufen? Fünf gute Gründe für die Teilnahme nennen',
            'fakten' => "Für alle Altersklassen: Bambini, Schüler, Jugend, Erwachsene\nDistanzen 500 m bis 10 km — jeder findet seine Strecke\nMedaillen & Urkunden für alle Finisher\nTeil des Energie- & Umwelttags: Sport und Umwelt an einem Tag\nLauffest mitten in Kirchseeon — Anmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'strecke' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Strecke entdecken',
            'prompt' => 'Strecke entdecken — Streckenverlauf und Besonderheiten vorstellen',
            'fakten' => "Start & Ziel: JEK, Westring 6, Kirchseeon\nStreckenverlauf & Karte: atsv-kirchseeon-marktlauf.de\n(Besonderheiten ergänzen: Runden, Untergrund, Highlights am Weg)",
        ],
        'nachhaltigkeit' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Nachhaltig laufen',
            'prompt' => 'Nachhaltig laufen — Umwelt- und Nachhaltigkeitsaspekte des Events',
            'fakten' => "Der Marktlauf ist Teil des Energie- & Umwelttags 2026\n(konkrete Punkte ergänzen: Anreise zu Fuß/Rad, regionale Verpflegung, Müllvermeidung)",
        ],
        'anmeldung_offen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Anmeldung geöffnet',
            'prompt' => 'Anmeldung geöffnet — Aufruf, sich jetzt anzumelden',
            'fakten' => "Die Anmeldung läuft: atsv-kirchseeon-marktlauf.de\nSonntag, 20.09.2026 · Start ab 10:00 Uhr · JEK, Westring 6, Kirchseeon\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)",
        ],
        'helfer_gesucht' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Helfer gesucht',
            'prompt' => 'Helfer gesucht — Aufruf, sich als Helfer für das Event zu melden',
            'fakten' => "Für den Renntag am Sonntag, 20.09.2026 suchen wir Helfer\nEinsatzbereiche: Streckenposten, Start & Ziel, Verpflegung, Auf- & Abbau\nSchichten am Renntag, Auf-/Abbau am Tag selbst\nMelden über atsv-kirchseeon-marktlauf.de oder direkt bei der Orga\nAls Dankeschön: gemeinsamer Ausklang nach dem Lauf",
        ],
        'sponsorenvorstellung' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Sponsorenvorstellung',
            'prompt' => 'Sponsorenvorstellung — einen Sponsor/Partner des Events vorstellen',
            'fakten' => "(Sponsor-Name, was er macht, was er beim Marktlauf unterstützt — hier einsetzen)\nDanke für die Unterstützung des Marktlaufs 2026\nTipp: Sponsor im Bild taggen oder als Instagram-Collab einladen",
        ],
        'countdown_30' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '30-Tage-Countdown',
            'prompt' => '30-Tage-Countdown — noch 30 Tage bis zum Event',
            'fakten' => "Noch 30 Tage bis zum Marktlauf: Sonntag, 20.09.2026, Start ab 10:00 Uhr\nJetzt anmelden: atsv-kirchseeon-marktlauf.de",
        ],
        'trainingstipp' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Trainingstipp',
            'prompt' => 'Trainingstipp für die Vorbereitung auf den Lauf',
            'fakten' => "(Konkreten Tipp einsetzen: z. B. Tempoläufe, langer Lauf am Wochenende, Regeneration)\nZiel: fit für 5 oder 10 km am 20.09.2026\nNoch nicht angemeldet? atsv-kirchseeon-marktlauf.de",
        ],
        'energie_umwelttag' => [
            'presse' => true,
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Energie- & Umwelttag',
            'prompt' => 'Energie- & Umwelttag — Hinweis auf den Aktionstag rund um das Event',
            'fakten' => "Der Marktlauf ist Teil des Energie- & Umwelttags 2026 in Kirchseeon\nSport + Umwelt an einem Tag — Programm rund um den Lauf\n(Programmpunkte des Aktionstags hier ergänzen)",
        ],
        'countdown_7' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '7-Tage-Countdown',
            'prompt' => '7-Tage-Countdown — noch eine Woche bis zum Event',
            'fakten' => "Noch 1 Woche: Marktlauf am Sonntag, 20.09.2026\nStart ab 10:00 Uhr · JEK, Westring 6, Kirchseeon\nLetzte Chance zur Anmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'morgen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Morgen geht\'s los',
            'prompt' => 'Morgen geht es los — letzter Aufruf am Vortag des Events',
            'fakten' => "Morgen ist Renntag: Sonntag, 20.09.2026\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)\nStart & Ziel: JEK, Westring 6, Kirchseeon\n(ergänzen: Startnummernausgabe, Parken, Nachmeldung möglich?)",
        ],
        'eventtag' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Eventtag (live)',
            'prompt' => 'Eventtag — Live-Beitrag vom Renntag selbst',
            'fakten' => "Heute läuft der Marktlauf Kirchseeon!\n(Live-Eindruck: Foto + 1–2 Sätze zur Stimmung vor Ort)\nErgebnisse später auf atsv-kirchseeon-marktlauf.de",
        ],
        'danke' => [
            'presse' => true,
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Danke / Rückblick',
            'prompt' => 'Danke & Rückblick nach dem Event',
            'fakten' => "Danke an alle Läuferinnen und Läufer, Helfer, Sponsoren und Zuschauer\n(Teilnehmerzahl/Highlights einsetzen — oder Anlass Renntag-Nachbericht mit RaceResult-Daten nutzen)\nErgebnisse: atsv-kirchseeon-marktlauf.de",
        ],
    ];
}
