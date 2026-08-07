<?php
/**
 * Design-Token-Loader — gemeinsame, server-lesbare Quelle.
 *
 * Liest die Design-Tokens aus den kanonischen CSS-Dateien (:root) und stellt sie
 * strukturiert bereit. EINE Quelle für den Design-System-Browser
 * (orga/design_system.php) und — perspektivisch — die Generatoren
 * (Social/Newsletter/Plakat), damit Marken-Werte nicht länger je Ziel dupliziert
 * werden.
 *
 * Kanon (Spec design-system-integration-spec.md, E2):
 *   css/base.css       — Marke/Website
 *   orga/css/orga.css  — Dashboard-UI
 * Keine Werte hier hartcodieren — neue Tokens in den CSS-Dateien erscheinen automatisch.
 */

declare(strict_types=1);

/** Kanonische Token-Dateien, relativ zum website/-Root. */
const DS_TOKEN_SOURCES = [
    'base' => 'css/base.css',
    'orga' => 'orga/css/orga.css',
];

/** Menschenlesbare Herkunft je Quelle (für Badges im Browser). */
const DS_SOURCE_LABEL = [
    'base' => 'Website / Marke',
    'orga' => 'Dashboard',
];

/**
 * Parst den ersten :root{}-Block einer CSS-Datei zu [--name => value].
 *
 * @return array<string,string> in Deklarationsreihenfolge; leer wenn nicht lesbar.
 */
function ds_parse_root(string $absPath): array
{
    $tokens = [];
    $raw = @file_get_contents($absPath);
    if ($raw === false) {
        return $tokens;
    }
    // Ersten :root { ... }-Block greifen (Default-/Light-Tokens).
    if (preg_match('/:root\s*\{(.*?)\}/s', $raw, $block)) {
        if (preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $block[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $p) {
                $tokens[$p[1]] = trim($p[2]);
            }
        }
    }
    return $tokens;
}

/** Art eines Tokens für Darstellung/Verarbeitung ableiten. */
function ds_token_kind(string $name, string $value): string
{
    if (str_starts_with($name, '--shadow')) return 'shadow';
    if (str_starts_with($name, '--radius')) return 'radius';
    if (str_starts_with($name, '--font'))   return 'font';
    if (str_starts_with($name, '--space'))  return 'space';
    if (str_starts_with($name, '--text'))   return 'size';
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) return 'color';
    if (preg_match('/^(?:rgb|hsl)a?\(/i', $value))   return 'color';
    if (str_starts_with($value, 'var('))     return 'alias';
    return 'other';
}

/**
 * Lädt alle kanonischen Tokens mit Herkunft.
 *
 * @param string|null $root website/-Wurzel (Default: eine Ebene über src/).
 * @return list<array{name:string,value:string,kind:string,source:string,file:string}>
 */
function ds_load_tokens(?string $root = null): array
{
    $root ??= dirname(__DIR__); // src/ -> website/
    $out = [];
    foreach (DS_TOKEN_SOURCES as $source => $rel) {
        foreach (ds_parse_root($root . '/' . $rel) as $name => $value) {
            $out[] = [
                'name'   => $name,
                'value'  => $value,
                'kind'   => ds_token_kind($name, $value),
                'source' => $source,
                'file'   => $rel,
            ];
        }
    }
    return $out;
}

/**
 * Flache name=>value-Map über alle Quellen (spätere Quelle überschreibt frühere).
 * Für Generatoren, die einen Wert per Name auflösen wollen.
 *
 * @return array<string,string>
 */
function ds_token_map(?string $root = null): array
{
    $map = [];
    foreach (ds_load_tokens($root) as $t) {
        $map[$t['name']] = $t['value'];
    }
    return $map;
}

/** var(--x) eine Ebene gegen eine Token-Map auflösen; null wenn Ziel unbekannt. */
function ds_resolve(string $value, array $map): ?string
{
    if (preg_match('/^var\(\s*(--[\w-]+)\s*\)$/', $value, $m) && isset($map[$m[1]])) {
        return $map[$m[1]];
    }
    return null;
}
