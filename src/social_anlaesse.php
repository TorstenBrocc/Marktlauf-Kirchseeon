<?php
/**
 * Anlaesse/Themen der Social-Media-Strecke — EINE Quelle fuer Maske und APIs.
 * Fahrplan (orga/social_fahrplan.php) und Post-Detail (orga/social_post.php)
 * rendern hieraus, social_generate.php nimmt die Prompt-Beschreibung,
 * social_prompt.php validiert gegen die Keys, der Digest nimmt die UI-Labels.
 * 'presse' => true markiert pressefaehige Anlaesse (Presse-Feld + Presse-LLM-Call).
 * 'prompt' = Winkel als imperativer Prompt (Post-Wirkung-Spec 5.A/5.D) — kein Etikett,
 * sondern echte Anweisung an die KI, damit die Ausgabe nicht ausfranst.
 * 'ausschluss' = „darf NICHT rein" je Thema (5.D): wird von social_generate.php und
 * social_review.php als harte Negativ-Regel in den Prompt gehoben (Anti-Verwaesserung,
 * z. B. kein „Anmelden" auf Nachlauf-Themen).
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
 * Default-Text „Beste Sendezeiten" (Studien 2025/26) — editierbar über die Einstellungen
 * (Key `beste_sendezeiten`). Eine Zeile je Kanal/Regel; wird im Post-Detail (Thema-Kachel +
 * eigene Kachel) und in den Einstellungen gezeigt. Gespeicherter Wert gewinnt.
 */
function besteSendezeitenDefault(): string
{
    return "Instagram: Mi 12:00 · Do 8:30 · So 20:00\n"
        . "Facebook: Di 12:30 · (Test: Do 6:30)\n"
        . "Kernzeit: Di–Do, mittags 12–14 & abends 18–21 Uhr\n"
        . "Meiden: Fr-Abend, Samstag, nachts";
}

/** Gespeicherte „Beste Sendezeiten" lesen, sonst Default. Robust gegen fehlende Tabelle. */
function besteSendezeiten(PDO $pdo): string
{
    try {
        $stmt = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'beste_sendezeiten'");
        $val  = $stmt ? trim((string) ($stmt->fetchColumn() ?: '')) : '';
    } catch (PDOException $e) {
        $val = '';
    }
    return $val !== '' ? $val : besteSendezeitenDefault();
}

/**
 * Maschinenlesbare Best-Zeiten je Kanal × Wochentag (1=Mo … 7=So) → 'HH:MM'.
 * Grundlage für den „beste Sendezeit"-Timer (Spec social-auto-versand-beste-zeit-spec.md, E4).
 * Deckungsgleich mit dem Freitext-Default (besteSendezeitenDefault): IG Mi/Do/So, FB Di.
 * @return array<string, array<int, string>>
 */
function besteSendezeitenStrukturDefault(): array
{
    return [
        'instagram' => [3 => '12:00', 4 => '08:30', 7 => '20:00'],
        'facebook'  => [2 => '12:30'],
    ];
}

/** Gespeicherte Struktur (JSON, Key `beste_sendezeiten_struktur`) lesen, sonst Default. */
function besteSendezeitenStruktur(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'beste_sendezeiten_struktur'");
        $raw  = $stmt ? (string) ($stmt->fetchColumn() ?: '') : '';
    } catch (PDOException $e) {
        $raw = '';
    }
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    return is_array($decoded) && $decoded !== [] ? $decoded : besteSendezeitenStrukturDefault();
}

