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

$fakten = trim($_POST['fakten'] ?? '');
if ($fakten === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Bitte zuerst Fakten/Inhalte für den Newsletter eingeben.']);
    exit;
}

$refDir   = __DIR__ . '/../../src/newsletter/';
$identity = @file_get_contents($refDir . '01_identity.md') ?: '';
$style    = @file_get_contents($refDir . '02_style.md') ?: '';
$template = @file_get_contents($refDir . '03_html_master_template.md') ?: '{{CONTENT}}';

// --- Body (HTML-Fragment) ---
$bodyPrompt = "Du schreibst den Inhalt eines Vereins-Newsletters.\n\n"
    . "IDENTITÄT:\n" . $identity . "\n\nSTIL:\n" . $style . "\n\n"
    . "Erzeuge NUR den HTML-Body (Fließtext) aus den Fakten: erlaubt sind <p>, <h2>, "
    . "<ul>/<li>, <a href>, <strong>. KEIN <html>/<head>/<body>, keine Inline-Styles, "
    . "keine Code-Fences, keine Erklärung. Nur die genannten Fakten verwenden.";
$bodyHtml = trim(llmGenerate($bodyPrompt, $fakten, $provider));
// evtl. Code-Fences entfernen, falls das Modell sie doch setzt
$bodyHtml = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $bodyHtml);

// --- Betreffzeilen ---
$subjectPrompt = "Du bist Newsletter-Redakteur für den ATSV Kirchseeon (Marktlauf Kirchseeon).\n"
    . $style . "\n\nGib GENAU 3 Betreffzeilen aus, je eine pro Zeile, ohne Nummerierung, "
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
