<?php
/**
 * Sponsoren-Briefvorlagen: Defaults, Laden, Platzhalter und Markdown-Rendering.
 *
 * Trennung der Zuständigkeiten:
 *   - Der Standardtext der drei Vorlagen lebt hier (sponsorBriefDefaults) als
 *     einzige Quelle der Wahrheit. Die DB-Tabelle sponsor_briefvorlagen hält nur
 *     Überschreibungen; ein leerer Text fällt automatisch auf den Default zurück.
 *   - Dynamische Bestandteile (Anrede, Firma, Paket-Tabelle, Signatur, Termine)
 *     werden über Platzhalter {{...}} eingesetzt, NICHT vom Bearbeiter getippt.
 *
 * Sicherheit: Vom Bearbeiter getippter Markdown wird immer HTML-escaped gerendert
 * (Parsedown SafeMode + MarkupEscaped bzw. der Fallback-Konverter). Vertrauens-
 * würdiges HTML (Paket-Tabelle, Signatur) wird ausschließlich serverseitig über
 * Block-Platzhalter NACH dem Rendern eingesetzt. Ein eingetipptes <script> landet
 * damit als sichtbarer Text in der Mail, wird aber nie ausgeführt.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sponsor_leistungen.php';

// Optionaler Markdown-Parser (MIT, gepinnt 1.7.4). Fehlt die Datei, greift der
// projekteigene Mini-Konverter weiter unten – das Feature bleibt funktionsfähig.
if (is_file(__DIR__ . '/Parsedown.php')) {
    require_once __DIR__ . '/Parsedown.php';
}

/** Datum von Y-m-d in deutsches Format (z. B. "20. September 2026"). */
function sponsorFormatDatum(string $ymd, string $fallback): string {
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if (!$d) return $fallback;
    static $months = ['Januar','Februar','März','April','Mai','Juni',
                      'Juli','August','September','Oktober','November','Dezember'];
    return (int)$d->format('j') . '. ' . $months[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

/** Gültige Vorlagen-Slugs (= Anschreiben-Typen). */
function sponsorBriefSlugs(): array {
    return ['erstanschreiben', 'folgejahr', 'frei', 'bestaetigung', 'rechnung', 'bedingungen'];
}

function sponsorBriefSlugValid(string $slug): bool {
    return in_array($slug, sponsorBriefSlugs(), true);
}

/**
 * Standard-Vorlagen (Betreff + Markdown-Körper). Einzige Quelle der Wahrheit.
 * @return array<string, array{name:string, betreff:string, koerper_md:string}>
 */
function sponsorBriefDefaults(): array {
    $erst = <<<MD
{{anrede}}

wir machen es wieder – und diesmal noch größer.

Am **{{event_datum}}** startet der **2. Marktlauf Kirchseeon** auf dem Westring, gemeinsam mit der Gemeinde Kirchseeon im Rahmen des Energie- und Umwelttags. Beim ersten Marktlauf 2025 haben wir gezeigt, was in Kirchseeon steckt. 2026 wollen wir das ausbauen: **300 Läufer, rund 900 Gäste** – Familien, Sportler, Nachbarinnen und Nachbarn aus der ganzen Region.

Gerade als lokales Unternehmen sind Sie hier mittendrin statt nur dabei: Ihre Kundinnen und Kunden laufen, jubeln oder schauen direkt vor Ihrer Haustür zu. Ich würde mich sehr freuen, wenn Sie mit Ihrer Marke ein Teil davon sind.

### Unsere Sponsoring-Pakete im Überblick:

{{paket_tabelle}}

{{paket_text}}

Sachsponsoring (z. B. Verpflegung, Preise für die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit möglich – einfach kurz melden.

Grundlage einer möglichen Zusammenarbeit sind unsere beiliegenden Sponsoring-Bedingungen (Anhang).

**Rückmeldung erbeten bis zum {{antwort_bis}}** – so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.

Ich freue mich auf Ihre Rückmeldung und darauf, Sie am 20. September persönlich begrüßen zu dürfen.

Herzliche Grüße

{{signatur}}
MD;

    $folge = <<<MD
{{anrede}}

schön, dass {{firma}} beim 1. Marktlauf Kirchseeon dabei war – dafür noch einmal herzlichen Dank!

Am **{{event_datum}}** geht der **2. Marktlauf Kirchseeon** auf dem Westring an den Start – gemeinsam mit der Gemeinde im Rahmen des Energie- und Umwelttags, diesmal noch größer: **300 Läufer, rund 900 Gäste**. Wir würden uns sehr freuen, wenn Sie auch 2026 wieder mit an Bord wären.

Gerade als lokales Unternehmen sind Sie hier mittendrin statt nur dabei: Ihre Kundinnen und Kunden laufen, jubeln oder schauen direkt vor Ihrer Haustür zu. Ich würde mich sehr freuen, wenn Sie mit Ihrer Marke ein Teil davon sind.

### Unsere Sponsoring-Pakete im Überblick:

{{paket_tabelle}}

{{paket_text}}

Sachsponsoring (z. B. Verpflegung, Preise für die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit möglich – einfach kurz melden.

Grundlage einer möglichen Zusammenarbeit sind unsere beiliegenden Sponsoring-Bedingungen (Anhang).

**Rückmeldung erbeten bis zum {{antwort_bis}}** – so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.

Ich freue mich auf Ihre Rückmeldung und darauf, Sie am 20. September persönlich begrüßen zu dürfen.

Herzliche Grüße

{{signatur}}
MD;

    $frei = <<<MD
{{anrede}}

Herzliche Grüße

{{signatur}}
MD;

    $rechnung = <<<MD
{{anrede}}

vielen Dank für Ihre Unterstützung des Marktlaufs Kirchseeon. Anbei erhalten Sie die Rechnung Nr. **{{rechnungsnummer}}** über **{{betrag}}** zu Ihrem Sponsoring ({{leistung}}).

Über einen Ausgleich auf die auf der Rechnung genannte Bankverbindung innerhalb von 14 Tagen freuen wir uns.

Es gelten unsere beiliegenden Sponsoring-Bedingungen; mit Begleichung der Rechnung gelten diese als vereinbart.

Mit sportlichen Grüßen
ATSV Kirchseeon e.V. – Abteilung Marktlauf
MD;

    $bedingungen = <<<MD
{{anrede}}

vielen Dank für Ihre Unterstützung des Marktlaufs Kirchseeon! Wir haben unsere Sponsoring-Bedingungen formalisiert – Sie finden sie im Anhang.

Bitte bestätigen Sie uns kurz per Antwort auf diese E-Mail, dass Sie mit den beiliegenden Bedingungen einverstanden sind.

Vielen Dank und herzliche Grüße

{{signatur}}
MD;

    return [
        'erstanschreiben' => [
            'name'       => 'Erstanschreiben',
            'betreff'    => 'Gemeinsam für Kirchseeon: Sponsoring-Chance für {{firma}}',
            'koerper_md' => $erst,
        ],
        'folgejahr' => [
            'name'       => 'Folgejahr / Bestandssponsor',
            'betreff'    => 'Auch 2026 wieder dabei? Marktlauf Kirchseeon – {{firma}}',
            'koerper_md' => $folge,
        ],
        'frei' => [
            'name'       => 'Freier Brief',
            'betreff'    => 'Marktlauf Kirchseeon – {{firma}}',
            'koerper_md' => $frei,
        ],
        'rechnung' => [
            'name'       => 'Rechnungs-Begleitmail',
            'betreff'    => 'Ihre Sponsoring-Rechnung – Marktlauf Kirchseeon',
            'koerper_md' => $rechnung,
        ],
        'bedingungen' => [
            'name'       => 'Sponsoring-Bedingungen nachreichen',
            'betreff'    => 'Unsere Sponsoring-Bedingungen – Marktlauf Kirchseeon, {{firma}}',
            'koerper_md' => $bedingungen,
        ],
        'bestaetigung' => [
            'name'       => 'Bestätigung Sponsoring',
            'betreff'    => 'Herzlichen Dank und nächste Schritte – Marktlauf Kirchseeon, {{firma}}',
            'koerper_md' => <<<MD
{{anrede}}

herzlichen Dank, dass Sie den Marktlauf Kirchseeon am **{{event_datum}}** als **{{paket_text}}** unterstützen. Wir freuen uns sehr über Ihre Zusage und die Zusammenarbeit!

Damit wir Ihren Markenauftritt optimal vorbereiten können, bitten wir Sie, uns folgende Unterlagen und Informationen zukommen zu lassen:

**1. Logo & Platzierungen**

- Bitte senden Sie uns Ihr Logo in allen Auflösungen für Web (bevorzugt SVG) und Druck.
- Für die Website-Verlinkung benötigen wir den gewünschten Ziel-Link.
- Haben Sie konkrete Vorstellungen zur Platzierung? Aktuell vorgesehen: Plakat und Startnummern.
- Haben Sie Flyer oder Give-aways, die wir auslegen oder in den Startetüten verteilen dürfen?

**2. Banner / Hussen**

Für unsere Absperrgitter empfehlen wir **Hussen** statt klassischer Banner – geringerer Aufwand, kein Kabelbinder-Abfall nach dem Event. Die Bemaßungen finden Sie im Anhang.

Lieferadresse:

ATSV Kirchseeon
c/o ORGA Marktlauf, z. Hd. Frau Jenny Fischer
Sportplatzweg 1
85614 Kirchseeon

**3. Digitale Vernetzung**

Unsere Social-Media-Auftritte sind auf [atsv-kirchseeon-marktlauf.de](https://atsv-kirchseeon-marktlauf.de) im Footer verlinkt. Wie möchten Sie digital vernetzt werden? Gibt es Kanäle oder Links, die wir besonders hervorheben sollen?

**4. Ablauf am Renntag**

Wie und wo möchten Sie sich am Renntag aufbauen? Zu welcher Zeit sollen wir mit Ihnen rechnen?

**5. Nachlauf & Social Media**

Wie soll der Nachlauf gestaltet werden? Benötigen Sie von uns Fotos, Logos oder Ergebnis-Highlights für Ihre Social-Media-Kanäle?

**6. Freie Startplätze**

Gutschein laut Paket {{startplaetze}}x frei verwendbar: {{gutscheincode}}

Bitte bei der Registrierung gern bei Verein {{firma}} (bei gebräuchlichen Kürzeln gern auch dieses) mit angeben, dann können wir sogar eine Gruppenauswertung am Ende machen, wenn gewünscht.

**7. Plakate**

zum Aushängen/Weiterleiten anbei

**8. Rechnungsanschrift**

Damit wir Ihnen die Rechnung korrekt ausstellen können, benötigen wir Ihre vollständige Rechnungsadresse sowie alle für die Buchhaltung notwendigen Informationen (z. B. Ansprechpartner Buchhaltung).

Sollte Ihnen etwas fehlen oder Sie noch Fragen haben, kommen Sie jederzeit gerne auf mich zu.

Vielen Dank für Ihre Unterstützung und Ihr Vertrauen – gemeinsam machen wir den Marktlauf Kirchseeon zu einem unvergesslichen Erlebnis!

Grundlage unserer Zusammenarbeit sind die beiliegenden Sponsoring-Bedingungen. Bitte geben Sie uns dazu eine kurze positive Rückmeldung – damit gelten sie als vereinbart.

Herzliche Grüße

{{signatur}}
MD,
        ],
    ];
}

/**
 * Die 7 optionalen Abschnitte der Sponsoring-Bestätigung für den Bausteine-Selektor.
 * @return array<int,array{id:string,titel:string,checked:bool,text:string}>
 */
function sponsorBestaetigungSektionen(): array {
    return [
        [
            'id'      => 's1',
            'titel'   => '1. Logo & Platzierungen',
            'checked' => true,
            'text'    => "**1. Logo & Platzierungen**\n\n"
                       . "- Bitte senden Sie uns Ihr Logo in allen Auflösungen für Web (bevorzugt SVG) und Druck.\n"
                       . "- Für die Website-Verlinkung benötigen wir den gewünschten Ziel-Link.\n"
                       . "- Haben Sie konkrete Vorstellungen zur Platzierung? Aktuell vorgesehen: Plakat und Startnummern.\n"
                       . "- Haben Sie Flyer oder Give-aways, die wir auslegen oder in den Startetüten verteilen dürfen?",
        ],
        [
            'id'      => 's2',
            'titel'   => '2. Banner / Hussen',
            'checked' => true,
            'text'    => "**2. Banner / Hussen**\n\n"
                       . "Für unsere Absperrgitter empfehlen wir **Hussen** statt klassischer Banner – "
                       . "geringerer Aufwand, kein Kabelbinder-Abfall nach dem Event. "
                       . "Die Bemaßungen finden Sie im Anhang.\n\n"
                       . "Lieferadresse:\n\n"
                       . "ATSV Kirchseeon  \n"
                       . "c/o ORGA Marktlauf, z. Hd. Frau Jenny Fischer  \n"
                       . "Sportplatzweg 1  \n"
                       . "85614 Kirchseeon",
        ],
        [
            'id'      => 's3',
            'titel'   => '3. Digitale Vernetzung',
            'checked' => true,
            'text'    => "**3. Digitale Vernetzung**\n\n"
                       . "Unsere Social-Media-Auftritte sind auf [atsv-kirchseeon-marktlauf.de](https://atsv-kirchseeon-marktlauf.de) "
                       . "im Footer verlinkt. Wie möchten Sie digital vernetzt werden? "
                       . "Gibt es Kanäle oder Links, die wir besonders hervorheben sollen?",
        ],
        [
            'id'      => 's4',
            'titel'   => '4. Ablauf am Renntag',
            'checked' => true,
            'text'    => "**4. Ablauf am Renntag**\n\n"
                       . "Wie und wo möchten Sie sich am Renntag aufbauen? Zu welcher Zeit sollen wir mit Ihnen rechnen?",
        ],
        [
            'id'      => 's5',
            'titel'   => '5. Nachlauf & Social Media',
            'checked' => true,
            'text'    => "**5. Nachlauf & Social Media**\n\n"
                       . "Wie soll der Nachlauf gestaltet werden? Benötigen Sie von uns Fotos, Logos oder "
                       . "Ergebnis-Highlights für Ihre Social-Media-Kanäle?",
        ],
        [
            'id'      => 's6',
            'titel'   => '6. Freie Startplätze',
            'checked' => true,
            'text'    => "**6. Freie Startplätze**\n\n"
                       . "Gutschein laut Paket {{startplaetze}}x frei verwendbar: {{gutscheincode}}\n\n"
                       . "Bitte bei der Registrierung gern bei Verein {{firma}} (bei gebräuchlichen "
                       . "Kürzeln gern auch dieses) mit angeben, dann können wir sogar eine "
                       . "Gruppenauswertung am Ende machen, wenn gewünscht.",
        ],
        [
            'id'      => 's7',
            'titel'   => '7. Plakate',
            'checked' => true,
            'text'    => "**7. Plakate**\n\nzum Aushängen/Weiterleiten anbei",
        ],
        [
            'id'      => 's8',
            'titel'   => '8. Rechnungsanschrift',
            'checked' => true,
            'text'    => "**8. Rechnungsanschrift**\n\n"
                       . "Damit wir Ihnen die Rechnung korrekt ausstellen können, benötigen wir Ihre vollständige "
                       . "Rechnungsadresse sowie alle für die Buchhaltung notwendigen Informationen "
                       . "(z. B. Ansprechpartner Buchhaltung).",
        ],
    ];
}

/** Liste der verfügbaren Platzhalter für die Editor-Referenz. */
function sponsorBriefPlatzhalterHilfe(string $slug = ''): array {
    if ($slug === 'rechnung') {
        return [
            '{{anrede}}'          => 'Anrede (bei Rechnungen fest: „Sehr geehrte Damen und Herren,")',
            '{{firma}}'           => 'Firmenname des Sponsors (Rechnungsempfänger)',
            '{{rechnungsnummer}}' => 'Fortlaufende Rechnungsnummer (Format NN-JJJJ)',
            '{{betrag}}'          => 'Rechnungsbetrag brutto, z. B. 1.190,00 €',
            '{{netto}}'           => 'Nettobetrag',
            '{{leistung}}'        => 'Leistung/Paket, z. B. Gold-Sponsoring Marktlauf 2026',
            '{{zeitraum}}'        => 'Leistungszeitraum',
        ];
    }
    return [
        '{{anrede}}'        => "Persönliche Anrede – wird automatisch generiert:\n"
                               . "• Frau + Nachname → \"Sehr geehrte Frau Jost,\"\n"
                               . "• Herr + Nachname → \"Sehr geehrter Herr Müller,\"\n"
                               . "• kein Nachname + Firma → \"Sehr geehrte Damen und Herren der Muster GmbH,\"\n"
                               . "• sonst → \"Sehr geehrte Damen und Herren,\"",
        '{{vorname}}'       => 'Vorname des Ansprechpartners',
        '{{firma}}'         => 'Firmenname des Sponsors',
        '{{paket_text}}'    => 'Paketname (Hauptsponsor / Gold-Sponsor / Silber-Sponsor / Bronze-Sponsor / Sachsponsor)',
        '{{paket_tabelle}}' => 'Tabelle aller Sponsoring-Pakete mit Preisen und Highlights',
        '{{signatur}}'      => "Signatur-Block (Name, Aufgabe, Telefon, E-Mail, Social-Media-Logos)\n"
                               . "Die persönlichen Daten stammen aus der Benutzerverwaltung (dein Profil).\n"
                               . "Enthält KEINE Grußformel – die schreibst du frei in den Text darüber.",
        '{{event_datum}}'   => 'Datum des Marktlaufs (aus Einstellungen)',
        '{{antwort_bis}}'   => 'Rückmeldefrist (aus Einstellungen)',
        '{{startplaetze}}'  => "Anzahl der freien Startplätze laut Paket (Bronze 1 / Silber 3 / Gold 5).\n"
                               . "Quelle: Leistungs-Katalog – dieselbe Zahl, die die Leistungs-Matrix anzeigt.\n"
                               . "Leer beim Sachsponsor (keine Startplätze) und beim Hauptsponsor\n"
                               . "(individuelle Menge, in der Matrix „indiv.“) – dann selbst eintragen.",
        '{{gutscheincode}}' => "Gutscheincode für die freien Startplätze.\n"
                               . "Quelle: Leistungs-Matrix, Zeile des Sponsors, Spalte „Startplätze“.\n"
                               . "Leer, solange dort nichts hinterlegt ist – dann in der Matrix nachtragen\n"
                               . "oder den Satz von Hand schreiben.",
    ];
}

/**
 * Vorlage laden: eigener Draft → DB-Master → Code-Default.
 * $userId = 0 überspringt die Draft-Prüfung (Preview-APIs, CLI).
 * @return array{name:string, betreff:string, koerper_md:string, draft:bool, draft_ts:string}
 */
function sponsorBriefLoad(PDO $pdo, string $slug, int $userId = 0): array {
    $defaults = sponsorBriefDefaults();
    $base = $defaults[$slug] ?? $defaults['erstanschreiben'];

    $master = $base;
    try {
        $stmt = $pdo->prepare('SELECT name, betreff, koerper_md FROM sponsor_briefvorlagen WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row) {
            $betreff = trim((string) ($row['betreff'] ?? ''));
            $koerper = trim((string) ($row['koerper_md'] ?? ''));
            $master = [
                'name'       => (string) ($row['name'] ?? $base['name']),
                'betreff'    => $betreff !== '' ? $betreff : $base['betreff'],
                'koerper_md' => $koerper !== '' ? $koerper : $base['koerper_md'],
            ];
        }
    } catch (PDOException $e) {
        // Tabelle evtl. noch nicht migriert -> Default nutzen
    }

    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT betreff, koerper_md, gespeichert_am FROM briefvorlagen_entwurf WHERE user_id = :uid AND vorlage_art = :art AND slug = :slug');
            $stmt->execute(['uid' => $userId, 'art' => 'sponsor', 'slug' => $slug]);
            $draft = $stmt->fetch();
            if ($draft) {
                $dBetreff = trim((string) ($draft['betreff'] ?? ''));
                $dKoerper = trim((string) ($draft['koerper_md'] ?? ''));
                return [
                    'name'       => $master['name'],
                    'betreff'    => $dBetreff !== '' ? $dBetreff : $master['betreff'],
                    'koerper_md' => $dKoerper !== '' ? $dKoerper : $master['koerper_md'],
                    'draft'      => true,
                    'draft_ts'   => (string) $draft['gespeichert_am'],
                ];
            }
        } catch (PDOException $e) {
            // Entwurf-Tabelle noch nicht migriert -> ignorieren
        }
    }

    return array_merge($master, ['draft' => false, 'draft_ts' => '']);
}

/* ---- Platzhalter-Kontext (aus Empfängerdaten) ---------------------------- */

/** Persönliche Anrede mit kaskadierendem Fallback. */
function sponsorAnrede(string $anrede, string $nachname, string $firma = ''): string {
    $nachname = trim($nachname);
    if ($nachname !== '' && $anrede === 'Frau') {
        return "Sehr geehrte Frau {$nachname},";
    }
    if ($nachname !== '' && $anrede === 'Herr') {
        return "Sehr geehrter Herr {$nachname},";
    }
    $firma = trim($firma);
    if ($firma !== '') {
        return "Sehr geehrte Damen und Herren der {$firma},";
    }
    return 'Sehr geehrte Damen und Herren,';
}

/** Paketname. */
function sponsorLevelText(string $paket): string {
    return match ($paket) {
        'hauptsponsor' => 'Hauptsponsor',
        'gold'         => 'Gold-Sponsor',
        'silber'       => 'Silber-Sponsor',
        'sachsponsor'  => 'Sachsponsor',
        default        => 'Bronze-Sponsor',
    };
}

/**
 * Formatierungs-Legende für die Brief-Editoren (Sponsoren + Vereine).
 *
 * Bewusst hier im Code neben dem Renderer und nicht als Text in den beiden
 * Seiten: eine handgeschriebene Legende läuft irgendwann von dem auseinander,
 * was sponsorMiniMarkdown/sponsorMiniInline tatsächlich beherrschen — und eine
 * falsche Legende ist schlechter als keine. Wer den Konverter erweitert, ändert
 * die Liste hier gleich mit.
 *
 * Der Abschnitt "geht nicht" ist kein Beiwerk: numerierte Listen und Tabellen
 * probieren Leute erfahrungsgemäß zuerst, und beide erscheinen still als
 * roher Text statt als Formatierung.
 */
function sponsorMarkdownLegende(): string
{
    $kann = [
        ['**fett**',                    '<strong>fett</strong>'],
        ['*kursiv*',                    '<em>kursiv</em>'],
        ['## Große Überschrift',        '<span style="font-size:1.15em;font-weight:700">Große Überschrift</span>'],
        ['### Mittlere Überschrift',    '<span style="font-weight:700;color:#009640">Mittlere Überschrift</span>'],
        ['#### Kleine Überschrift',     '<span style="font-weight:700">Kleine Überschrift</span>'],
        ['- Punkt (oder * Punkt)',      'Aufzählung mit Punkten'],
        ['[Marktlauf](https://…)',      '<span style="text-decoration:underline;color:#009640">Marktlauf</span> als Link'],
        ['Leerzeile dazwischen',        'neuer Absatz'],
    ];
    $kannNicht = [
        'Numerierte Listen (<code>1.</code>) — erscheinen als normaler Text',
        'Tabellen, Bilder, Zitate (<code>&gt;</code>) und Code (<code>`</code>)',
        'Getipptes HTML wie <code>&lt;b&gt;</code> — wird als Text angezeigt, nie ausgeführt',
    ];

    $zeilen = '';
    foreach ($kann as [$syntax, $ergebnis]) {
        $zeilen .= '<tr>'
            . '<td class="md-syntax"><code>' . htmlspecialchars($syntax) . '</code></td>'
            . '<td class="md-ergebnis">' . $ergebnis . '</td>'
            . '</tr>';
    }
    $nicht = '';
    foreach ($kannNicht as $eintrag) {
        $nicht .= '<li>' . $eintrag . '</li>';
    }

    return '<details class="md-legende">'
        . '<summary>Formatierung</summary>'
        . '<div class="md-legende-body">'
        . '<table class="md-tabelle"><tbody>' . $zeilen . '</tbody></table>'
        . '<p class="md-hinweis"><strong>Zwei Fallstricke:</strong> Eine Aufzählung braucht eine '
        . 'Leerzeile davor, sonst wird sie Teil des Absatzes. Und <code>{{…}}</code>-Platzhalter '
        . 'bitte unverändert stehen lassen — die werden beim Versand ersetzt.</p>'
        . '<p class="md-hinweis"><em>Geht nicht:</em></p><ul class="md-nicht">' . $nicht . '</ul>'
        . '</div></details>';
}

/**
 * Social-Media-Block für alle Anschreiben-Signaturen — eine Quelle für
 * Sponsoren- und Vereins-Briefe (verein_brief.php lädt diese Datei).
 *
 * Layout wie im Website-Footer: Beschriftung plus die drei Marken-Kacheln
 * (46px, radius 12px, weißes Glyph — layout.css:319ff), hier auf 24px
 * skaliert und der Link direkt hinter dem Logo, ohne Text daneben.
 *
 * Bewusst <img> statt inline SVG: Gmail und Outlook entfernen <svg> aus
 * E-Mails. Deshalb auch width/height als Attribute (Outlook ignoriert CSS)
 * und ein sprechendes alt, damit bei blockierten Bildern der Netzwerkname
 * lesbar bleibt. Die Text-Fassung trägt die URLs ausgeschrieben, denn ohne
 * SMTP fällt sendMail() auf text/plain zurück (channels/mail.php:58).
 */
function marktlaufSocialLinks(): array {
    $base = 'https://atsv-kirchseeon-marktlauf.de';
    $netze = [
        ['Instagram', 'https://www.instagram.com/atsv_marktlauf_kirchseeon',   'instagram.png'],
        ['Facebook',  'https://www.facebook.com/profile.php?id=61591689790244', 'facebook.png'],
        ['Strava',    'https://www.strava.com/clubs/2252807',                  'strava.png'],
    ];

    $icons = '';
    $zeilen = [];
    foreach ($netze as [$label, $url, $datei]) {
        $icons .= '<a href="' . htmlspecialchars($url) . '" title="' . $label . '"'
            . ' style="text-decoration:none;margin-right:6px">'
            . '<img src="' . $base . '/assets/images/social-media/mail/' . $datei . '"'
            . ' alt="' . $label . '" width="24" height="24"'
            . ' style="width:24px;height:24px;border:0;border-radius:6px;vertical-align:middle">'
            . '</a>';
        $zeilen[] = $label . ': ' . $url;
    }

    return [
        'html' => '<span style="vertical-align:middle">Folge uns:</span> ' . $icons,
        'text' => "Folge uns:\n" . implode("\n", $zeilen),
    ];
}

function sponsorSignatur(PDO $pdo, int $userId): array {
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT name, email, telefon, aufgabe FROM users WHERE id = :id AND active = 1');
            $stmt->execute(['id' => $userId]);
            $u = $stmt->fetch();
            if ($u) {
                return [
                    'name'  => (string) $u['name'],
                    'role'  => (string) ($u['aufgabe'] ?? ''),
                    'phone' => (string) ($u['telefon'] ?? ''),
                    'email' => (string) $u['email'],
                ];
            }
        } catch (PDOException $e) {}
    }
    $cfg = getConfig()['sponsor_mail'] ?? [];
    return [
        'name'  => $cfg['sender_name']  ?? 'Orga-Team Marktlauf Kirchseeon',
        'role'  => $cfg['sender_role']  ?? 'Sponsoring · Marktlauf Kirchseeon, ATSV Kirchseeon e.V.',
        'phone' => $cfg['sender_phone'] ?? '',
        'email' => $cfg['smtp_from']    ?? '',
    ];
}

/** @return array<int,array{key:string,name:string,investition:string,highlights:string}> */
function sponsorBriefPaketeDefault(): array {
    return [
        ['key'=>'hauptsponsor','name'=>'Hauptsponsor','investition'=>'auf Anfrage',
         'highlights'=>'Zentraler Partner des Events, maximale Sichtbarkeit auf allen Kanälen'],
        ['key'=>'gold','name'=>'Gold','investition'=>'1.000 €',
         'highlights'=>'Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fläche, 5 Startplätze, Moderations-Erwähnungen'],
        ['key'=>'silber','name'=>'Silber','investition'=>'500 €',
         'highlights'=>'Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze'],
        ['key'=>'bronze','name'=>'Bronze','investition'=>'250 €',
         'highlights'=>'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben, 1 Startplatz'],
    ];
}

function sponsorBriefPaketeAusDb(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'sponsoring_pakete'");
        $stmt->execute();
        $json = $stmt->fetchColumn();
        if ($json) {
            $data = json_decode((string) $json, true);
            if (is_array($data) && count($data) > 0) return $data;
        }
    } catch (PDOException $e) {}
    return sponsorBriefPaketeDefault();
}