/** Bester Slot 'HH:MM' für Kanal + Wochentag (1=Mo … 7=So), sonst '' wenn keiner hinterlegt. */
function besteSlotFuer(array $struktur, string $kanal, int $wochentag): string
{
    $slot = $struktur[$kanal][$wochentag] ?? ($struktur[$kanal][(string) $wochentag] ?? '');
    return preg_match('/^\d{2}:\d{2}$/', (string) $slot) ? (string) $slot : '';
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
 * @return array<string, array{gruppe: string, ui: string, prompt: string, ausschluss: string, fakten: string, presse?: bool}>
 */
function socialAnlaesse(): array
{
    return [
        // Standard
        'allgemein' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Allgemeiner Beitrag',
            'prompt' => 'Vermittle einen herzlichen Gesamteindruck vom Marktlauf als Lauffest. Finde einen menschlichen Aufhänger, kein Datenblatt.',
            'ausschluss' => 'Keine Vollzähligkeit aller Startzeiten aufzählen. Keine Sponsoren-Liste.',
            'fakten' => "Marktlauf Kirchseeon · Sonntag, 20. September 2026 · Start ab 10:00 Uhr\nStart & Ziel: JEK, Westring 6, Kirchseeon\nLäufe: Bambini 500 m · Schüler 1 & 2 km · 5 km & 10 km\nAnmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'ankuendigung' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Ankündigung des Events',
            'prompt' => 'Kündige den Marktlauf an, wecke Vorfreude und rufe zur Anmeldung auf. Erwähne in einem Satz den Energie- & Umwelttag als Rahmen.',
            'ausschluss' => 'Nichts Rückblickartiges. Nicht überladen — nicht jedes Detail aufzählen.',
            'fakten' => "Marktlauf Kirchseeon · Sonntag, 20. September 2026 · Start ab 10:00 Uhr\nStart & Ziel: JEK, Westring 6, Kirchseeon\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)\nTeil des Energie- & Umwelttags 2026 · Medaillen & Urkunden für alle Finisher\nAnmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'countdown' => [
            'gruppe' => 'Standard',
            'ui'     => 'Countdown / Vorfreude',
            'prompt' => 'Schreibe einen kurzen Vorfreude-Post mit knackigem Hook und klarer Einladung zur Anmeldung.',
            'ausschluss' => 'Keine volle Distanz- oder Startzeitliste. Keine Sponsoren.',
            'fakten' => "Renntag: Sonntag, 20.09.2026, Start ab 10:00 Uhr\nAlle Distanzen: 500 m bis 10 km\nJetzt anmelden: atsv-kirchseeon-marktlauf.de",
        ],
        'sponsoren_dank' => [
            'gruppe' => 'Standard',
            'ui'     => 'Dank an Sponsoren & Partner',
            'prompt' => 'Bedanke dich einheitlich-warm bei Sponsoren und Partnern, ohne Stufen-Titel. Bring in einem emotionalen Satz rüber, was sie ermöglichen.',
            'ausschluss' => 'Keine Stufen-Titel (kein „Gold-/Silber-Sponsor"). Kein Anmelde-Hard-Sell. Nicht überladen.',
            'fakten' => "Dank an alle Sponsoren & Partner des Marktlaufs 2026\nSie machen Medaillen, Urkunden und Verpflegung möglich\n(konkrete Sponsorennamen hier einsetzen und im Bild taggen)",
        ],
        'helfer' => [
            'gruppe' => 'Standard',
            'ui'     => 'Helfer-Aufruf / -Dank',
            'prompt' => 'Sprich Helferinnen und Helfer direkt und ehrenamtsnah an — als Aufruf oder als Dank — mit einem konkreten nächsten Schritt.',
            'ausschluss' => 'Nicht mit der Läufer-Anmeldung vermischen. Keine Sponsoren.',
            'fakten' => "Helfer für den Renntag 20.09.2026 gesucht\nEinsatzbereiche: Streckenposten, Start & Ziel, Verpflegung, Auf- & Abbau\nMelden über atsv-kirchseeon-marktlauf.de oder direkt bei der Orga",
        ],
        'renntag' => [
            'presse' => true,
            'gruppe' => 'Standard',
            'ui'     => 'Renntag-Nachbericht (nutzt RaceResult-Daten)',
            'prompt' => 'Erzähl den Renntag lebendig — Stimmung, Wetter, Momente, Dank. Verweb die Ergebnisdaten als Erzählung, nicht als Tabelle.',
            'ausschluss' => 'Keine Anmeldung (Event ist gelaufen). Keine Countdown-Sprache. Keine Roh-Tabelle mit Zeiten.',
            'fakten' => "(Ergebnisdaten kommen automatisch aus RaceResult)\nHier ergänzen: Wetter, Stimmung, besondere Momente, Dank an Helfer/Sponsoren",
        ],
        // Contentplan 2026
        'save_the_date' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Save the Date',
            'prompt' => 'Save-the-Date: Bring die Leser dazu, sich den Termin zu merken. Wecke Vorfreude, halte es knapp und einladend.',
            'ausschluss' => 'Keine Detailzeiten. Kein Anmelde-Druck. Keine Sponsoren.',
            'fakten' => "Save the Date: Marktlauf Kirchseeon am Sonntag, 20. September 2026\nJEK, Westring 6, Kirchseeon · Start ab 10:00 Uhr\nLäufe für alle: Bambini bis 10 km",
        ],
        'warum_mitlaufen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Warum mitlaufen? (5 Gründe)',
            'prompt' => 'Nenne fünf gute Gründe fürs Mitlaufen — emotional erzählt, keine trockene Liste. Sprich alle Altersklassen und Familien an.',
            'ausschluss' => 'Kein Datenblatt. Keine Sponsoren.',
            'fakten' => "Für alle Altersklassen: Bambini, Schüler, Jugend, Erwachsene\nDistanzen 500 m bis 10 km — jeder findet seine Strecke\nMedaillen & Urkunden für alle Finisher\nTeil des Energie- & Umwelttags: Sport und Umwelt an einem Tag\nLauffest mitten in Kirchseeon — Anmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'strecke' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Strecke entdecken',
            'prompt' => 'Stell die Strecke vor — Verlauf und Besonderheiten — und mach Lust darauf, hier vor Ort zu laufen.',
            'ausschluss' => 'Keine Countdown- oder Ergebnis-Sprache. Keine Sponsoren.',
            'fakten' => "Start & Ziel: JEK, Westring 6, Kirchseeon\nStreckenverlauf & Karte: atsv-kirchseeon-marktlauf.de\n(Besonderheiten ergänzen: Runden, Untergrund, Highlights am Weg)",
        ],
        'nachhaltigkeit' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Nachhaltig laufen',
            'prompt' => 'Beleuchte die Nachhaltigkeits-Seite im Rahmen des Energie- & Umwelttags — glaubwürdig und konkret, ohne Greenwashing.',
            'ausschluss' => 'Keine Öko-Floskeln oder Superlative. Nichts Unbelegtes behaupten.',
            'fakten' => "Der Marktlauf ist Teil des Energie- & Umwelttags 2026\n(konkrete Punkte ergänzen: Anreise zu Fuß/Rad, regionale Verpflegung, Müllvermeidung)",
        ],
        'anmeldung_offen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Anmeldung geöffnet',
            'prompt' => 'Verkünde, dass die Anmeldung offen ist — mit einem klaren, freundlichen Aufruf.',
            'ausschluss' => 'Keine Rückblick- oder Countdown-Sprache. Keine Sponsoren.',
            'fakten' => "Die Anmeldung läuft: atsv-kirchseeon-marktlauf.de\nSonntag, 20.09.2026 · Start ab 10:00 Uhr · JEK, Westring 6, Kirchseeon\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)",
        ],
        'helfer_gesucht' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Helfer gesucht',
            'prompt' => 'Rufe gezielt Helfer auf — ehrenamtsnah und herzlich. Mach den nächsten Schritt leicht.',
            'ausschluss' => 'Nicht mit der Läufer-Anmeldung vermischen. Keine Sponsoren.',
            'fakten' => "Für den Renntag am Sonntag, 20.09.2026 suchen wir Helfer\nEinsatzbereiche: Streckenposten, Start & Ziel, Verpflegung, Auf-/Abbau\nMelden über QR-Code oder unser Kontaktformular: atsv-kirchseeon-marktlauf.de/#connect",
        ],
        'sponsorenvorstellung' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Sponsorenvorstellung',
            'prompt' => 'Stell EINEN Sponsor herzlich vor und stelle selbst den Bezug seiner Kernkompetenz zum Marktlauf her (z. B. „bringt die Brezn ins Ziel"). Einheitlich-warm, ohne Stufen-Titel.',
            'ausschluss' => 'Keine Stufen-Titel oder Paket-Sprache. Keine mehreren Sponsoren in einem Post.',
            'fakten' => "(Sponsor-Name, was er macht, was er beim Marktlauf unterstützt — hier einsetzen)\nDanke für die Unterstützung des Marktlaufs 2026\nTipp: Sponsor im Bild taggen oder als Instagram-Collab einladen",
        ],
        'countdown_30' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '30-Tage-Countdown',
            'prompt' => 'Noch 30 Tage: Transportiere Countdown-Energie und eine klare Einladung zur Anmeldung.',
            'ausschluss' => 'Keine Distanzliste. Keine Sponsoren.',
            'fakten' => "Noch 30 Tage bis zum Marktlauf: Sonntag, 20.09.2026, Start ab 10:00 Uhr\nJetzt anmelden: atsv-kirchseeon-marktlauf.de",
        ],
        'trainingstipp' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Trainingstipp',
            'prompt' => 'Gib einen konkreten, machbaren Trainingstipp — nahbar und ermutigend, für 5 oder 10 km.',
            'ausschluss' => 'Keine medizinischen Versprechen oder Superlative. Keine Sponsoren.',
            'fakten' => "(Konkreten Tipp einsetzen: z. B. Tempoläufe, langer Lauf am Wochenende, Regeneration)\nZiel: fit für 5 oder 10 km am 20.09.2026\nNoch nicht angemeldet? atsv-kirchseeon-marktlauf.de",
        ],
        'energie_umwelttag' => [
            'presse' => true,
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Energie- & Umwelttag',
            'prompt' => 'Weise auf den Energie- & Umwelttag als Rahmen hin — Sport und Umwelt an einem Tag — und nenne konkrete Programmpunkte.',
            'ausschluss' => 'Keine Superlative. Nicht ohne konkrete Programmpunkte posten.',
            'fakten' => "Der Marktlauf ist Teil des Energie- & Umwelttags 2026 in Kirchseeon\nSport + Umwelt an einem Tag — Programm rund um den Lauf\n(Programmpunkte des Aktionstags hier ergänzen)",
        ],
        'countdown_7' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '7-Tage-Countdown',
            'prompt' => 'Noch eine Woche: Mach die Vorfreude spürbar und lade ein letztes Mal zur Anmeldung ein.',
            'ausschluss' => 'Keine Detail-Distanzliste. Keine Sponsoren.',
            'fakten' => "Noch 1 Woche: Marktlauf am Sonntag, 20.09.2026\nStart ab 10:00 Uhr · JEK, Westring 6, Kirchseeon\nLetzte Chance zur Anmeldung: atsv-kirchseeon-marktlauf.de",
        ],
        'morgen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Morgen geht\'s los',
            'prompt' => 'Letzter Aufruf am Vortag: Vorfreude plus praktische Infos für morgen.',
            'ausschluss' => 'Nichts zu Parken oder Nachmeldung erfinden — nur wenn es in den Fakten steht. Keine Sponsoren.',
            'fakten' => "Morgen ist Renntag: Sonntag, 20.09.2026\nBambini 500 m (10:00) · Schüler 1 & 2 km (10:30) · 5 & 10 km (11:00)\nStart & Ziel: JEK, Westring 6, Kirchseeon\n(ergänzen: Startnummernausgabe, Parken, Nachmeldung möglich?)",
        ],
        'eventtag' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Eventtag (live)',
            'prompt' => 'Live vom Renntag: Fang einen echten Moment ein, kurze Stimmung — authentisch statt poliert.',
            'ausschluss' => 'Keine Anmeldung. Keine langen Texte. Kein Datenblatt.',
            'fakten' => "Heute läuft der Marktlauf Kirchseeon!\n(Live-Eindruck: Foto + 1–2 Sätze zur Stimmung vor Ort)\nErgebnisse später auf atsv-kirchseeon-marktlauf.de",
        ],
        'danke' => [
            'presse' => true,
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Danke / Rückblick',
            'prompt' => 'Sag Danke und blick warm zurück — Menschen vor Zahlen. Dank an Läufer, Helfer, Sponsoren und Zuschauer.',
            'ausschluss' => 'Keine Anmeldung (Event ist vorbei). Keine Countdown-Sprache.',
            'fakten' => "Danke an alle Läuferinnen und Läufer, Helfer, Sponsoren und Zuschauer\n(Teilnehmerzahl/Highlights einsetzen — oder Anlass Renntag-Nachbericht mit RaceResult-Daten nutzen)\nErgebnisse: atsv-kirchseeon-marktlauf.de",
        ],
    ];
}

