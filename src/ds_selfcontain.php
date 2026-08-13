<?php
/**
 * Self-contained-Export der DS-Paket-HTML (Guidelines/Komponenten).
 *
 * Die Paket-HTMLs unter design-system/guidelines/ und design-system/components/ rendern
 * on-page über relative Referenzen (../tokens.php, ../../css/fonts.css, ../../assets/…).
 * Lose heruntergeladen brechen diese Pfade. Für den Designer-Download (Punkt 6 #3) werden
 * sie hier zu EINER offline öffenbaren Datei verschmolzen: Token-CSS, Schriften (woff2) und
 * Bilder als data-URI inline, für Komponenten zusätzlich React + das vorkompilierte Bundle.
 *
 * Bewusst nur On-Demand (Download-Endpoint), nie im Seiten-Ladepfad: die Dateien werden mit
 * eingebetteten Fonts/Bildern groß (Guideline ~0,5–1,5 MB, Komponente ~250 KB).
 */

declare(strict_types=1);

require_once __DIR__ . '/design_tokens.php';

/** MIME-Typ nach Dateiendung (nur die im Paket vorkommenden Bild-/Font-Typen). */
function ds_mime_for(string $path): string
{
    return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'png'          => 'image/png',
        'jpg', 'jpeg'  => 'image/jpeg',
        'gif'          => 'image/gif',
        'svg'          => 'image/svg+xml',
        'webp'         => 'image/webp',
        'woff2'        => 'font/woff2',
        default        => 'application/octet-stream',
    };
}

/** Datei als data:-URI (base64). Null, wenn nicht lesbar. */
function ds_data_uri(string $absPath): ?string
{
    if (!is_file($absPath)) {
        return null;
    }
    $bytes = @file_get_contents($absPath);
    if ($bytes === false) {
        return null;
    }
    return 'data:' . ds_mime_for($absPath) . ';base64,' . base64_encode($bytes);
}

/**
 * Öffentliches Token-CSS wie design-system/tokens.php — aber als String (ohne HTTP-Header):
 * DS-Paket-Zusatztokens (tokens/*.css außer fonts.css) + kanonische base.css/orga.css-Werte
 * (ds_render_root_css) zuletzt, damit die kanonischen Werte gewinnen (Spec E2).
 */
function ds_public_tokens_css(string $webroot): string
{
    $css = "/* Design-System-Tokens (self-contained Export). */\n";
    foreach (glob($webroot . '/design-system/tokens/*.css') ?: [] as $f) {
        if (basename($f) === 'fonts.css') {
            continue;
        }
        $css .= (string) @file_get_contents($f) . "\n";
    }
    $css .= ds_render_root_css() . "\n";
    return $css;
}

/**
 * css/fonts.css mit den woff2-Dateien als data-URI inline. url(../assets/fonts/X) ist relativ
 * zu css/ → auflösbar unter <webroot>/assets/fonts/X.
 */
function ds_inline_fonts_css(string $webroot): string
{
    $css = (string) @file_get_contents($webroot . '/css/fonts.css');
    if ($css === '') {
        return '';
    }
    return (string) preg_replace_callback(
        '#url\(\.\./assets/fonts/([^)]+)\)#',
        static function (array $m) use ($webroot): string {
            $uri = ds_data_uri($webroot . '/assets/fonts/' . trim($m[1], "'\""));
            return $uri !== null ? 'url(' . $uri . ')' : $m[0];
        },
        $css
    );
}

/**
 * Verschmilzt eine Paket-HTML (Guideline/Komponente) zu einer offline öffenbaren Datei.
 *
 * @param string $srcAbs  absoluter Pfad der Quell-HTML (unter design-system/guidelines|components/)
 * @param string $webroot absoluter Pfad des website/-Roots
 * @param bool   $withScripts  Komponenten: React + Bundle-Skripte inline einbetten
 */
function ds_selfcontain_html(string $srcAbs, string $webroot, bool $withScripts): string
{
    $html = (string) @file_get_contents($srcAbs);
    if ($html === '') {
        return '';
    }

    // 1) Token-CSS: <link href="../tokens.php"> → <style>…</style>
    $html = (string) preg_replace(
        '#<link[^>]*href="\.\./tokens\.php"[^>]*>#',
        "<style>\n" . ds_public_tokens_css($webroot) . "\n</style>",
        $html
    );

    // 2) Fonts: <link href="../../css/fonts.css"> → <style> mit woff2 als data-URI
    $html = (string) preg_replace(
        '#<link[^>]*href="\.\./\.\./css/fonts\.css"[^>]*>#',
        "<style>\n" . ds_inline_fonts_css($webroot) . "\n</style>",
        $html
    );

    // 3) Komponenten: die extern geladenen Skripte inline einbetten (React, ReactDOM, Bundle).
    //    ZUERST (vor dem Bild-Inlining), damit auch die Bildpfade IM Bundle mit erfasst werden.
    if ($withScripts) {
        $scripts = [
            '../../assets/vendor/react/react.production.min.js'     => $webroot . '/assets/vendor/react/react.production.min.js',
            '../../assets/vendor/react/react-dom.production.min.js' => $webroot . '/assets/vendor/react/react-dom.production.min.js',
            '../_ds_bundle.js'                                      => $webroot . '/design-system/_ds_bundle.js',
        ];
        foreach ($scripts as $ref => $abs) {
            $code = (string) @file_get_contents($abs);
            $pattern = '#<script[^>]*src="' . preg_quote($ref, '#') . '"[^>]*>\s*</script>#';
            // </script> im Bundle neutralisieren, damit der Inline-Block nicht vorzeitig schließt.
            $safe = str_replace('</script>', '<\/script>', $code);
            $html = (string) preg_replace($pattern, "<script>\n" . $safe . "\n</script>", $html, 1);
        }
    }

    // 4) Bilder als data-URI — ZULETZT über das ganze Dokument (inkl. inline-Bundle). Zwei Formen:
    //    (a) direkte Literale `../assets/images/NAME` / `../../assets/images/NAME` (HTML-src, url(), JS-src),
    //    (b) Laufzeit-Konkatenation im Bundle: `base + 'assets/images/NAME'` → data-URI-Literal
    //        (base wird verworfen; für einen Offline-Download ist immer das eingebettete Asset gemeint).
    $img = static function (string $name) use ($webroot): ?string {
        return ds_data_uri($webroot . '/assets/images/' . $name);
    };
    $html = (string) preg_replace_callback(
        '#(?:\.\./)+assets/images/([A-Za-z0-9._-]+)#',
        static fn (array $m): string => $img($m[1]) ?? $m[0],
        $html
    );
    $html = (string) preg_replace_callback(
        '#base\s*\+\s*([\'"])assets/images/([A-Za-z0-9._-]+)\1#',
        static function (array $m) use ($img): string {
            $uri = $img($m[2]);
            return $uri !== null ? $m[1] . $uri . $m[1] : $m[0];
        },
        $html
    );

    return $html;
}
