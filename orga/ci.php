<?php
/**
 * CI & Design-Tokens — lebende Referenz.
 *
 * Liest die Dashboard-Tokens zur Laufzeit aus der Single Source of Truth
 * (`orga/css/orga.css`, :root) und rendert sie. Keine Werte hier hartcodieren —
 * neue Tokens in orga.css erscheinen automatisch. Die oeffentliche Marken-Palette
 * (base.css: Gold, Marken-Schriften, Hero-Verlauf) ist bewusst NICHT hier, sondern
 * im Design-System-Paket unter intern/design-system/ — das Dashboard bleibt getrennt.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

/** Art eines Tokens für die Darstellung bestimmen. */
function ci_kind(string $name, string $value): string
{
    if (str_starts_with($name, '--shadow')) return 'shadow';
    if (str_starts_with($name, '--font'))   return 'font';
    if (str_starts_with($name, '--space'))  return 'space';
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) return 'color';
    if (str_starts_with($value, 'var('))    return 'alias';
    return 'other';
}

/** Fachliche Gruppe eines Tokens (Reihenfolge siehe $groupOrder). */
function ci_group(string $name, string $kind): string
{
    if ($kind === 'shadow') return 'Schatten';
    if ($kind === 'font')   return 'Schrift';
    if ($kind === 'space')  return 'Abstände';

    // Hero-/Kooperationsfarben zuerst — sonst würde z. B. --color-accent-yellow
    // fälschlich von der Primär-Regel (--color-accent*) eingefangen.
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
    if (str_starts_with($name, '--gray') || $name === '--white') return 'Graustufen';
    if ($name === '--success') return 'Status';
    if ($kind === 'color' || $kind === 'alias') return 'Weitere Farben';
    return 'Sonstige';
}

/** var(--x) eine Ebene auflösen, falls Ziel bekannt ist. */
function ci_resolve(string $value, array $tokens): ?string
{
    if (preg_match('/^var\(\s*(--[\w-]+)\s*\)$/', $value, $m) && isset($tokens[$m[1]])) {
        return $tokens[$m[1]];
    }
    return null;
}

/**
 * Eine CSS-Datei einlesen und ihre :root-Tokens gruppiert zurückgeben.
 * @return array{read:bool,tokens:array<string,string>,groups:array<string,array>,gradient:?string}
 */
function ci_parse(string $cssPath): array
{
    $tokens = [];
    $read   = false;
    $raw    = @file_get_contents($cssPath);
    if ($raw !== false) {
        $read = true;
        // Ersten :root { ... }-Block greifen (Default-/Light-Tokens)
        if (preg_match('/:root\s*\{(.*?)\}/s', $raw, $block)) {
            if (preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $block[1], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $tokens[$p[1]] = trim($p[2]);
                }
            }
        }
    }

    $groups = [];
    foreach ($tokens as $name => $value) {
        $kind = ci_kind($name, $value);
        $groups[ci_group($name, $kind)][] = ['name' => $name, 'value' => $value, 'kind' => $kind];
    }

    $gradient = null;
    if (isset($tokens['--hero-gradient-start'], $tokens['--hero-gradient-mid'], $tokens['--hero-gradient-end'])) {
        $gradient = sprintf(
            'linear-gradient(120deg, %s 0%%, %s 55%%, %s 100%%)',
            $tokens['--hero-gradient-start'],
            $tokens['--hero-gradient-mid'],
            $tokens['--hero-gradient-end']
        );
    }

    return ['read' => $read, 'tokens' => $tokens, 'groups' => $groups, 'gradient' => $gradient];
}

$groupOrder = [
    'Primär & Marke', 'Hero & Kooperation', 'Graustufen', 'Status',
    'Weitere Farben', 'Schrift', 'Abstände', 'Schatten', 'Sonstige',
];

$groupNote = [
    'Primär & Marke'     => 'Marke',
    'Hero & Kooperation' => 'nur in Hero-Klassen',
    'Graustufen'         => 'Flächen · Text · Ränder',
    'Status'             => 'Rückmeldungen',
    'Abstände'           => 'Spacing-Skala',
    'Schatten'           => 'Elevation',
];