/**
 * Leseranimation — vorgeschlagene Engagement-Fragen/CTAs je Anlass (Post-Wirkung-Spec,
 * „Leseranimation" 2026-08-27). Die gewaehlte Frage ist DER EINE Handlungsaufruf des Posts
 * (ersetzt den generischen CTA, steht nicht zusaetzlich daneben — „genau ein CTA"-Regel).
 * Der Orga-Board-Benutzer waehlt beim Erstellen im Post-Editor (social_post.php, Schritt 1);
 * Klick fuegt den Text am Cursor in die Caption ein. Wortlaut in Vereins-Stimme, zentral hier
 * pflegbar. Unbekannter/leerer Anlass faellt auf 'allgemein' zurueck.
 *
 * @return list<string>
 */
function socialCtaVorschlaege(string $anlassKey): array
{
    $vorschlaege = [
        'allgemein'            => ['Was verbindest du mit dem Marktlauf? 👇'],
        'ankuendigung'         => ['Bist du 2026 dabei? Sag\'s uns in den Kommentaren 👇', 'Wen nimmst du mit? Markier deine Laufpartner 👇'],
        'countdown'            => ['Wer freut sich schon? Reagier mit einem 🏃', 'Bist du schon angemeldet? 👇'],
        'sponsoren_dank'       => ['Kennt ihr unsere Partner? Ein 💚 für ihre Unterstützung', 'Sagt unseren Partnern kurz Danke in den Kommentaren 👇'],
        'helfer'               => ['Warst du schon mal im Helfer-Team? Erzähl\'s uns 👇', 'Ein 💪 für alle, die mit anpacken'],
        'renntag'              => ['Warst du dabei? Teil deinen schönsten Moment 👇', 'Markier jemanden, den du auf dem Bild entdeckst!'],
        'save_the_date'        => ['Termin schon im Kalender? Reagier mit 📅', 'Wer ist fest dabei? 👇'],
        'warum_mitlaufen'      => ['Was ist dein Grund zu laufen? 👇', 'Welcher Grund zieht bei dir am meisten? 👇'],
        'strecke'              => ['Welcher Streckenabschnitt ist dein Liebling? 👇', 'Kennst du die Strecke schon? 👇'],
        'nachhaltigkeit'       => ['Wie kommst du zum Lauf — Rad, zu Fuß, ÖPNV? 👇', 'Dein Tipp für mehr Nachhaltigkeit im Alltag? 👇'],
        'anmeldung_offen'      => ['Schon angemeldet? Markier deine Laufpartner 👇', 'Wer ist dieses Jahr am Start? 👇'],
        'helfer_gesucht'       => ['Wer ist dieses Jahr im Helfer-Team dabei? Meld dich 👇', 'Kennst du jemanden, der mit anpacken mag? Markier ihn 👇'],
        'sponsorenvorstellung' => ['Kennt ihr [Sponsor]? Sagt kurz Hallo 👇', 'Ein 💚 für die Unterstützung von [Sponsor]'],
        'countdown_30'         => ['Noch 30 Tage — wer trainiert schon? Reagier mit 💪', 'Wie sieht dein Trainingsplan aus? 👇'],
        'trainingstipp'        => ['Was ist dein bester Trainingstipp? Verrate ihn 👇', 'Was hilft dir kurz vorm Lauf am meisten? 👇'],
        'energie_umwelttag'    => ['Was tust du für mehr Nachhaltigkeit im Alltag? 👇', 'Kommst du klimafreundlich zum Lauf? Erzähl\'s uns 👇'],
        'countdown_7'          => ['Eine Woche noch! Wer ist angemeldet? 🏃', 'Bist du bereit? Reagier mit 💪'],
        'morgen'               => ['Bereit für morgen? Zeig deine Vorfreude 👇', 'Wer kann heute Nacht schon nicht schlafen vor Aufregung? 😄'],
        'eventtag'             => ['Du bist vor Ort? Zeig uns dein Marktlauf-Foto 📸', 'Wie ist die Stimmung bei dir? 👇'],
        'danke'                => ['Dein schönster Moment vom Marktlauf? Teil ihn 👇', 'Warst du dabei? Ein 💚 dalassen'],
    ];

    return $vorschlaege[$anlassKey] ?? $vorschlaege['allgemein'];
}
