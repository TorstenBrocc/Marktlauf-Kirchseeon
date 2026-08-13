<?php
/**
 * Design-System-Downloads (Punkt 6 #3): einzelne Element-/Vorlagen-Downloads + Readme/Tonalität.
 *
 * Login-geschützt (Orga). GET, read-only — kein CSRF (wie orga/api/sponsor_export.php).
 * Reicht genau die Dateien des DS-Pakets aus, per Whitelist über `kind`:
 *   readme|voice     → die Markdown-Briefe (feste Quelle, `file` ignoriert)
 *   snippet          → self-contained HTML-Block roh (design-system/snippets/)
 *   template         → WebP-Vorschau roh (design-system/templates/)
 *   guideline|component → zu EINER offline öffenbaren Datei verschmolzen (ds_selfcontain_html)
 *
 * Kein Pfad-Ausbruch: `file` wird auf basename reduziert und muss real im Zielordner liegen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';                 // requireLogin (Redirect, wenn nicht eingeloggt)
require_once __DIR__ . '/../../src/helpers.php';     // content_disposition()

$webroot = (string) realpath(__DIR__ . '/../..');    // website/
$dsRoot  = $webroot . '/design-system';

$kind = (string) ($_GET['kind'] ?? '');
$file = basename((string) ($_GET['file'] ?? ''));    // Pfad-Traversal neutralisieren

/** 404 + Ende. */
function ds_dl_fail(string $msg): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

/** Rohe Datei als Attachment streamen. */
function ds_dl_stream(string $absPath, string $downloadName, string $ctype): never
{
    header('Content-Type: ' . $ctype);
    content_disposition($downloadName);
    header('Content-Length: ' . (string) filesize($absPath));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($absPath);
    exit;
}

/** Fertigen String als Attachment senden. */
function ds_dl_string(string $body, string $downloadName, string $ctype): never
{
    header('Content-Type: ' . $ctype);
    content_disposition($downloadName);
    header('Content-Length: ' . (string) strlen($body));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}

switch ($kind) {
    // Markdown-Briefe — feste Quelle, `file` bewusst ignoriert.
    case 'readme':
        $src = $dsRoot . '/readme.md';
        is_file($src) || ds_dl_fail('Readme nicht gefunden.');
        ds_dl_stream($src, 'marktlauf-design-system-readme.md', 'text/markdown; charset=utf-8');
        // no break (exit)

    case 'voice':
        $src = $webroot . '/src/brand/voice.md';
        is_file($src) || ds_dl_fail('Tonalitäts-Datei nicht gefunden.');
        ds_dl_stream($src, 'marktlauf-brand-voice.md', 'text/markdown; charset=utf-8');

    // Self-contained HTML-Snippet: roh (schon ohne externe Refs).
    case 'snippet':
        $src = $dsRoot . '/snippets/' . $file;
        (str_ends_with($file, '.html') && is_file($src)) || ds_dl_fail('Snippet nicht gefunden.');
        ds_dl_stream($src, $file, 'text/html; charset=utf-8');

    // Vorlagen-Vorschau: WebP roh (die editierbare Druckdatei lebt in gDrive, Punkt 3).
    case 'template':
        $src = $dsRoot . '/templates/' . $file;
        (str_ends_with($file, '.webp') && is_file($src)) || ds_dl_fail('Vorlage nicht gefunden.');
        ds_dl_stream($src, $file, 'image/webp');

    // Guideline / Komponente: zu einer offline öffenbaren Datei verschmelzen.
    case 'guideline':
    case 'component':
        require_once __DIR__ . '/../../src/ds_selfcontain.php';
        $dir = $kind === 'guideline' ? 'guidelines' : 'components';
        $src = $dsRoot . '/' . $dir . '/' . $file;
        (str_ends_with($file, '.html') && is_file($src)) || ds_dl_fail(ucfirst($kind) . ' nicht gefunden.');
        $body = ds_selfcontain_html($src, $webroot, $kind === 'component');
        $body !== '' || ds_dl_fail('Export fehlgeschlagen.');
        ds_dl_string($body, $file, 'text/html; charset=utf-8');

    default:
        ds_dl_fail('Unbekannter Download-Typ.');
}
