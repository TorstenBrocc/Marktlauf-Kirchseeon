<?php
/**
 * Design-System-Browser — navigierbare, lebende Referenz.
 *
 * Menü links / Inhalt rechts (kein flacher Scroll). Alle Werte kommen zur Laufzeit
 * aus der gemeinsamen Quelle src/design_tokens.php (→ css/base.css + orga/css/orga.css),
 * damit dieselben Tokens später auch die Generatoren speisen. Vanilla — kein Framework,
 * kein CDN, keine externen Ressourcen (Fonts self-hosted via css/fonts.css).
 *
 * Spec: intern/design-system-integration-spec.md (E1 Vanilla, E2 kanonische Quelle).
 * Komponenten-/Template-Sektion folgt als Inc 2 (self-hosted React im iframe).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/design_tokens.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

$tokens = ds_load_tokens();          // list of [name,value,kind,source,file]
$map    = ds_token_map();            // name => value
$tokensRead = $tokens !== [];

// Tokens auf Sektionen verteilen.
$sections = ['colors' => [], 'spacing' => [], 'type' => [], 'elevation' => []];
foreach ($tokens as $t) {
    switch ($t['kind']) {
        case 'color':
        case 'alias':  $sections['colors'][]    = $t; break;
        case 'font':
        case 'size':   $sections['type'][]       = $t; break;
        case 'radius':
        case 'shadow': $sections['elevation'][]  = $t; break;
        case 'space':
        default:       $sections['spacing'][]     = $t; break; // 'other' (Maße) hier mit
    }
}

// Snippets — statische, self-contained HTML-Bloecke aus dem DS-Paket (Inc 2),
// oeffentlich ausgeliefert unter website/design-system/snippets/. Keine externen Refs.
$snippetDir   = __DIR__ . '/../design-system/snippets';
$snippetItems = [
    ['preview' => 'live-ticker.html',         'copy' => 'live-ticker.snippet.html',      'name' => 'Live-Ticker',          'sub' => 'Meldeband über dem Header (3 Meldungstypen)'],
    // Newsletter-Master: Code aus der EINEN kanonischen Quelle (kein Snippet-Duplikat mehr),
    // {{token:--x}} serverseitig zu E-Mail-Hex aufgelöst — identisch zu dem, was der Generator liefert.
    ['preview' => 'newsletter-mail.html',      'canonical' => __DIR__ . '/../src/newsletter/03_html_master_template.md', 'name' => 'Newsletter-Master',    'sub' => 'HTML-Grundgerüst für Mailings (kanonische Quelle)'],
    ['preview' => 'newsletter-beispiel.html',  'copy' => 'newsletter-beispiel.html',       'name' => 'Newsletter-Beispiel',  'sub' => 'Ausgefülltes Beispiel-Mailing'],
    ['preview' => 'raceresult-infotext.html',  'copy' => 'raceresult-infotext.html',       'name' => 'RaceResult Info-Text', 'sub' => 'HTML-Block für das INFO-Feld'],
];
// Nur real vorhandene Snippets zeigen; Kopier-Inhalt serverseitig laden. Der Newsletter-Master
// kommt aus der kanonischen Vorlage (Anti-Drift), die übrigen aus dem Snippet-Ordner.
$snippetItems = array_values(array_filter(array_map(static function (array $s) use ($snippetDir, $map): ?array {
    if (!is_file($snippetDir . '/' . $s['preview'])) {
        return null;
    }
    if (isset($s['canonical'])) {
        $raw = @file_get_contents($s['canonical']);
        // {{token:--x}} -> Hex (wie orga/api/newsletter_generate.php); {{TITLE}}/{{CONTENT}}
        // bleiben als sichtbare Platzhalter stehen (echte Vorlagen-Referenz).
        $s['code'] = $raw !== false
            ? preg_replace_callback('/\{\{token:(--[\w-]+)\}\}/', static fn (array $m): string => $map[$m[1]] ?? $m[0], $raw)
            : '';
    } else {
        $raw = @file_get_contents($snippetDir . '/' . $s['copy']);
        $s['code'] = $raw !== false ? $raw : '';
    }
    return $s;
}, $snippetItems)));

// Guidelines — kuratierte Doku-Karten aus dem Paket (Brand/Colors/Type/Spacing),
// auf die kanonische Token-Quelle (../design-system/tokens.php) + self-hosted Fonts
// umgebogen. Gruppierung/Titel aus dem @dsCard-Kommentar im Dateikopf.
$guidelineDir    = __DIR__ . '/../design-system/guidelines';
$guidelineGroups = [];
foreach (glob($guidelineDir . '/*.html') ?: [] as $path) {
    $head = (string) @file_get_contents($path, false, null, 0, 500);
    preg_match('/group="([^"]*)"/', $head, $g);
    preg_match('/name="([^"]*)"/', $head, $n);
    preg_match('/subtitle="([^"]*)"/', $head, $sub);
    $group = ($g[1] ?? '') !== '' ? $g[1] : 'Weitere';
    $guidelineGroups[$group][] = [
        'file' => basename($path),
        'name' => ($n[1] ?? '') !== '' ? $n[1] : basename($path, '.html'),
        'sub'  => $sub[1] ?? '',
    ];
}
ksort($guidelineGroups);
$guidelineCount = array_sum(array_map('count', $guidelineGroups));

// Komponenten — die React-Demos des Pakets, vorab (lokal) zu plain JS kompiliert und
// auf self-hosted React umgebogen (kein CDN, kein Babel-Runtime). Laufen interaktiv im
// iframe. Titel/Untertitel aus dem Build-Meta (_meta.json).
$componentDir  = __DIR__ . '/../design-system/components';
$componentMeta = [];
if (is_file($componentDir . '/_meta.json')) {
    $componentMeta = json_decode((string) @file_get_contents($componentDir . '/_meta.json'), true) ?: [];
}
$componentItems = [];
foreach (glob($componentDir . '/*.html') ?: [] as $path) {
    $key = basename($path, '.html');
    $componentItems[] = [
        'file' => basename($path),
        'name' => $componentMeta[$key]['name'] ?? $key,
        'sub'  => $componentMeta[$key]['sub'] ?? '',
    ];
}

// Templates — Vorschau (captured WebP-Thumbnails) je Vorlage. Die interaktive Erzeugung
// lebt in den jeweiligen Generatoren (Social/Plakat/Newsletter), nicht hier — deshalb
// reicht das Vorschaubild. sponsoren-rechnung bewusst NICHT enthalten (echte Bankdaten, privat).
$templateDir  = __DIR__ . '/../design-system/templates';
$templateItems = array_values(array_filter([
    ['thumb' => 'event-website.webp',    'name' => 'Event-Website-Seite',  'sub' => 'Öffentliche Seite im Marktlauf-Look: Verlaufs-Hero, Abschnitte, Karten, Footer.'],
    ['thumb' => 'orga-seite.webp',       'name' => 'Orga-Dashboard-Seite', 'sub' => 'Interne Verwaltungsseite: Seitenleiste, Kacheln, Filterleiste, Datentabelle.'],
    ['thumb' => 'plakat.webp',           'name' => 'Plakat',               'sub' => 'Druckfertiges A3-Plakat, Variante Hauptplakat oder Schulplakat.'],
    ['thumb' => 'raceresult-cover.webp', 'name' => 'RaceResult-Cover',     'sub' => 'Cover-Grafiken für die RaceResult-Anmeldeseite (mobil + Desktop).'],
    ['thumb' => 'social-post.webp',      'name' => 'Social-Media-Post',    'sub' => 'Instagram-Formate 1:1, 4:5 und 9:16 in zwei Marken-Varianten.'],
], static fn (array $t): bool => is_file($templateDir . '/' . $t['thumb'])));

// Readme — die Fließtext-Einleitung des DS-Pakets (design-system/readme.md), serverseitig
// zu HTML gerendert. Überschriften werden um eine Ebene abgesenkt (# → h2), damit die
// Seiten-<h1> eindeutig bleibt (SEO). Kein CDN, keine externen Ressourcen.
$readmePath = __DIR__ . '/../design-system/readme.md';
$readmeHtml = is_file($readmePath)
    ? ds_render_markdown((string) @file_get_contents($readmePath), 1)
    : '';

// Tonalität & Voice — die EINE Marken-Stimme (src/brand/voice.md), die alle Text-Generatoren
// speist. Injektions-Marker (<!-- voice:… -->) fürs Rendern entfernen. Spec: social-ds-voice-wp-spec.md.
$voicePath = __DIR__ . '/../src/brand/voice.md';
$voiceHtml = is_file($voicePath)
    ? ds_render_markdown((string) preg_replace('/<!--.*?-->/s', '', (string) @file_get_contents($voicePath)), 1)
    : '';

// Hero-Verlauf aus Einzeltokens zusammensetzen (falls vorhanden).
$gradient = null;
if (isset($map['--hero-gradient-start'], $map['--hero-gradient-mid'], $map['--hero-gradient-end'])) {
    $gradient = sprintf(
        'linear-gradient(120deg, %s 0%%, %s 55%%, %s 100%%)',
        $map['--hero-gradient-start'], $map['--hero-gradient-mid'], $map['--hero-gradient-end']
    );
}

// Marken-Kernwerte (base bevorzugt, orga als Fallback).
$primary = $map['--color-primary'] ?? $map['--primary'] ?? '#009640';
$accent  = $map['--color-accent']  ?? $map['--accent']  ?? '#ff6b35';

/** Menü der Sektionen: key => Anzeigename. */
$menu = [
    'readme'     => 'Readme',
    'voice'      => 'Tonalität',
    'brand'      => 'Marke',
    'colors'     => 'Farben',
    'spacing'    => 'Abstände & Maße',
    'type'       => 'Typografie',
    'elevation'  => 'Radius & Schatten',
    'guidelines' => 'Guidelines',
    'snippets'   => 'Snippets',
    'components' => 'Komponenten',
    'templates'  => 'Templates',
];
$defaultSection = 'readme';