function sponsorBriefPaketTabelleHtml(PDO $pdo): string {
    $pakete = sponsorBriefPaketeAusDb($pdo);
    $rows = '';
    foreach ($pakete as $i => $p) {
        $bg = $i % 2 !== 0 ? ' style="background-color: #fafafa;"' : '';
        $rows .= '<tr' . $bg . '>'
            . '<td style="border: 1px solid #dddddd; padding: 8px;"><strong>' . htmlspecialchars((string) ($p['name'] ?? '')) . '</strong></td>'
            . '<td style="border: 1px solid #dddddd; padding: 8px;">' . htmlspecialchars((string) ($p['investition'] ?? '')) . '</td>'
            . '<td style="border: 1px solid #dddddd; padding: 8px;">' . htmlspecialchars((string) ($p['highlights'] ?? '')) . '</td>'
            . '</tr>';
    }
    // Bewusst ohne eigene Überschrift: die stand hier fest verdrahtet und war
    // im Editor unerreichbar — zusammen mit der Tabellenkopfzeile ergab das
    // zwei Titelzeilen in Folge. Der Block liefert jetzt nur noch Daten, die
    // Überschrift steht als "### …" in der Vorlage und ist damit editierbar.
    return '<table style="width: 100%; border-collapse: collapse; margin: 20px 0 6px;">'
        . '<tr style="background-color: #f2f2f2;">'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Paket</th>'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Investition</th>'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Highlights</th>'
        . '</tr>' . $rows . '</table>'
        . '<p style="font-size: 12px; color: #666666; margin: 0 0 20px;">'
        . 'Alle Paketpreise verstehen sich netto zzgl. der gesetzlichen Umsatzsteuer (19&nbsp;%).'
        . '</p>';
}

