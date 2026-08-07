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
    ['preview' => 'newsletter-mail.html',      'copy' => 'newsletter-master.snippet.html', 'name' => 'Newsletter-Master',    'sub' => 'HTML-Grundgerüst für Mailings'],
    ['preview' => 'newsletter-beispiel.html',  'copy' => 'newsletter-beispiel.html',       'name' => 'Newsletter-Beispiel',  'sub' => 'Ausgefülltes Beispiel-Mailing'],
    ['preview' => 'raceresult-infotext.html',  'copy' => 'raceresult-infotext.html',       'name' => 'RaceResult Info-Text', 'sub' => 'HTML-Block für das INFO-Feld'],
];
// Nur real vorhandene Snippets zeigen; Kopier-Inhalt serverseitig laden.
$snippetItems = array_values(array_filter(array_map(static function (array $s) use ($snippetDir): ?array {
    if (!is_file($snippetDir . '/' . $s['preview'])) {
        return null;
    }
    $raw = @file_get_contents($snippetDir . '/' . $s['copy']);
    $s['code'] = $raw !== false ? $raw : '';
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
$defaultSection = 'brand';

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
        .ds-tpl-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
        .ds-tpl { margin: 0; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--white); box-shadow: var(--shadow-card); }
        .ds-tpl img { display: block; width: 100%; height: auto; border-bottom: 1px solid var(--border); background: var(--bg); }

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
                        <p class="ds-lead">Alle Farb-Tokens beider Quellen. Doppelt geführte Marken-Werte werden am Herkunfts-Badge sichtbar.</p>
                        <?= ds_render_grid($sections['colors'], $map) ?>
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
                        <div class="ds-guide-grid">
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
                                    <img src="../design-system/templates/<?= htmlspecialchars($t['thumb']) ?>"
                                         alt="Vorschau: <?= htmlspecialchars($t['name']) ?>" loading="lazy">
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