/** Herkunfts-Badge (Website/Marke vs. Dashboard). */
function ds_src_badge(string $source): string
{
    $label = DS_SOURCE_LABEL[$source] ?? $source;
    return '<span class="ds-src ds-src--' . htmlspecialchars($source) . '">'
         . htmlspecialchars($label) . '</span>';
}

/** Eine Token-Kachel rendern (Vorschau + Name/Wert + Herkunft, Klick = kopieren). */
function ds_card(array $t, array $map): string
{
    $name    = htmlspecialchars($t['name']);
    $val     = htmlspecialchars($t['value']);
    $varCopy = htmlspecialchars('var(' . $t['name'] . ')');

    $meta = '<div class="ds-meta">'
          . '<span class="ds-var" data-copy="' . $varCopy . '" title="var(' . $name . ') kopieren">' . $name . '</span>'
          . '<span class="ds-val">' . $val . '</span>'
          . ds_src_badge($t['source'])
          . '</div>';

    switch ($t['kind']) {
        case 'color':
            return '<button class="ds-tile" type="button" data-copy="' . $val . '" data-label="Hex" title="Hex kopieren">'
                 . '<span class="ds-chip" style="background:' . $val . '"></span>' . $meta . '</button>';

        case 'alias':
            $resolved = ds_resolve($t['value'], $map);
            $chip = $resolved !== null
                ? '<span class="ds-chip" style="background:' . htmlspecialchars($resolved) . '"></span>'
                : '<span class="ds-chip ds-chip--empty"></span>';
            return '<button class="ds-tile" type="button" data-copy="' . $val . '" data-label="Wert" title="Wert kopieren">'
                 . $chip . $meta . '</button>';

        case 'shadow':
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Schatten" title="Schatten kopieren">'
                 . '<span class="ds-stage"><span class="ds-shadow-tile" style="box-shadow:' . $val . '"></span></span>'
                 . $meta . '</button>';

        case 'radius':
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Radius" title="Wert kopieren">'
                 . '<span class="ds-stage"><span class="ds-radius-tile" style="border-radius:' . $val . '"></span></span>'
                 . $meta . '</button>';

        case 'font':
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Font" title="Font-Stack kopieren">'
                 . '<span class="ds-font-stage" style="font-family:' . $val . '">Aa · Marktlauf 2026</span>'
                 . $meta . '</button>';

        case 'size':
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Größe" title="Wert kopieren">'
                 . '<span class="ds-stage ds-stage--left"><span style="font-size:' . $val . ';line-height:1.1">Text ' . $val . '</span></span>'
                 . $meta . '</button>';

        case 'space':
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Abstand" title="Wert kopieren">'
                 . '<span class="ds-stage"><span class="ds-space-bar" style="width:' . $val . '"></span></span>'
                 . $meta . '</button>';

        default:
            return '<button class="ds-tile ds-tile--wide" type="button" data-copy="' . $val . '" data-label="Wert" title="Wert kopieren">'
                 . '<span class="ds-value-stage">' . $val . '</span>' . $meta . '</button>';
    }
}