function sponsorBriefPaketTextListe(PDO $pdo): string {
    // Ebenfalls ohne Überschrift — die kommt als "### …" aus der Vorlage und
    // landet über sponsorMdToText auch im Text-Teil. Sonst stünde sie doppelt.
    $pakete = sponsorBriefPaketeAusDb($pdo);
    $lines = '';
    foreach ($pakete as $p) {
        $lines .= '- ' . ($p['name'] ?? '') . ' (' . ($p['investition'] ?? '') . '): ' . ($p['highlights'] ?? '') . "\n";
    }
    return rtrim($lines);
}

/**
 * Platzhalter-Kontext aufbauen.
 *
 * $sponsorId ist optional (0 = kein konkreter Sponsor, z. B. Editor-Vorschau): nur sponsor-
 * bezogene Platzhalter wie {{gutscheincode}} brauchen ihn. Versand, Beleg-PDF und die
 * Bestätigungs-Seite reichen ihn durch, damit alle drei dieselben Werte sehen.
 *
 * @return array{inline:array<string,string>, blocksHtml:array<string,string>, blocksText:array<string,string>}
 */
function sponsorBriefContext(PDO $pdo, int $userId, string $anrede, string $vorname, string $nachname, string $firma, string $paket, int $sponsorId = 0): array {
    $firmaText  = trim($firma) !== '' ? trim($firma) : 'Ihr Unternehmen';
    $sig        = sponsorSignatur($pdo, $userId);
    // Freie Startplätze: Stückzahl aus dem Paket (Katalog), Code aus der Leistungs-Matrix.
    $startplaetze = sponsorStartplaetzeAnzahl($paket !== '' ? $paket : null);
    $eventDatum = '20. September 2026';
    $antwortBis = '30. August 2026';
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('sponsor_brief_event_datum','sponsor_brief_antwort_bis')");
        foreach ($stmt->fetchAll() as $row) {
            if ($row['key'] === 'sponsor_brief_event_datum' && (string) $row['value'] !== '') {
                $eventDatum = sponsorFormatDatum((string) $row['value'], $eventDatum);
            } elseif ($row['key'] === 'sponsor_brief_antwort_bis' && (string) $row['value'] !== '') {
                $antwortBis = sponsorFormatDatum((string) $row['value'], $antwortBis);
            }
        }
    } catch (PDOException $e) {}

    $sigRoleHtml  = $sig['role']  !== '' ? htmlspecialchars($sig['role'])  . '<br>' : '';
    $sigPhoneHtml = $sig['phone'] !== '' ? 'T: ' . htmlspecialchars($sig['phone']) : '';
    $sigEmailHtml = $sig['email'] !== '' ? ($sigPhoneHtml !== '' ? ' | ' : '') . 'M: <a href="mailto:' . htmlspecialchars($sig['email']) . '">' . htmlspecialchars($sig['email']) . '</a>' : '';
    // Ohne Grußformel: die schreibt der Absender frei in den Brieftext.
    $social  = marktlaufSocialLinks();
    $sigHtml = '<p>'
        . '<strong>' . htmlspecialchars($sig['name']) . '</strong><br>'
        . $sigRoleHtml
        . ($sigPhoneHtml . $sigEmailHtml !== '' ? $sigPhoneHtml . $sigEmailHtml . '<br>' : '')
        . 'W: <a href="https://atsv-kirchseeon-marktlauf.de">atsv-kirchseeon-marktlauf.de</a><br><br>'
        . $social['html'] . '</p>';

    $sigParts = [];
    if ($sig['phone'] !== '') $sigParts[] = 'T: ' . $sig['phone'];
    if ($sig['email'] !== '') $sigParts[] = 'M: ' . $sig['email'];
    $sigParts[] = 'W: atsv-kirchseeon-marktlauf.de';
    $sigRoleText = $sig['role'] !== '' ? $sig['role'] . "\n" : '';
    $sigText = "{$sig['name']}\n{$sigRoleText}" . implode(' | ', $sigParts)
        . "\n\n" . $social['text'];

    return [
        'inline' => [
            '{{anrede}}'      => sponsorAnrede($anrede, $nachname, $firma),
            '{{vorname}}'     => trim($vorname),
            '{{firma}}'       => $firmaText,
            '{{paket_text}}'  => sponsorLevelText($paket),
            '{{event_datum}}' => $eventDatum,
            '{{antwort_bis}}' => $antwortBis,
            '{{startplaetze}}'  => $startplaetze !== null ? (string) $startplaetze : '',
            '{{gutscheincode}}' => sponsorGutscheincode($pdo, $sponsorId),
        ],
        'blocksHtml' => [
            'paket_tabelle' => sponsorBriefPaketTabelleHtml($pdo),
            'signatur'      => $sigHtml,
        ],
        'blocksText' => [
            'paket_tabelle' => sponsorBriefPaketTextListe($pdo),
            'signatur'      => $sigText,
        ],
    ];
}

