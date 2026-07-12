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

// Optionaler Markdown-Parser (MIT, gepinnt 1.7.4). Fehlt die Datei, greift der
// projekteigene Mini-Konverter weiter unten – das Feature bleibt funktionsfähig.
if (is_file(__DIR__ . '/Parsedown.php')) {
    require_once __DIR__ . '/Parsedown.php';
}

/** Feste Event-Eckdaten (bei Jahres-/Terminwechsel hier anpassen). */
const SPONSOR_BRIEF_EVENT_DATUM = '20. September 2026';
const SPONSOR_BRIEF_ANTWORT_BIS = '30. August 2026';

/** Gültige Vorlagen-Slugs (= Anschreiben-Typen). */
function sponsorBriefSlugs(): array {
    return ['erstanschreiben', 'folgejahr', 'frei'];
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

{{paket_tabelle}}

{{paket_text}}

Sachsponsoring (z. B. Verpflegung, Preise für die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit möglich – einfach kurz melden.

**Rückmeldung erbeten bis zum {{antwort_bis}}** – so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.

Ich freue mich auf Ihre Rückmeldung und darauf, Sie am 20. September persönlich begrüßen zu dürfen.

{{signatur}}
MD;

    $folge = <<<MD
{{anrede}}

schön, dass {{firma}} beim 1. Marktlauf Kirchseeon dabei war – dafür noch einmal herzlichen Dank!

Am **{{event_datum}}** geht der **2. Marktlauf Kirchseeon** auf dem Westring an den Start – gemeinsam mit der Gemeinde im Rahmen des Energie- und Umwelttags, diesmal noch größer: **300 Läufer, rund 900 Gäste**. Wir würden uns sehr freuen, wenn Sie auch 2026 wieder mit an Bord wären.

Gerade als lokales Unternehmen sind Sie hier mittendrin statt nur dabei: Ihre Kundinnen und Kunden laufen, jubeln oder schauen direkt vor Ihrer Haustür zu. Ich würde mich sehr freuen, wenn Sie mit Ihrer Marke ein Teil davon sind.

{{paket_tabelle}}

{{paket_text}}

Sachsponsoring (z. B. Verpflegung, Preise für die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit möglich – einfach kurz melden.

**Rückmeldung erbeten bis zum {{antwort_bis}}** – so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.

Ich freue mich auf Ihre Rückmeldung und darauf, Sie am 20. September persönlich begrüßen zu dürfen.

{{signatur}}
MD;

    $frei = <<<MD
{{anrede}}

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
    ];
}

/** Liste der verfügbaren Platzhalter für die Editor-Referenz. */
function sponsorBriefPlatzhalterHilfe(): array {
    return [
        '{{anrede}}'        => 'Persönliche Anrede (Sehr geehrte Frau …)',
        '{{firma}}'         => 'Firmenname des Sponsors',
        '{{paket_text}}'    => 'Paketabhängiger Textbaustein (Gold/Silber/Bronze)',
        '{{paket_tabelle}}' => 'Tabelle aller Sponsoring-Pakete mit Preisen',
        '{{signatur}}'      => 'Signatur-Block (Name, Rolle, Kontakt)',
        '{{event_datum}}'   => 'Datum des Marktlaufs (' . SPONSOR_BRIEF_EVENT_DATUM . ')',
        '{{antwort_bis}}'   => 'Rückmeldefrist (' . SPONSOR_BRIEF_ANTWORT_BIS . ')',
    ];
}

/**
 * Vorlage laden: DB-Überschreibung über Default gemergt.
 * @return array{name:string, betreff:string, koerper_md:string}
 */
function sponsorBriefLoad(PDO $pdo, string $slug): array {
    $defaults = sponsorBriefDefaults();
    $base = $defaults[$slug] ?? $defaults['erstanschreiben'];

    try {
        $stmt = $pdo->prepare('SELECT name, betreff, koerper_md FROM sponsor_briefvorlagen WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row) {
            $betreff = trim((string) ($row['betreff'] ?? ''));
            $koerper = trim((string) ($row['koerper_md'] ?? ''));
            return [
                'name'       => (string) ($row['name'] ?? $base['name']),
                'betreff'    => $betreff !== '' ? $betreff : $base['betreff'],
                'koerper_md' => $koerper !== '' ? $koerper : $base['koerper_md'],
            ];
        }
    } catch (PDOException $e) {
        // Tabelle evtl. noch nicht migriert -> Default nutzen
    }

    return $base;
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

/** Paketabhängiger Textbaustein. */
function sponsorLevelText(string $paket): string {
    return match ($paket) {
        'gold', 'hauptsponsor' =>
            'Als führender regionaler Akteur würden wir uns besonders freuen, '
            . 'Sie als Gold-Sponsor auf unserer zentralen Bühne präsentieren zu dürfen.',
        'silber' =>
            'Mit einem Silber-Sponsoring sichern Sie sich hervorragende Sichtbarkeit '
            . 'direkt auf den Laufshirts und Startnummern unserer Teilnehmer.',
        default =>
            'Schon mit unserem Bronze-Paket leisten Sie einen wertvollen Beitrag '
            . 'für die Gemeinschaft und sind auf allen digitalen Kanälen präsent.',
    };
}

/**
 * Signatur-Block aus der Config (nicht im Repo — enthält Name/Telefon).
 * @return array{name:string, role:string, phone:string}
 */
function sponsorSignatur(): array {
    $cfg = getConfig()['sponsor_mail'] ?? [];
    return [
        'name'  => $cfg['sender_name'] ?? 'Orga-Team Marktlauf Kirchseeon',
        'role'  => $cfg['sender_role'] ?? 'Sponsoring · Marktlauf Kirchseeon, ATSV Kirchseeon e.V.',
        'phone' => $cfg['sender_phone'] ?? '',
    ];
}

function sponsorBriefPaketTabelleHtml(): string {
    return <<<HTML
<h3>Unsere Sponsoring-Pakete im Überblick:</h3>
<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <tr style="background-color: #f2f2f2;">
        <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Paket</th>
        <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Investition</th>
        <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Highlights</th>
    </tr>
    <tr>
        <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Bronze</strong></td>
        <td style="border: 1px solid #dddddd; padding: 8px;">250 &euro;</td>
        <td style="border: 1px solid #dddddd; padding: 8px;">Logo auf Website, Startet&uuml;ten-Branding, Urkunde, Dankesschreiben</td>
    </tr>
    <tr style="background-color: #fafafa;">
        <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Silber</strong></td>
        <td style="border: 1px solid #dddddd; padding: 8px;">500 &euro;</td>
        <td style="border: 1px solid #dddddd; padding: 8px;">+ Logo auf Startnummer &amp; Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startpl&auml;tze</td>
    </tr>
    <tr>
        <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Gold</strong></td>
        <td style="border: 1px solid #dddddd; padding: 8px;">1.000 &euro;</td>
        <td style="border: 1px solid #dddddd; padding: 8px;">+ Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fl&auml;che, 5 Startpl&auml;tze, Moderations-Erw&auml;hnungen</td>
    </tr>
</table>
HTML;
}

function sponsorBriefPaketTextListe(): string {
    return "Sponsoring-Pakete:\n"
        . "- Bronze (250 €): Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben\n"
        . "- Silber (500 €): + Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze\n"
        . "- Gold (1.000 €): + Banner zentral im Start-/Zielbereich, eigener Stand, 5 Startplätze, Moderations-Erwähnungen";
}

/**
 * Platzhalter-Kontext aufbauen.
 * @return array{inline:array<string,string>, blocksHtml:array<string,string>, blocksText:array<string,string>}
 */
function sponsorBriefContext(string $anrede, string $nachname, string $firma, string $paket): array {
    $firmaText = trim($firma) !== '' ? trim($firma) : 'Ihr Unternehmen';
    $sig = sponsorSignatur();

    $sigPhoneHtml = $sig['phone'] !== '' ? 'T: ' . htmlspecialchars($sig['phone']) . ' | ' : '';
    $sigHtml = '<p>Herzliche Grüße<br><br>'
        . '<strong>' . htmlspecialchars($sig['name']) . '</strong><br>'
        . htmlspecialchars($sig['role']) . '<br>'
        . $sigPhoneHtml . 'W: <a href="https://atsv-kirchseeon-marktlauf.de">atsv-kirchseeon-marktlauf.de</a></p>';
    $sigPhoneText = $sig['phone'] !== '' ? "T: {$sig['phone']} | " : '';
    $sigText = "Herzliche Grüße\n\n{$sig['name']}\n{$sig['role']}\n"
        . "{$sigPhoneText}W: atsv-kirchseeon-marktlauf.de";

    return [
        'inline' => [
            '{{anrede}}'      => sponsorAnrede($anrede, $nachname, $firma),
            '{{firma}}'       => $firmaText,
            '{{paket_text}}'  => sponsorLevelText($paket),
            '{{event_datum}}' => SPONSOR_BRIEF_EVENT_DATUM,
            '{{antwort_bis}}' => SPONSOR_BRIEF_ANTWORT_BIS,
        ],
        'blocksHtml' => [
            'paket_tabelle' => sponsorBriefPaketTabelleHtml(),
            'signatur'      => $sigHtml,
        ],
        'blocksText' => [
            'paket_tabelle' => sponsorBriefPaketTextListe(),
            'signatur'      => $sigText,
        ],
    ];
}

/** Beispiel-Kontext für die Editor-Vorschau (keine echten Empfängerdaten nötig). */
function sponsorBriefBeispielContext(): array {
    return sponsorBriefContext('Frau', 'Musterfrau', 'Muster GmbH', 'gold');
}

/* ---- Rendering ----------------------------------------------------------- */

/** Betreff mit Inline-Platzhaltern füllen (reiner Text). */
function sponsorBriefBetreff(string $betreff, array $ctx): string {
    return strtr($betreff, $ctx['inline']);
}

/** Markdown -> HTML (Parsedown wenn vorhanden, sonst sicherer Mini-Konverter). */
function sponsorMdToHtml(string $md): string {
    if (class_exists('Parsedown')) {
        $pd = new Parsedown();
        $pd->setSafeMode(true);       // filtert gefährliche URLs/Attribute
        $pd->setMarkupEscaped(true);  // getipptes Roh-HTML wird zu Text
        $pd->setBreaksEnabled(true);  // einfacher Zeilenumbruch => <br>
        return $pd->text($md);
    }
    return sponsorMiniMarkdown($md);
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
        if (preg_match('/^(#{1,6})\s+(.*)$/', $lines[0], $m)) {
            $level = strlen($m[1]);
            $out[] = "<h{$level}>" . sponsorMiniInline($m[2]) . "</h{$level}>";
            continue;
        }
        $out[] = '<p>' . implode('<br>', array_map('sponsorMiniInline', $lines)) . '</p>';
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
    $md = strtr($md, $ctx['inline']);
    // Block-Platzhalter durch Tokens ersetzen, die das Markdown-Rendering überleben.
    foreach (array_keys($ctx['blocksHtml']) as $name) {
        $md = str_replace('{{' . $name . '}}', "%%BLOCK_{$name}%%", $md);
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
    return sponsorMdToText(strtr($md, $map));
}