/** Eine Grid-Sektion aus einer Token-Liste rendern. */
function ds_render_grid(array $items, array $map): string
{
    if ($items === []) {
        return '<p class="ds-empty">Keine Tokens in dieser Gruppe.</p>';
    }
    $html = '<div class="ds-grid">';
    foreach ($items as $t) {
        $html .= ds_card($t, $map);
    }
    return $html . '</div>';
}

/**
 * Fachliche Gruppe eines Farb-Tokens — portiert aus der abgelösten ci.php (ci_group),
 * erweitert um die Dashboard-UI-Tokens aus orga.css (--text*, --bg, --border, --error*,
 * --success*). Prüfreihenfolge bewusst: Hero/Kooperation ZUERST, damit z. B.
 * --color-accent-yellow nicht von der Primär-/Akzent-Regel eingefangen wird.
 */
function ds_color_group(string $name): string
{
    if (str_starts_with($name, '--hero')) return 'Hero & Kooperation';
    if (in_array($name, [
        '--color-accent-yellow', '--color-deep-green', '--color-marktlauf-green',
        '--color-teal', '--color-cream', '--color-ink',
    ], true)) {
        return 'Hero & Kooperation';
    }
    if (preg_match('/^--(color-primary|primary|color-accent|accent)/', $name)) {
        return 'Primär & Marke';
    }
    if (str_starts_with($name, '--gray') || $name === '--white'
        || str_starts_with($name, '--text') || $name === '--bg' || $name === '--border') {
        return 'Graustufen & UI';
    }
    if (str_starts_with($name, '--success') || str_starts_with($name, '--error')) {
        return 'Status';
    }
    return 'Weitere Farben';
}

/**
 * Farb-Tokens gruppiert rendern: je Gruppe ein Untertitel (+ Kurznote) und ein eigenes
 * Grid, in fester fachlicher Reihenfolge. Ersetzt die flache Liste, damit die DS-Seite die
 * Farben so ordentlich zeigt wie zuvor ci.php.
 */
function ds_render_color_groups(array $items, array $map): string
{
    if ($items === []) {
        return '<p class="ds-empty">Keine Farb-Tokens gefunden.</p>';
    }
    $order = ['Primär & Marke', 'Hero & Kooperation', 'Graustufen & UI', 'Status', 'Weitere Farben'];
    $note  = [
        'Primär & Marke'     => 'Marke',
        'Hero & Kooperation' => 'nur in Hero-Flächen',
        'Graustufen & UI'    => 'Flächen · Text · Ränder',
        'Status'             => 'Rückmeldungen',
    ];
    $buckets = [];
    foreach ($items as $t) {
        $buckets[ds_color_group($t['name'])][] = $t;
    }
    $render = static function (string $group, array $rows) use ($note, $map): string {
        $sub = isset($note[$group])
            ? ' <span class="ds-color-note">· ' . htmlspecialchars($note[$group]) . '</span>'
            : '';
        return '<h3 class="ds-color-group">' . htmlspecialchars($group) . $sub . '</h3>'
             . ds_render_grid($rows, $map);
    };
    $html = '';
    foreach ($order as $group) {
        if (!empty($buckets[$group])) {
            $html .= $render($group, $buckets[$group]);
        }
    }
    // Sicherheitsnetz: unerwartete Gruppen dennoch zeigen (nie ein Token verschlucken).
    foreach ($buckets as $group => $rows) {
        if (!in_array($group, $order, true)) {
            $html .= $render($group, $rows);
        }
    }
    return $html;
}

/** Inline-Formatierung eines bereits zeilenweise zerlegten Fragments (nach dem Escapen). */
function ds_md_inline(string $text): string
{
    $out = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Inline-Code zuerst schützen, damit ** / [] darin literal bleiben.
    $out = preg_replace('/`([^`]+)`/', '<code>$1</code>', $out);
    $out = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $out);
    // Links [Text](url) — nur http(s), sonst als Text belassen.
    $out = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', static function (array $m): string {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $out);
    return $out;
}

/**
 * Minimaler, self-contained Markdown->HTML-Renderer für die Readme-Einleitung.
 * Deckt genau das ab, was readme.md nutzt: ATX-Headings, Absätze, GFM-Pipe-Tabellen,
 * `-`-Listen (mit lazy continuation), horizontale Linien und Inline (bold/code/link).
 * $headingOffset senkt jede Überschrift um n Ebenen ab (1 → # wird zu <h2>).
 */