/** Beispiel-Kontext für die Editor-Vorschau (keine echten Empfängerdaten nötig). */
function sponsorBriefBeispielContext(PDO $pdo, int $userId = 0): array {
    return sponsorBriefContext($pdo, $userId, 'Frau', 'Erika', 'Musterfrau', 'Muster GmbH', 'gold');
}

/* ---- Rechnungs-Begleitmail: eigener (schlanker) Kontext ------------------ */

/** Inline-Platzhalter aus einer Rechnungs-Zeile (sponsor_rechnungen-Snapshot). */
function rechnungMailInlineFromRow(array $r): array {
    $eur = static fn ($v): string => number_format((float) $v, 2, ',', '.') . ' €';
    $leistung = trim((string) ($r['leistung'] ?? ''));
    $pos = strpos($leistung, ':');
    if ($pos !== false) {
        $leistung = trim(substr($leistung, 0, $pos)); // nur die Bezeichnung, ohne Detailtext
    }
    return [
        '{{anrede}}'          => 'Sehr geehrte Damen und Herren,',
        '{{firma}}'           => (string) ($r['empfaenger_firma'] ?? ''),
        '{{rechnungsnummer}}' => (string) ($r['rechnungsnummer'] ?? ''),
        '{{betrag}}'          => $eur($r['brutto'] ?? 0),
        '{{netto}}'           => $eur($r['netto'] ?? 0),
        '{{leistung}}'        => $leistung,
        '{{zeitraum}}'        => (string) ($r['zeitraum'] ?? ''),
    ];
}

