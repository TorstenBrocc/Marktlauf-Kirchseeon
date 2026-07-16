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
        '{{anrede}}'        => "Persönliche Anrede – wird automatisch generiert:\n"
                               . "• Frau + Nachname → \"Sehr geehrte Frau Jost,\"\n"
                               . "• Herr + Nachname → \"Sehr geehrter Herr Müller,\"\n"
                               . "• kein Nachname + Firma → \"Sehr geehrte Damen und Herren der Muster GmbH,\"\n"
                               . "• sonst → \"Sehr geehrte Damen und Herren,\"",
        '{{vorname}}'       => 'Vorname des Ansprechpartners',
        '{{firma}}'         => 'Firmenname des Sponsors',
        '{{paket_text}}'    => 'Paketname (Hauptsponsor / Gold-Sponsor / Silber-Sponsor / Bronze-Sponsor)',
        '{{paket_tabelle}}' => 'Tabelle aller Sponsoring-Pakete mit Preisen und Highlights',
        '{{signatur}}'      => 'Signatur-Block (Name, Aufgabe, Telefon, E-Mail)',
        '{{event_datum}}'   => 'Datum des Marktlaufs (aus Einstellungen)',
        '{{antwort_bis}}'   => 'Rückmeldefrist (aus Einstellungen)',
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

/** Paketname. */
function sponsorLevelText(string $paket): string {
    return match ($paket) {
        'hauptsponsor' => 'Hauptsponsor',
        'gold'         => 'Gold-Sponsor',
        'silber'       => 'Silber-Sponsor',
        default        => 'Bronze-Sponsor',
    };
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
         'highlights'=>'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben'],
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
    return '<h3>Unsere Sponsoring-Pakete im Überblick:</h3>'
        . '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">'
        . '<tr style="background-color: #f2f2f2;">'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Paket</th>'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Investition</th>'
        . '<th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Highlights</th>'
        . '</tr>' . $rows . '</table>';
}

function sponsorBriefPaketTextListe(PDO $pdo): string {
    $pakete = sponsorBriefPaketeAusDb($pdo);
    $lines = "Sponsoring-Pakete:\n";
    foreach ($pakete as $p) {
        $lines .= '- ' . ($p['name'] ?? '') . ' (' . ($p['investition'] ?? '') . '): ' . ($p['highlights'] ?? '') . "\n";
    }
    return rtrim($lines);
}

/**
 * Platzhalter-Kontext aufbauen.
 * @return array{inline:array<string,string>, blocksHtml:array<string,string>, blocksText:array<string,string>}
 */
function sponsorBriefContext(PDO $pdo, int $userId, string $anrede, string $vorname, string $nachname, string $firma, string $paket): array {
    $firmaText  = trim($firma) !== '' ? trim($firma) : 'Ihr Unternehmen';
    $sig        = sponsorSignatur($pdo, $userId);
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
    $sigHtml = '<p>Herzliche Grüße<br><br>'
        . '<strong>' . htmlspecialchars($sig['name']) . '</strong><br>'
        . $sigRoleHtml
        . ($sigPhoneHtml . $sigEmailHtml !== '' ? $sigPhoneHtml . $sigEmailHtml . '<br>' : '')
        . 'W: <a href="https://atsv-kirchseeon-marktlauf.de">atsv-kirchseeon-marktlauf.de</a></p>';

    $sigParts = [];
    if ($sig['phone'] !== '') $sigParts[] = 'T: ' . $sig['phone'];
    if ($sig['email'] !== '') $sigParts[] = 'M: ' . $sig['email'];
    $sigParts[] = 'W: atsv-kirchseeon-marktlauf.de';
    $sigRoleText = $sig['role'] !== '' ? $sig['role'] . "\n" : '';
    $sigText = "Herzliche Grüße\n\n{$sig['name']}\n{$sigRoleText}" . implode(' | ', $sigParts);

    return [
        'inline' => [
            '{{anrede}}'      => sponsorAnrede($anrede, $nachname, $firma),
            '{{vorname}}'     => trim($vorname),
            '{{firma}}'       => $firmaText,
            '{{paket_text}}'  => sponsorLevelText($paket),
            '{{event_datum}}' => $eventDatum,
            '{{antwort_bis}}' => $antwortBis,
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
