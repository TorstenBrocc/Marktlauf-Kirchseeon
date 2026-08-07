<?php
/**
 * Öffentliche Token-CSS für die statischen DS-Paket-Seiten (guidelines/, components/).
 *
 * Schichtung (Reihenfolge = CSS-Kaskade, Späteres gewinnt):
 *   1) DS-Paket-Zusatztokens (design-system/tokens/*.css, ohne fonts.css) — die
 *      DS-spezifischen Werte, die die Website-CSS NICHT kennt: Radien-Skala
 *      (`--radius-pill` …), Motion (`--duration-*`, `--ease`), Gewichte
 *      (`--weight-*`). Ohne sie rendern die Komponenten falsch (eckige statt
 *      pill-Buttons, keine Transitions).
 *   2) Kanonische Marken-Werte aus css/base.css + orga/css/orga.css
 *      (`ds_render_root_css`) ZULETZT → sie überschreiben alle geteilten Tokens
 *      (z. B. `--color-primary`) autoritativ (Spec E2: base/orga bleiben die eine
 *      Wertequelle; das Paket liefert nur die Zusatz-Tokens).
 *
 * Fonts kommen separat über css/fonts.css (self-hosted) → tokens/fonts.css hier ausgelassen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/design_tokens.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300');

echo "/* DS-Paket-Zusatztokens + kanonische Werte (base.css/orga.css gewinnen). Nicht editieren. */\n\n";

foreach (glob(__DIR__ . '/tokens/*.css') ?: [] as $f) {
    if (basename($f) === 'fonts.css') {
        continue;
    }
    echo "/* paket-token: " . basename($f) . " */\n" . (string) file_get_contents($f) . "\n";
}

echo "/* kanonisch — base.css + orga.css (Vorrang) */\n" . ds_render_root_css();