/** Kontext für die Rechnungs-Begleitmail (Blöcke leer — Template hat feste Grußformel). */
function rechnungMailContext(array $r): array {
    return [
        'inline'     => rechnungMailInlineFromRow($r),
        'blocksHtml' => [],
        'blocksText' => [],
    ];
}

/** Beispiel-Kontext für die Editor-Vorschau der Rechnungs-Begleitmail. */
function rechnungMailBeispielContext(): array {
    return rechnungMailContext([
        'empfaenger_firma' => 'Muster GmbH',
        'rechnungsnummer'  => '05-2026',
        'brutto'           => 1190.00,
        'netto'            => 1000.00,
        'leistung'         => 'Gold-Sponsoring Marktlauf 2026',
        'zeitraum'         => 'Marktlauf 2026',
    ]);
}

/* ---- Rendering ----------------------------------------------------------- */

/**
 * Platzhalter case-insensitiv ersetzen: {{Vorname}}, {{ VORNAME }} und {{vorname}}
 * treffen alle denselben Wert. Unbekannte Platzhalter bleiben unverändert stehen
 * (z. B. Block-Platzhalter, die separat behandelt werden). Ersetzt das früher
 * genutzte strtr, das case-sensitiv war und getippte Groß-/Kleinschreibung nicht traf.
 *
 * @param array<string,string> $map  Schlüssel wie '{{vorname}}' => Wert
 */