function ds_render_markdown(string $md, int $headingOffset = 0): string
{
    $lines = preg_split('/\r\n|\r|\n/', $md) ?: [];
    $n     = count($lines);
    $html  = '';
    $i     = 0;

    $isBlank = static fn (string $l): bool => trim($l) === '';
    $isRule  = static fn (string $l): bool => (bool) preg_match('/^\s*-{3,}\s*$/', $l);
    $isHead  = static fn (string $l): bool => (bool) preg_match('/^\s*#{1,6}\s+/', $l);
    $isList  = static fn (string $l): bool => (bool) preg_match('/^\s*[-*]\s+/', $l);
    // Tabellen-Trennzeile: |---|:--:| o. Ä.
    $isTableSep = static fn (string $l): bool => (bool) preg_match('/^\s*\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)+\|?\s*$/', $l);
    $splitRow = static function (string $l): array {
        $l = trim($l);
        $l = preg_replace('/^\||\|$/', '', $l);
        return array_map('trim', explode('|', (string) $l));
    };

    while ($i < $n) {
        $line = $lines[$i];

        if ($isBlank($line)) { $i++; continue; }

        if ($isRule($line)) { $html .= "<hr>\n"; $i++; continue; }

        if ($isHead($line)) {
            preg_match('/^\s*(#{1,6})\s+(.*)$/', $line, $m);
            $level = min(6, strlen($m[1]) + $headingOffset);
            $html .= '<h' . $level . '>' . ds_md_inline(rtrim($m[2], " #")) . '</h' . $level . ">\n";
            $i++;
            continue;
        }

        // Tabelle: aktuelle Zeile hat |, nächste ist Trennzeile.
        if (strpos($line, '|') !== false && $i + 1 < $n && $isTableSep($lines[$i + 1])) {
            $head = $splitRow($line);
            $i   += 2; // Kopf + Trennzeile
            $rows = [];
            while ($i < $n && !$isBlank($lines[$i]) && strpos($lines[$i], '|') !== false && !$isHead($lines[$i])) {
                $rows[] = $splitRow($lines[$i]);
                $i++;
            }
            $html .= "<table class=\"ds-md-table\">\n<thead><tr>";
            foreach ($head as $c) { $html .= '<th>' . ds_md_inline($c) . '</th>'; }
            $html .= "</tr></thead>\n<tbody>\n";
            foreach ($rows as $r) {
                $html .= '<tr>';
                foreach ($head as $ci => $_) { $html .= '<td>' . ds_md_inline($r[$ci] ?? '') . '</td>'; }
                $html .= "</tr>\n";
            }
            $html .= "</tbody>\n</table>\n";
            continue;
        }

        if ($isList($line)) {
            $items = [];
            while ($i < $n && !$isBlank($lines[$i]) && !$isHead($lines[$i]) && !$isRule($lines[$i])) {
                if ($isList($lines[$i])) {
                    $items[] = preg_replace('/^\s*[-*]\s+/', '', $lines[$i]);
                } elseif ($items !== []) {
                    // Lazy continuation: Fortsetzungszeile an letztes Item anhängen.
                    $items[count($items) - 1] .= ' ' . trim($lines[$i]);
                } else {
                    break;
                }
                $i++;
            }
            $html .= "<ul class=\"ds-md-list\">\n";
            foreach ($items as $it) { $html .= '<li>' . ds_md_inline($it) . "</li>\n"; }
            $html .= "</ul>\n";
            continue;
        }

        // Absatz: aufeinanderfolgende Zeilen bis Leerzeile/Blockstart, mit Leerzeichen verbunden.
        $para = [];
        while ($i < $n && !$isBlank($lines[$i]) && !$isHead($lines[$i]) && !$isRule($lines[$i]) && !$isList($lines[$i])) {
            if (strpos($lines[$i], '|') !== false && $i + 1 < $n && $isTableSep($lines[$i + 1])) {
                break;
            }
            $para[] = trim($lines[$i]);
            $i++;
        }
        if ($para !== []) {
            $html .= '<p>' . ds_md_inline(implode(' ', $para)) . "</p>\n";
        }
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Design-System des ATSV Kirchseeon Marktlauf — Marke, Farben, Typografie und Muster als lebende Referenz.">
    <title>Design-System | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="stylesheet" href="../css/fonts.css?v=<?= @filemtime(__DIR__ . '/../css/fonts.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .ds-intro { color: var(--text-light); max-width: 62ch; margin: 0 0 1.25rem; font-size: 0.9rem; line-height: 1.5; }
        .ds-intro code { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; background: #eee; padding: 0.1em 0.4em; border-radius: 4px; font-size: 0.85em; }

        /* Shell: Menü links, Inhalt rechts. */
        .ds-shell { display: flex; gap: 1.5rem; align-items: flex-start; }
        html { scroll-behavior: smooth; }
        .ds-nav { flex: 0 0 200px; position: sticky; top: 1rem; align-self: flex-start; max-height: calc(100vh - 2rem); overflow-y: auto; display: flex; flex-direction: column; gap: 0.15rem; }
        .ds-nav a {
            text-decoration: none; color: var(--text); padding: 0.55rem 0.75rem; border-radius: 8px;
            display: flex; align-items: center; gap: 0.5rem; transition: background 0.12s;
        }
        .ds-nav a:hover { background: var(--bg); }
        .ds-nav a.is-active { background: var(--primary); color: #fff; font-weight: 600; }
        .ds-nav .ds-nav-count { margin-left: auto; font-size: 0.72rem; opacity: 0.7; font-variant-numeric: tabular-nums; }
        .ds-nav a.is-active .ds-nav-count { opacity: 0.85; }
        .ds-content { flex: 1 1 auto; min-width: 0; }

        /* Fließende Seite: alle Sektionen untereinander, Menü springt per Anker. */
        .ds-section { scroll-margin-top: 1.5rem; padding-bottom: 2.25rem; margin-bottom: 2.25rem; border-bottom: 1px solid var(--border); }
        .ds-section:last-child { border-bottom: 0; }
        .ds-section > h2 { font-size: 1.15rem; margin: 0 0 0.35rem; }
        .ds-section > .ds-lead { color: var(--text-light); font-size: 0.85rem; margin: 0 0 1.25rem; max-width: 60ch; line-height: 1.5; }

        .ds-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
        /* Untertitel je Farb-Gruppe (Ordnung aus der abgelösten ci.php). */
        .ds-color-group { font-size: 0.9rem; margin: 1.4rem 0 0.6rem; padding-bottom: 0.3rem; border-bottom: 2px solid var(--primary); }
        .ds-color-group:first-of-type { margin-top: 0.2rem; }
        .ds-color-note { font-weight: 400; color: var(--text-light); }
        .ds-tile {
            background: var(--white); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-card);
            overflow: hidden; padding: 0; font: inherit; color: inherit; text-align: left; cursor: pointer;
            display: flex; flex-direction: column; transition: box-shadow 0.15s, transform 0.15s;
        }
        .ds-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 16px -6px rgba(0,0,0,0.25); }
        .ds-tile:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .ds-tile--wide { grid-column: span 2; }
        @media (max-width: 560px) { .ds-tile--wide { grid-column: span 1; } }

        .ds-chip { height: 84px; width: 100%; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06); }
        .ds-chip--empty { background: repeating-linear-gradient(45deg, #f3f3f3, #f3f3f3 8px, #e9e9e9 8px, #e9e9e9 16px); }
        .ds-stage { height: 84px; display: grid; place-items: center; background: var(--bg); }
        .ds-stage--left { place-items: center start; padding: 0 0.9rem; }
        .ds-shadow-tile { width: 60%; height: 44px; border-radius: 8px; background: var(--white); }
        .ds-radius-tile { width: 64px; height: 44px; background: var(--primary); }
        .ds-space-bar { height: 18px; background: var(--primary); border-radius: 4px; min-width: 2px; }
        .ds-font-stage { height: 84px; display: grid; place-items: center; background: var(--bg); font-size: 1.5rem; font-weight: 600; color: var(--text); }
        .ds-value-stage { padding: 0.9rem 0.8rem; background: var(--bg); font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 0.78rem; color: var(--text); word-break: break-all; }

        .ds-meta { padding: 0.55rem 0.7rem 0.65rem; display: flex; flex-direction: column; gap: 0.2rem; }
        .ds-var { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 0.76rem; font-weight: 600; word-break: break-all; line-height: 1.3; }
        .ds-var:hover { color: var(--primary); }
        .ds-val { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 0.7rem; color: var(--text-light); word-break: break-all; }
        .ds-src { align-self: flex-start; margin-top: 0.1rem; font-size: 0.62rem; font-weight: 600; letter-spacing: 0.02em; padding: 0.08rem 0.4rem; border-radius: 999px; }
        .ds-src--base { background: rgba(0,150,64,0.12); color: var(--primary); }
        .ds-src--orga { background: rgba(0,0,0,0.06); color: var(--text-light); }

        /* Marke-Sektion. */
        .ds-brand-row { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 0.85rem; }
        .ds-brand-card { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; box-shadow: var(--shadow-card); background: var(--white); }
        .ds-brand-swatch { height: 120px; display: flex; align-items: flex-end; padding: 0.6rem 0.8rem; color: #fff; font-weight: 700; text-shadow: 0 1px 2px rgba(0,0,0,0.25); }
        .ds-brand-foot { padding: 0.5rem 0.8rem 0.65rem; font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 0.76rem; color: var(--text-light); }

        .ds-empty { color: var(--text-light); font-size: 0.85rem; }
        .ds-todo { background: var(--bg); border: 1px dashed var(--border); border-radius: 10px; padding: 1.25rem 1.4rem; color: var(--text-light); font-size: 0.9rem; line-height: 1.55; max-width: 60ch; }
        .ds-todo strong { color: var(--text); }
        .ds-error { background: var(--error-bg, #fdecec); color: var(--error, #b3261e); border: 1px solid var(--error, #b3261e); border-radius: 8px; padding: 1rem 1.25rem; }

        .ds-snip-list { display: flex; flex-direction: column; gap: 1.1rem; }
        .ds-snip { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--white); box-shadow: var(--shadow-card); }
        .ds-snip-head { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0.9rem; border-bottom: 1px solid var(--border); }
        .ds-snip-title { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
        .ds-snip-title strong { font-size: 0.92rem; }
        .ds-snip-title span { font-size: 0.76rem; color: var(--text-light); }
        .ds-snip-copy { margin-left: auto; flex: 0 0 auto; appearance: none; border: 1px solid var(--primary); background: var(--primary); color: #fff; font: inherit; font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; transition: background 0.12s; }
        .ds-snip-copy:hover { background: var(--primary-dark, #007230); }
        .ds-snip-copy:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        /* Auto-Height: JS setzt die echte Höhe (misst den iframe-Inhalt) — keine Beschneidung. */
        .ds-fit { display: block; width: 100%; border: 0; background: var(--white); min-height: 120px; }

        .ds-guide-group { font-size: 0.95rem; margin: 1.5rem 0 0.75rem; padding-bottom: 0.35rem; border-bottom: 2px solid var(--border); }
        .ds-guide-group:first-of-type { margin-top: 0; }
        .ds-guide-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        .ds-guide { margin: 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--white); box-shadow: var(--shadow-card); }
        .ds-guide-cap { padding: 0.55rem 0.75rem 0.65rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.15rem; }
        .ds-guide-cap strong { font-size: 0.85rem; }
        .ds-guide-cap span { font-size: 0.74rem; color: var(--text-light); line-height: 1.4; }
        /* Komponenten: eine Kachel pro Zeile, volle Content-Breite (Guidelines-Grid bleibt mehrspaltig). */
        .ds-guide-grid--full { grid-template-columns: 1fr; }

        /* Templates: eine Kachel pro Zeile; Vorschau scrollbar (unterschiedlich hohe Motive). */
        .ds-tpl-grid { display: grid; gap: 0.85rem; grid-template-columns: 1fr; }
        .ds-tpl { margin: 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--white); box-shadow: var(--shadow-card); }
        .ds-tpl-scroll { max-height: 60vh; overflow-y: auto; border-bottom: 1px solid var(--border); background: var(--bg); }
        .ds-tpl img { display: block; width: 100%; height: auto; }

        /* Readme: gerenderte Markdown-Prosa. */
        .ds-readme { max-width: 74ch; color: var(--text); font-size: 0.9rem; line-height: 1.6; }
        .ds-readme h2 { font-size: 1.05rem; margin: 1.75rem 0 0.6rem; padding-bottom: 0.3rem; border-bottom: 2px solid var(--border); }
        .ds-readme h3 { font-size: 0.95rem; margin: 1.4rem 0 0.5rem; }
        .ds-readme h4 { font-size: 0.88rem; margin: 1.1rem 0 0.4rem; }
        .ds-readme > h2:first-child, .ds-readme > h3:first-child { margin-top: 0; }
        .ds-readme p { margin: 0 0 0.9rem; }
        .ds-readme ul { margin: 0 0 0.9rem; padding-left: 1.25rem; }
        .ds-readme li { margin: 0.2rem 0; }
        .ds-readme code { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; background: #eee; padding: 0.1em 0.4em; border-radius: 4px; font-size: 0.85em; word-break: break-word; }
        .ds-readme a { color: var(--primary); }
        .ds-readme hr { border: 0; border-top: 1px solid var(--border); margin: 1.75rem 0; }
        .ds-md-table { border-collapse: collapse; width: 100%; margin: 0 0 1rem; font-size: 0.82rem; }
        .ds-md-table th, .ds-md-table td { border: 1px solid var(--border); padding: 0.4rem 0.6rem; text-align: left; vertical-align: top; }
        .ds-md-table th { background: var(--bg); font-weight: 600; }

        @media (max-width: 720px) {
            .ds-shell { flex-direction: column; }
            .ds-nav { position: static; flex-direction: row; flex-wrap: wrap; width: 100%; }
        }

        #ds-toast {
            position: fixed; left: 50%; bottom: 2rem; transform: translate(-50%, 1.5rem);
            background: var(--text); color: #fff; padding: 0.55rem 1.1rem; border-radius: 999px;
            font-size: 0.82rem; font-weight: 600; font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            box-shadow: 0 8px 24px -6px rgba(0,0,0,0.4); opacity: 0; pointer-events: none;
            transition: opacity 0.2s, transform 0.2s; z-index: 1000;
        }
        #ds-toast.show { opacity: 1; transform: translate(-50%, 0); }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .ds-tile, #ds-toast, .ds-nav a { transition: none; } }
    </style>
</head>
<body>
<?php $activeNav = 'design_system'; require __DIR__ . '/_sidebar.php'; ?>
        <main class="main-content">
            <header class="content-header">
                <h1>Design-System</h1>
            </header>

            <p class="ds-intro">
                Navigierbare, lebende Referenz. Werte kommen bei jedem Aufruf direkt aus der
                gemeinsamen Quelle — <code>css/base.css</code> (Marke) und <code>orga/css/orga.css</code>
                (Dashboard). Klick auf eine Kachel kopiert den Wert, Klick auf den Variablennamen
                <code>var(--token)</code>. Das Herkunfts-Badge zeigt, aus welcher Datei ein Token stammt.
            </p>

            <?php if (!$tokensRead): ?>
                <div class="ds-error">
                    <strong>Keine Tokens gefunden.</strong>
                    Pfade/Deployment prüfen: <code>css/base.css</code>, <code>orga/css/orga.css</code>.
                </div>
            <?php else: ?>
            <div class="ds-shell">
                <nav class="ds-nav" aria-label="Design-System-Sektionen">
                    <?php foreach ($menu as $key => $label): ?>
                        <?php
                        $count = match ($key) {
                            'snippets'   => count($snippetItems),
                            'guidelines' => $guidelineCount,
                            'components' => count($componentItems),
                            'templates'  => count($templateItems),
                            default      => isset($sections[$key]) ? count($sections[$key]) : 0,
                        };
                        $countBadge = $count > 0
                            ? '<span class="ds-nav-count">' . str_pad((string) $count, 2, '0', STR_PAD_LEFT) . '</span>'
                            : '';
                        ?>
                        <a class="ds-nav-link<?= $key === $defaultSection ? ' is-active' : '' ?>"
                           href="#ds-<?= htmlspecialchars($key) ?>">
                            <?= htmlspecialchars($label) ?><?= $countBadge ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="ds-content">
                    <!-- Readme -->
                    <section class="ds-section" id="ds-readme" data-section="readme">
                        <h2>Readme</h2>
                        <p class="ds-lead">Einleitung des Design-System-Pakets — die zwei Oberflächen, Quellen, Content- und visuelle Grundlagen. Gerendert aus <code>design-system/readme.md</code>.</p>
                        <?php if ($readmeHtml === ''): ?>
                            <p class="ds-empty">Keine Readme gefunden (Deployment von <code>design-system/readme.md</code> prüfen).</p>
                        <?php else: ?>
                            <div class="ds-readme"><?= $readmeHtml ?></div>
                        <?php endif; ?>
                    </section>

                    <!-- Tonalität & Voice -->
                    <section class="ds-section" id="ds-voice" data-section="voice">
                        <h2>Tonalität &amp; Voice</h2>
                        <p class="ds-lead">Die EINE Marken-Stimme, aus der Newsletter, Pressetext und Social ihre Vorgaben ziehen (harte Regeln + Kanal-Deltas). Gerendert aus <code>src/brand/voice.md</code> — hier ändern, alle Generatoren ziehen mit.</p>
                        <?php if ($voiceHtml === ''): ?>
                            <p class="ds-empty">Keine Voice-Datei gefunden (<code>src/brand/voice.md</code> prüfen).</p>
                        <?php else: ?>
                            <div class="ds-readme"><?= $voiceHtml ?></div>
                        <?php endif; ?>
                    </section>

                    <!-- Marke -->
                    <section class="ds-section" id="ds-brand" data-section="brand">
                        <h2>Marke</h2>
                        <p class="ds-lead">Die tragenden Werte. Grün trägt die Marke, Orange ist die Aktion.</p>
                        <div class="ds-brand-row">
                            <div class="ds-brand-card">
                                <div class="ds-brand-swatch" style="background:<?= htmlspecialchars($primary) ?>">Primär · ATSV-Grün</div>
                                <div class="ds-brand-foot"><?= htmlspecialchars($primary) ?></div>
                            </div>
                            <div class="ds-brand-card">
                                <div class="ds-brand-swatch" style="background:<?= htmlspecialchars($accent) ?>">Akzent · Aktion</div>
                                <div class="ds-brand-foot"><?= htmlspecialchars($accent) ?></div>
                            </div>
                        </div>
                        <?php if ($gradient !== null): ?>
                            <div class="ds-brand-card">
                                <div class="ds-brand-swatch" style="background:<?= htmlspecialchars($gradient) ?>">Hero-Verlauf</div>
                                <div class="ds-brand-foot"><?= htmlspecialchars(
                                    $map['--hero-gradient-start'] . ' → ' . $map['--hero-gradient-mid'] . ' → ' . $map['--hero-gradient-end']
                                ) ?></div>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Farben -->
                    <section class="ds-section" id="ds-colors" data-section="colors">
                        <h2>Farben</h2>
                        <p class="ds-lead">Farb-Tokens beider Quellen, fachlich gruppiert (Primär &amp; Marke · Hero &amp; Kooperation · Graustufen &amp; UI · Status). Doppelt geführte Marken-Werte werden am Herkunfts-Badge sichtbar.</p>
                        <?= ds_render_color_groups($sections['colors'], $map) ?>
                    </section>

                    <!-- Abstände & Maße -->
                    <section class="ds-section" id="ds-spacing" data-section="spacing">
                        <h2>Abstände &amp; Maße</h2>
                        <p class="ds-lead">Spacing-Skala und weitere Maße für Layout und Bedienelemente.</p>
                        <?= ds_render_grid($sections['spacing'], $map) ?>
                    </section>

                    <!-- Typografie -->
                    <section class="ds-section" id="ds-type" data-section="type">
                        <h2>Typografie</h2>
                        <p class="ds-lead">Schrift-Stacks und Größen — mit den echten, self-hosted Schriften gerendert (kein CDN).</p>
                        <?= ds_render_grid($sections['type'], $map) ?>
                    </section>

                    <!-- Radius & Schatten -->
                    <section class="ds-section" id="ds-elevation" data-section="elevation">
                        <h2>Radius &amp; Schatten</h2>
                        <p class="ds-lead">Rundungen und Elevation für Karten und Flächen.</p>
                        <?= ds_render_grid($sections['elevation'], $map) ?>
                    </section>

                    <!-- Guidelines -->
                    <section class="ds-section" id="ds-guidelines" data-section="guidelines">
                        <h2>Guidelines</h2>
                        <p class="ds-lead">Kuratierte Design-Karten aus dem Paket — auf die kanonische Token-Quelle und self-hosted Schriften umgebogen (kein CDN, keine Google-Fonts).</p>
                        <?php if ($guidelineGroups === []): ?>
                            <p class="ds-empty">Keine Guidelines gefunden (Deployment von <code>design-system/guidelines/</code> prüfen).</p>
                        <?php else: ?>
                            <?php foreach ($guidelineGroups as $group => $items): ?>
                                <h3 class="ds-guide-group"><?= htmlspecialchars($group) ?></h3>
                                <div class="ds-guide-grid">
                                    <?php foreach ($items as $it): ?>
                                        <figure class="ds-guide">
                                            <iframe class="ds-fit" src="../design-system/guidelines/<?= htmlspecialchars($it['file']) ?>"
                                                    sandbox="allow-scripts allow-same-origin" loading="lazy" title="<?= htmlspecialchars($it['name']) ?>"></iframe>
                                            <figcaption class="ds-guide-cap">
                                                <strong><?= htmlspecialchars($it['name']) ?></strong>
                                                <?php if ($it['sub'] !== ''): ?><span><?= htmlspecialchars($it['sub']) ?></span><?php endif; ?>
                                            </figcaption>
                                        </figure>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <!-- Snippets -->
                    <section class="ds-section" id="ds-snippets" data-section="snippets">
                        <h2>Snippets</h2>
                        <p class="ds-lead">Fertige, self-contained HTML-Blöcke zum Kopieren — kein CDN, keine externen Schriften. Vorschau links, „HTML kopieren" liefert den einbettbaren Block.</p>
                        <?php if ($snippetItems === []): ?>
                            <p class="ds-empty">Keine Snippets gefunden (Deployment von <code>design-system/snippets/</code> prüfen).</p>
                        <?php else: ?>
                        <div class="ds-snip-list">
                            <?php foreach ($snippetItems as $i => $s): ?>
                                <article class="ds-snip">
                                    <header class="ds-snip-head">
                                        <div class="ds-snip-title">
                                            <strong><?= htmlspecialchars($s['name']) ?></strong>
                                            <span><?= htmlspecialchars($s['sub']) ?></span>
                                        </div>
                                        <button type="button" class="ds-snip-copy" data-code="snip-code-<?= $i ?>">HTML kopieren</button>
                                    </header>
                                    <iframe class="ds-fit" src="../design-system/snippets/<?= htmlspecialchars($s['preview']) ?>"
                                            sandbox="allow-scripts allow-same-origin" loading="lazy" title="Vorschau: <?= htmlspecialchars($s['name']) ?>"></iframe>
                                    <pre id="snip-code-<?= $i ?>" class="ds-snip-code" hidden><?= htmlspecialchars($s['code']) ?></pre>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>

                    <!-- Komponenten -->
                    <section class="ds-section" id="ds-components" data-section="components">
                        <h2>Komponenten</h2>
                        <p class="ds-lead">Die Bausteine des Pakets, live gerendert — React ist self-hosted, das JSX vorab kompiliert (kein CDN, kein Babel im Browser). Jede Demo läuft isoliert im iframe.</p>
                        <?php if ($componentItems === []): ?>
                            <p class="ds-empty">Keine Komponenten gefunden (Deployment von <code>design-system/components/</code> prüfen).</p>
                        <?php else: ?>
                        <div class="ds-guide-grid ds-guide-grid--full">
                            <?php foreach ($componentItems as $c): ?>
                                <figure class="ds-guide">
                                    <iframe class="ds-fit" src="../design-system/components/<?= htmlspecialchars($c['file']) ?>"
                                            sandbox="allow-scripts allow-same-origin" loading="lazy" title="<?= htmlspecialchars($c['name']) ?>"></iframe>
                                    <figcaption class="ds-guide-cap">
                                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                                        <?php if ($c['sub'] !== ''): ?><span><?= htmlspecialchars($c['sub']) ?></span><?php endif; ?>
                                    </figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>

                    <!-- Templates -->
                    <section class="ds-section" id="ds-templates" data-section="templates">
                        <h2>Templates</h2>
                        <p class="ds-lead">Vorschau der Vorlagen aus dem Paket. Die tatsächliche Erzeugung läuft in den jeweiligen Werkzeugen (Social-Orchestrator, Plakat-Generator, Newsletter) — Anbindung an die gemeinsame Token-Quelle folgt als Inc 3. Die Sponsoren-Rechnung ist bewusst nicht enthalten (echte Bankdaten bleiben privat).</p>
                        <?php if ($templateItems === []): ?>
                            <p class="ds-empty">Keine Templates gefunden (Deployment von <code>design-system/templates/</code> prüfen).</p>
                        <?php else: ?>
                        <div class="ds-tpl-grid">
                            <?php foreach ($templateItems as $t): ?>
                                <figure class="ds-tpl">
                                    <div class="ds-tpl-scroll">
                                        <img src="../design-system/templates/<?= htmlspecialchars($t['thumb']) ?>"
                                             alt="Vorschau: <?= htmlspecialchars($t['name']) ?>" loading="lazy">
                                    </div>
                                    <figcaption class="ds-guide-cap">
                                        <strong><?= htmlspecialchars($t['name']) ?></strong>
                                        <span><?= htmlspecialchars($t['sub']) ?></span>
                                    </figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="ds-toast">Kopiert</div>

    <script>
    (function () {
        // Burger-Menü (identisch zu den anderen Dashboard-Seiten).
        var burger  = document.getElementById('burger-btn');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
        if (burger && sidebar && overlay) {
            burger.addEventListener('click', function () {
                sidebar.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            overlay.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('.nav-item a').forEach(function (link) {
                link.addEventListener('click', closeSidebar);
            });
        }

        // Scrollspy: fließende Seite, Menü markiert die Sektion beim Scrollen (Anker-Sprung via href).
        var navLinks = Array.prototype.slice.call(document.querySelectorAll('.ds-nav-link'));
        var sections = Array.prototype.slice.call(document.querySelectorAll('.ds-section'));
        var linkByKey = {};
        navLinks.forEach(function (a) { linkByKey[(a.getAttribute('href') || '').replace('#', '')] = a; });
        function setActive(id) {
            navLinks.forEach(function (a) { a.classList.remove('is-active'); });
            if (linkByKey[id]) linkByKey[id].classList.add('is-active');
        }
        if ('IntersectionObserver' in window && sections.length) {
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) setActive(e.target.id); });
            }, { rootMargin: '-8% 0px -82% 0px', threshold: 0 });
            sections.forEach(function (s) { spy.observe(s); });
        }

        // Auto-Height: iframe-Inhalt messen (same-origin) und die Höhe setzen — keine Beschneidung.
        function fitFrame(f) {
            try {
                var doc = f.contentDocument || (f.contentWindow && f.contentWindow.document);
                var h = doc && doc.documentElement ? doc.documentElement.scrollHeight : 0;
                if (h > 0) { f.style.height = h + 'px'; }
            } catch (e) { /* Fallback: min-height aus CSS */ }
        }
        document.querySelectorAll('iframe.ds-fit').forEach(function (f) {
            function schedule() { [0, 400, 1000, 2000].forEach(function (t) { setTimeout(function () { fitFrame(f); }, t); }); }
            f.addEventListener('load', schedule);
            if (f.contentDocument && f.contentDocument.readyState === 'complete') { schedule(); }
        });
        window.addEventListener('resize', function () {
            document.querySelectorAll('iframe.ds-fit').forEach(fitFrame);
        });

        // Klick-zum-Kopieren.
        var toastEl = document.getElementById('ds-toast');
        var toastTimer;
        function toast(msg) {
            toastEl.textContent = msg;
            toastEl.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 1400);
        }
        function copy(text, label) {
            if (!text || !navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(
                function () { toast(label + '  ·  ' + text); },
                function () { toast('Kopieren blockiert'); }
            );
        }
        document.querySelectorAll('.ds-tile').forEach(function (tile) {
            tile.addEventListener('click', function () {
                copy(tile.dataset.copy, tile.dataset.label || 'Wert');
            });
            var v = tile.querySelector('.ds-var[data-copy]');
            if (v) {
                v.addEventListener('click', function (e) {
                    e.stopPropagation();
                    copy(v.dataset.copy, 'Variable');
                });
            }
        });

        // Snippet-HTML kopieren (kurzer Toast, nicht der ganze Block).
        document.querySelectorAll('.ds-snip-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pre = document.getElementById(btn.dataset.code);
                if (!pre || !navigator.clipboard) return;
                navigator.clipboard.writeText(pre.textContent).then(
                    function () { toast('HTML kopiert'); },
                    function () { toast('Kopieren blockiert'); }
                );
            });
        });
    })();
    </script>
</body>
</html>