// Zwei bereits ÖFFENTLICHE Quellen — keine zusätzliche Preisgabe:
// Dashboard-Tokens (orga.css) + Website-/Marken-Tokens (base.css).
$sources = [
    [
        'label' => 'Dashboard',
        'rel'   => 'orga/css/orga.css',
        'note'  => 'Density-first · Orga-Oberfläche',
        'data'  => ci_parse(__DIR__ . '/css/orga.css'),
    ],
    [
        'label' => 'Website & Marke',
        'rel'   => 'css/base.css',
        'note'  => 'Öffentliche Event-Website · Markenpalette',
        'data'  => ci_parse(__DIR__ . '/../css/base.css'),
    ],
];

/** Eine Token-Kachel rendern. */
function ci_card(array $t, array $tokens): string
{
    $name = htmlspecialchars($t['name']);
    $val  = htmlspecialchars($t['value']);
    $varCopy = htmlspecialchars('var(' . $t['name'] . ')');

    $meta = '<div class="ci-meta">'
          . '<span class="ci-var" data-copy="' . $varCopy . '" title="var(' . $name . ') kopieren">' . $name . '</span>'
          . '<span class="ci-val">' . $val . '</span>'
          . '</div>';

    switch ($t['kind']) {
        case 'color':
            return '<button class="ci-card" type="button" data-copy="' . $val . '" data-label="Hex" title="Hex kopieren">'
                 . '<span class="ci-chip" style="background:' . $val . '"></span>' . $meta . '</button>';

        case 'alias':
            $resolved = ci_resolve($t['value'], $tokens);
            $bg = $resolved !== null ? htmlspecialchars($resolved) : 'transparent';
            $chip = $resolved !== null
                ? '<span class="ci-chip" style="background:' . $bg . '"></span>'
                : '<span class="ci-chip ci-chip--empty"></span>';
            return '<button class="ci-card" type="button" data-copy="' . $val . '" data-label="Wert" title="Wert kopieren">'
                 . $chip . $meta . '</button>';

        case 'shadow':
            return '<button class="ci-card ci-card--wide" type="button" data-copy="' . $val . '" data-label="Schatten" title="Schatten kopieren">'
                 . '<span class="ci-shadow-stage"><span class="ci-shadow-tile" style="box-shadow:' . $val . '"></span></span>'
                 . $meta . '</button>';

        case 'font':
            return '<button class="ci-card ci-card--wide" type="button" data-copy="' . $val . '" data-label="Font" title="Font-Stack kopieren">'
                 . '<span class="ci-font-stage" style="font-family:' . $val . '">Aa · Marktlauf</span>'
                 . $meta . '</button>';

        case 'space':
            return '<button class="ci-card ci-card--wide" type="button" data-copy="' . $val . '" data-label="Abstand" title="Wert kopieren">'
                 . '<span class="ci-space-stage"><span class="ci-space-bar" style="width:' . $val . '"></span></span>'
                 . $meta . '</button>';

        default:
            return '<button class="ci-card ci-card--wide" type="button" data-copy="' . $val . '" data-label="Wert" title="Wert kopieren">'
                 . '<span class="ci-value-stage">' . $val . '</span>' . $meta . '</button>';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>CI &amp; Design-Tokens | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Montserrat:wght@500;700;800;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .ci-intro {
            color: var(--text-light);
            max-width: 62ch;
            margin: 0 0 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .ci-intro code {
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            background: #eee;
            padding: 0.1em 0.4em;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .ci-group { margin-bottom: 2.25rem; }
        .ci-group-head {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        .ci-group-head h2 { font-size: 1.05rem; margin: 0; }
        .ci-group-head .ci-count {
            font-size: 0.75rem;
            color: var(--text-light);
            font-variant-numeric: tabular-nums;
        }
        .ci-group-head .ci-note {
            margin-left: auto;
            font-size: 0.75rem;
            color: var(--text-light);
        }
        .ci-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
        }
        .ci-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: var(--shadow-card);
            overflow: hidden;
            padding: 0;
            font: inherit;
            color: inherit;
            text-align: left;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.15s, transform 0.15s;
        }
        .ci-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px -6px rgba(0,0,0,0.25); }
        .ci-card:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .ci-card--wide { grid-column: span 2; }
        @media (max-width: 520px) { .ci-card--wide { grid-column: span 1; } }
        .ci-chip {
            height: 88px;
            width: 100%;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06);
        }
        .ci-chip--empty {
            background: repeating-linear-gradient(45deg, #f3f3f3, #f3f3f3 8px, #e9e9e9 8px, #e9e9e9 16px);
        }
        .ci-meta {
            padding: 0.6rem 0.7rem 0.7rem;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .ci-var {
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.78rem;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.3;
        }
        .ci-var:hover { color: var(--primary); }
        .ci-val {
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.72rem;
            color: var(--text-light);
            word-break: break-all;
        }
        .ci-shadow-stage,
        .ci-space-stage {
            height: 88px;
            display: grid;
            place-items: center;
            background: var(--bg);
        }
        .ci-shadow-tile {
            width: 60%;
            height: 46px;
            border-radius: 8px;
            background: var(--white);
        }
        .ci-space-bar {
            height: 20px;
            background: var(--primary);
            border-radius: 4px;
            min-width: 2px;
        }
        .ci-font-stage {
            height: 88px;
            display: grid;
            place-items: center;
            background: var(--bg);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text);
        }
        .ci-value-stage {
            padding: 0.9rem 0.8rem;
            background: var(--bg);
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.78rem;
            color: var(--text);
            word-break: break-all;
        }
        .ci-gradient-chip { height: 120px; width: 100%; }
        .ci-error {
            background: var(--error-bg);
            color: var(--error);
            border: 1px solid var(--error);
            border-radius: 8px;
            padding: 1rem 1.25rem;
        }
        #ci-toast {
            position: fixed;
            left: 50%;
            bottom: 2rem;
            transform: translate(-50%, 1.5rem);
            background: var(--text);
            color: var(--white);
            padding: 0.55rem 1.1rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            box-shadow: 0 8px 24px -6px rgba(0,0,0,0.4);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            z-index: 1000;
        }
        #ci-toast.show { opacity: 1; transform: translate(-50%, 0); }
        @media (prefers-reduced-motion: reduce) {
            .ci-card, #ci-toast { transition: none; }
        }
        .ci-source-head { margin: 2.75rem 0 1.25rem; }
        .ci-source-head h2 { font-size: 1.35rem; margin: 0 0 0.2rem; }
        .ci-source-head p { margin: 0; font-size: 0.82rem; color: var(--text-light); }
        .ci-source-head p code { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; background: #eee; padding: 0.05em 0.35em; border-radius: 4px; font-size: 0.85em; }
        .ci-logo-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        .ci-logo-card { background: var(--white); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-card); overflow: hidden; }
        .ci-logo-stage { height: 120px; display: grid; place-items: center; background: #f4f5f2; padding: 16px; }
        .ci-logo-stage img { max-width: 100%; max-height: 88px; height: auto; }
        .ci-logo-card .ci-meta { border-top: 1px solid var(--border); }
        .ci-rule-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .ci-rule { background: var(--white); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1rem 1.1rem; font-size: 0.86rem; line-height: 1.5; color: var(--text); }
        .ci-rule h3 { margin: 0 0 0.4rem; font-size: 0.95rem; }
        .ci-swatch-row { display: flex; gap: 6px; margin-top: 0.6rem; }
        .ci-swatch-row span { flex: 1; height: 22px; border-radius: 5px; }
    </style>
</head>
<body>
<?php $activeNav = 'ci'; require __DIR__ . '/_sidebar.php'; ?>
        <main class="main-content">
            <header class="content-header">
                <h1>CI &amp; Design</h1>
            </header>

            <p class="ci-intro">
                Lebende Marken- &amp; Design-Referenz. Die Token-Kacheln werden bei jedem Aufruf direkt aus den
                <code>:root</code>-Blöcken gelesen (<strong>Dashboard</strong> = <code>orga/css/orga.css</code>,
                <strong>Website &amp; Marke</strong> = <code>css/base.css</code>) — Single Source of Truth, driftfrei.
                Klick auf eine Kachel kopiert den Wert, Klick auf den Variablennamen kopiert <code>var(--token)</code>.
                Darunter kuratiertes Marken-Narrativ (Logos, Regeln) — die Nutzungsvorgaben, die kein Token abbildet.
            </p>

            <?php foreach ($sources as $src): $d = $src['data']; ?>
                <div class="ci-source-head">
                    <h2><?= htmlspecialchars($src['label']) ?></h2>
                    <p>Live aus <code><?= htmlspecialchars($src['rel']) ?></code> (<code>:root</code>) · <?= htmlspecialchars($src['note']) ?></p>
                </div>

                <?php if (!$d['read']): ?>
                    <div class="ci-error"><strong><?= htmlspecialchars($src['rel']) ?> nicht lesbar.</strong> Pfad/Deployment prüfen.</div>
                <?php elseif (empty($d['tokens'])): ?>
                    <div class="ci-error"><strong>Keine Tokens im <code>:root</code>-Block.</strong> Struktur von <?= htmlspecialchars($src['rel']) ?> prüfen.</div>
                <?php else: ?>
                    <?php foreach ($groupOrder as $groupName): ?>
                        <?php if (empty($d['groups'][$groupName])) continue; ?>
                        <section class="ci-group">
                            <div class="ci-group-head">
                                <h2><?= htmlspecialchars($groupName) ?></h2>
                                <span class="ci-count"><?= str_pad((string) count($d['groups'][$groupName]), 2, '0', STR_PAD_LEFT) ?></span>
                                <?php if (!empty($groupNote[$groupName])): ?>
                                    <span class="ci-note"><?= htmlspecialchars($groupNote[$groupName]) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ci-grid">
                                <?php foreach ($d['groups'][$groupName] as $t): ?>
                                    <?= ci_card($t, $d['tokens']) ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <?php if ($d['gradient'] !== null): ?>
                        <section class="ci-group">
                            <div class="ci-group-head">
                                <h2>Hero-Verlauf</h2>
                                <span class="ci-count">01</span>
                                <span class="ci-note">start → mid → end</span>
                            </div>
                            <div class="ci-grid">
                                <button class="ci-card ci-card--wide" type="button"
                                        data-copy="<?= htmlspecialchars($d['gradient']) ?>" data-label="Verlauf" title="Verlauf kopieren">
                                    <span class="ci-gradient-chip" style="background:<?= htmlspecialchars($d['gradient']) ?>"></span>
                                    <div class="ci-meta">
                                        <span class="ci-var">hero-gradient</span>
                                        <span class="ci-val"><?= htmlspecialchars(
                                            $d['tokens']['--hero-gradient-start'] . ' → ' .
                                            $d['tokens']['--hero-gradient-mid'] . ' → ' .
                                            $d['tokens']['--hero-gradient-end']
                                        ) ?></span>
                                    </div>
                                </button>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Narrativ: Logos (öffentliche Assets, bereits im Repo) -->
            <div class="ci-source-head">
                <h2>Logos</h2>
                <p>Öffentliche Marken-Assets aus <code>assets/images/</code>. Auf farbigem Grund immer in der weißen Plakette.</p>
            </div>
            <div class="ci-logo-grid">
                <?php
                $logos = [
                    ['marktlauf-wordmark.png', 'Wortmarke', 'Header'],
                    ['ATSV_Logo-750x968.png', 'ATSV-Wappen', 'Veranstalter'],
                    ['logo-final.svg', 'Bildmarke', 'Favicon · Dashboard'],
                    ['Wort-u-Bildmarke-Gemeinde.png', 'Gemeinde Kirchseeon', 'nur Kooperation'],
                    ['laeufer.png', 'Läufer-Silhouette', 'Gold · Hero/Plakat'],
                    ['logo_ohne_kreis.svg', 'Peak-Zeichen', 'Wasserzeichen'],
                ];
                foreach ($logos as [$file, $name, $use]):
                    $logoSrc = '../assets/images/' . rawurlencode($file);
                ?>
                    <div class="ci-logo-card">
                        <span class="ci-logo-stage"><img src="<?= htmlspecialchars($logoSrc) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy"></span>
                        <div class="ci-meta">
                            <span class="ci-var"><?= htmlspecialchars($name) ?></span>
                            <span class="ci-val"><?= htmlspecialchars($use) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Narrativ: Marken-Regeln (kuratiert, driftfrei — keine Token-Werte) -->
            <div class="ci-source-head">
                <h2>Marken-Regeln</h2>
                <p>Kuratiert aus dem Design-System-Briefing — die Nutzung; die Werte stehen oben.</p>
            </div>
            <div class="ci-rule-grid">
                <div class="ci-rule">
                    <h3>Hero-Verlauf</h3>
                    Genau <strong>ein</strong> Verlauf, nur großflächig (Hero, Story). Links ein Kontrast-Overlay für WCAG-AA. Keine weiteren Verläufe, nie blau-violett.
                </div>
                <div class="ci-rule">
                    <h3>Farb-Rollen</h3>
                    Grün trägt · Gold highlightet Zahlen/Uhrzeiten · Orange nur Aktion/Warnung. Höchstens zwei Hintergrundfarben je Seite.
                </div>
                <div class="ci-rule">
                    <h3>Status</h3>
                    Neu · Bestätigt · Abgelehnt — nur als Rückmeldung, nie als Dekoration.
                    <div class="ci-swatch-row"><span style="background:#f4b81e"></span><span style="background:#2e7d32"></span><span style="background:#d32f2f"></span></div>
                </div>
                <div class="ci-rule">
                    <h3>Typografie</h3>
                    Fredoka (Display) · Montserrat (Headings/Labels) · Poppins (Fließtext). Das Dashboard nutzt System-Schrift.
                </div>
                <div class="ci-rule">
                    <h3>Formate</h3>
                    Instagram 1:1 1080×1080 · Portrait 1080×1350 · Story 1080×1920 · Plakat A3 · Rechnung/Brief A4.
                </div>
                <div class="ci-rule">
                    <h3>Volle Referenz</h3>
                    Logo-Nutzungsregeln, Motion, Do/Don'ts und Vorlagen liegen im Design-System-Paket (intern) — nicht öffentlich.
                </div>
            </div>
        </main>
    </div>

    <div id="ci-toast">Kopiert</div>

    <script>
    (function () {
        // Burger-Menü (identisch zu den anderen Dashboard-Seiten)
        var burger  = document.getElementById('burger-btn');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
        if (burger) burger.addEventListener('click', openSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });

        // Klick-zum-Kopieren
        var toastEl = document.getElementById('ci-toast');
        var toastTimer;
        function toast(msg) {
            toastEl.textContent = msg;
            toastEl.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 1400);
        }
        function copy(text, label) {
            if (!text) return;
            navigator.clipboard.writeText(text).then(
                function () { toast(label + '  ·  ' + text); },
                function () { toast('Kopieren blockiert'); }
            );
        }
        document.querySelectorAll('.ci-card').forEach(function (card) {
            card.addEventListener('click', function () {
                copy(card.dataset.copy, card.dataset.label || 'Wert');
            });
            var v = card.querySelector('.ci-var[data-copy]');
            if (v) {
                v.addEventListener('click', function (e) {
                    e.stopPropagation();
                    copy(v.dataset.copy, 'Variable');
                });
            }
        });
    })();
    </script>
</body>
</html>