function sponsorApplyInline(string $s, array $map): string {
    $lookup = [];
    foreach ($map as $token => $value) {
        $lookup[strtolower(trim($token, '{} '))] = $value;
    }
    return preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
        static fn (array $m): string => $lookup[strtolower($m[1])] ?? $m[0],
        $s
    );
}

/** Betreff mit Inline-Platzhaltern füllen (reiner Text). */
function sponsorBriefBetreff(string $betreff, array $ctx): string {
    return sponsorApplyInline($betreff, $ctx['inline']);
}

/** Markdown -> HTML (Parsedown wenn vorhanden, sonst sicherer Mini-Konverter). */
function sponsorMdToHtml(string $md): string {
    if (class_exists('Parsedown')) {
        $pd = new Parsedown();
        $pd->setSafeMode(true);       // filtert gefährliche URLs/Attribute
        $pd->setMarkupEscaped(true);  // getipptes Roh-HTML wird zu Text
        $pd->setBreaksEnabled(true);  // einfacher Zeilenumbruch => <br>
        return sponsorStyleHeadings($pd->text($md));
    }
    return sponsorStyleHeadings(sponsorMiniMarkdown($md));
}

/**
 * Gibt <h1>–<h6> aus dem Markdown-Rendering feste Inline-Styles.
 *
 * Nötig, weil Mail-Clients nackte Überschriften unterschiedlich groß setzen
 * und Outlook eigene Abstände erfindet. Bewusst hier und nicht im Konverter,
 * damit beide Rendering-Wege (Parsedown und Mini-Konverter) dasselbe Ergebnis
 * liefern. Die vertrauenswürdigen Blöcke (Tabelle, Signatur) werden erst
 * danach eingesetzt und sind deshalb nicht betroffen.
 *
 * Drei nutzbare Ebenen: ## groß, ### mittel (grün, die Stufe über der
 * Paket-Tabelle), #### klein.
 */
