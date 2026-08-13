<?php
/**
 * Newsletter-Generator (POST + CSRF), JSON-Antwort.
 * Fakten -> gemeinsamer LLM-Client (Gemini/Mistral) -> HTML-Newsletter +
 * 3 Betreffzeilen. Rahmen/Layout kommen aus src/newsletter/ (Referenzdateien),
 * der LLM erzeugt nur Body-Text + Betreffzeilen. Spec: newsletter-engine-spec.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/llm_client.php';
require_once __DIR__ . '/../../src/newsletter/blocks.php';
require_once __DIR__ . '/../../src/brand_voice.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültige Anfrage.']);
    exit;
}

$provider = $_POST['provider'] ?? null;
if ($provider !== null && !in_array($provider, ['gemini', 'mistral'], true)) {
    $provider = null;
}

$refDir   = __DIR__ . '/../../src/newsletter/';
$template = @file_get_contents($refDir . '03_html_master_template.md') ?: '{{CONTENT}}';

// Marken-Farben aus der gemeinsamen Quelle einsetzen ({{token:--x}} -> Hex).
// E-Mail-tauglich: konkrete Hex serverseitig statt CSS-Variablen (Mail-Clients kennen
// kein var()). Unbekannte Tokens bleiben als Platzhalter stehen (fällt im Review auf,
// statt still eine falsche/leere Farbe zu setzen). Quelle: src/design_tokens.php (Spec E3).
require_once __DIR__ . '/../../src/design_tokens.php';
$tokenMap = ds_token_map();
$template = preg_replace_callback('/\{\{token:(--[\w-]+)\}\}/', static function (array $m) use ($tokenMap): string {
    return $tokenMap[$m[1]] ?? $m[0];
}, $template);

// --- Body: Baukasten-Blöcke (bevorzugt) ODER freier Fakten-Text (Fallback) ---
// `blocks` = JSON-Array [{type, fakten}, …] in Ausgabe-Reihenfolge (Baukasten-UI).
// Betreffzeilen werden in beiden Modi aus $fakten erzeugt.
$blocksJson = trim((string) ($_POST['blocks'] ?? ''));
$blocks     = $blocksJson !== '' ? json_decode($blocksJson, true) : null;

if (is_array($blocks) && $blocks !== []) {
    // Baukasten-Modus: je aktivem Block ein LLM-Call (src/newsletter/blocks.php).
    $normBlocks = array_map(static fn ($b): array => [
        'type'   => (string) ($b['type'] ?? ''),
        'fakten' => (string) ($b['fakten'] ?? ''),
    ], $blocks);
    $bodyHtml = newsletterAssembleBlocks($normBlocks, $provider);
    $fakten   = trim(implode("\n", array_column($normBlocks, 'fakten')));
    if ($fakten === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Bitte in mindestens einem Block Fakten/Inhalte eingeben.']);
        exit;
    }
} else {
    // Freitext-Modus (rückwärtskompatibel).
    $fakten = trim($_POST['fakten'] ?? '');
    if ($fakten === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Bitte zuerst Fakten/Inhalte für den Newsletter eingeben.']);
        exit;
    }
    $bodyPrompt = brandVoiceSystem('newsletter') . "\n\n"
        . "AUFGABE: Erzeuge NUR den HTML-Body (Fließtext) aus den Fakten: erlaubt sind <p>, <h2>, "
        . "<ul>/<li>, <a href>, <strong>. KEIN <html>/<head>/<body>, keine Inline-Styles, "
        . "keine Code-Fences, keine Erklärung. Nur die genannten Fakten verwenden.";
    $bodyHtml = trim(llmGenerate($bodyPrompt, $fakten, $provider));
    // evtl. Code-Fences entfernen, falls das Modell sie doch setzt
    $bodyHtml = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $bodyHtml);
}

// --- Betreffzeilen ---
$subjectPrompt = brandVoiceSystem('newsletter') . "\n\n"
    . "AUFGABE: Gib GENAU 3 Betreffzeilen aus, je eine pro Zeile, ohne Nummerierung, "
    . "ohne Anführungszeichen, max. ~60 Zeichen. Nur die genannten Fakten verwenden.";
$subjectRaw = llmGenerate($subjectPrompt, $fakten, $provider);
$subjects = [];
foreach (preg_split('/\r?\n/', (string) $subjectRaw) as $line) {
    // führende Aufzählung ("1." / "1)" / "- " / "* ") entfernen …
    $line = preg_replace('/^\s*(?:\d+[.)]\s*|[-*•]\s*)/', '', (string) $line);
    // … dann umschließende Anführungszeichen/Leerraum beidseitig (interne Punkte bleiben)
    $line = trim($line, " \t\"'“”");
    if ($line !== '') {
        $subjects[] = mb_substr($line, 0, 120);
    }
}
$subjects = array_slice($subjects, 0, 3);

if ($bodyHtml === '' && $subjects === []) {
    http_response_code(502);
    echo json_encode(['error' => 'KI-Antwort leer — API-Key prüfen oder Provider wechseln.']);
    exit;
}

$title = $subjects[0] ?? 'Newsletter Marktlauf Kirchseeon';
$html  = str_replace(['{{TITLE}}', '{{CONTENT}}'], [htmlspecialchars($title), $bodyHtml], $template);

echo json_encode(['html' => $html, 'subjects' => $subjects], JSON_UNESCAPED_UNICODE);