function sponsorStyleHeadings(string $html): string {
    $styles = [
        1 => 'font-size:22px;line-height:1.25;font-weight:700;color:#1a1a1a;margin:24px 0 10px',
        2 => 'font-size:19px;line-height:1.30;font-weight:700;color:#1a1a1a;margin:22px 0 9px',
        3 => 'font-size:16px;line-height:1.35;font-weight:700;color:#009640;margin:20px 0 8px',
        4 => 'font-size:14px;line-height:1.40;font-weight:700;color:#1a1a1a;margin:18px 0 6px',
    ];
    return (string) preg_replace_callback(
        '~<h([1-6])>~',
        static function (array $m) use ($styles): string {
            $level = (int) $m[1];
            return '<h' . $level . ' style="' . ($styles[$level] ?? $styles[4]) . '">';
        },
        $html
    );
}

/** Minimaler, abhängigkeitsfreier Markdown->HTML-Fallback (immer HTML-escaped). */
function sponsorMiniMarkdown(string $md): string {
    $blocks = preg_split('/\R{2,}/', trim($md));
    $out = [];
    foreach ($blocks as $block) {
        $lines = preg_split('/\R/', trim($block));
        $isList = true;
        foreach ($lines as $l) {
            if (!preg_match('/^\s*[-*]\s+/', $l)) { $isList = false; break; }
        }
        if ($isList && count($lines) > 0) {
            $items = '';
            foreach ($lines as $l) {
                $items .= '<li>' . sponsorMiniInline(preg_replace('/^\s*[-*]\s+/', '', $l)) . '</li>';
            }
            $out[] = '<ul>' . $items . '</ul>';
            continue;
        }
        // Überschriften zeilenweise behandeln. Vorher wurde nur die erste Zeile
        // eines Blocks geprüft und mit "continue" alles danach verworfen — wer
        // unter eine Überschrift ohne Leerzeile weiterschrieb, verlor diesen
        // Text stillschweigend, auch in der Vorschau.
        $para = [];
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                if ($para !== []) {
                    $out[] = '<p>' . implode('<br>', array_map('sponsorMiniInline', $para)) . '</p>';
                    $para = [];
                }
                $level = strlen($m[1]);
                $out[] = "<h{$level}>" . sponsorMiniInline($m[2]) . "</h{$level}>";
                continue;
            }
            $para[] = $line;
        }
        if ($para !== []) {
            $out[] = '<p>' . implode('<br>', array_map('sponsorMiniInline', $para)) . '</p>';
        }
    }
    return implode("\n", $out);
}

/** Inline-Formatierung für den Fallback (escaped zuerst, dann fett/kursiv/Links). */
function sponsorMiniInline(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $s);
    $s = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', static function ($m) {
        return '<a href="' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '">' . $m[1] . '</a>';
    }, $s);
    return $s;
}

/** Markdown -> reiner Text (Syntax entfernt) für den Plaintext-Teil der Mail. */
function sponsorMdToText(string $md): string {
    $t = preg_replace('/^#{1,6}\s*/m', '', $md);
    $t = preg_replace('/\*\*(.+?)\*\*/s', '$1', $t);
    $t = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '$1', $t);
    $t = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', static fn ($m) => $m[1] . ' (' . $m[2] . ')', $t);
    return trim((string) $t);
}

/**
 * Vollständigen HTML-Body rendern: Inline-Platzhalter einsetzen, Markdown escaped
 * rendern, danach die vertrauenswürdigen HTML-Blöcke (Tabelle, Signatur) einsetzen.
 */
function sponsorBriefRenderHtml(string $md, array $ctx): string {
    $md = sponsorApplyInline($md, $ctx['inline']);
    // Block-Platzhalter durch Tokens ersetzen, die das Markdown-Rendering überleben.
    foreach (array_keys($ctx['blocksHtml']) as $name) {
        $md = str_ireplace('{{' . $name . '}}', "%%BLOCK_{$name}%%", $md);
    }
    $html = sponsorMdToHtml($md);
    foreach ($ctx['blocksHtml'] as $name => $blockHtml) {
        $html = str_replace('<p>%%BLOCK_' . $name . '%%</p>', $blockHtml, $html);
        $html = str_replace('%%BLOCK_' . $name . '%%', $blockHtml, $html);
    }

    return "<html>\n<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333333;\">\n"
        . $html
        . "\n</body>\n</html>";
}

/** Plaintext-Body rendern: alle Platzhalter (Text-Varianten) einsetzen, Syntax entfernen. */
function sponsorBriefRenderText(string $md, array $ctx): string {
    $map = $ctx['inline'];
    foreach ($ctx['blocksText'] as $name => $blockText) {
        $map['{{' . $name . '}}'] = $blockText;
    }
    return sponsorMdToText(sponsorApplyInline($md, $map));
}
