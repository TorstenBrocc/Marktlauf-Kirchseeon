<?php
/**
 * KI-Content generieren (POST + CSRF) — nur Admin/Orga.
 * Lädt Mock-Ergebnisdaten, baut Prompts, ruft LLM, gibt JSON zurück.
 * Response: {"article":"...","social":"..."} oder {"error":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/raceresult_client.php';
require_once __DIR__ . '/../../src/llm_client.php';
require_once __DIR__ . '/../../src/social_anlaesse.php';

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

// Allgemeiner Generator: Anlass + eigener Prompt + Stichpunkte + optional Renntag-Daten.
// Anlass-Katalog kommt aus src/social_anlaesse.php (eine Quelle fuer Maske + APIs).
$anlaesse = socialAnlaesse();
$anlass = $_POST['anlass'] ?? 'allgemein';
if (!isset($anlaesse[$anlass])) {
    $anlass = 'allgemein';
}
$stichpunkte = trim($_POST['stichpunkte'] ?? '');
$userPrompt  = trim($_POST['prompt'] ?? '');
$hashtags    = trim($_POST['hashtags'] ?? '');

$parts = ['Anlass: ' . $anlaesse[$anlass]['prompt']];
if ($anlass === 'renntag') {
    $parts[] = "Ergebnisdaten:\n" . raceResultToPromptText(raceResultData(getDbConnection()));
}
if ($stichpunkte !== '') {
    $parts[] = "Fakten/Stichpunkte:\n" . $stichpunkte;
}
if ($userPrompt !== '') {
    $parts[] = "Zusätzliche Anweisung des Nutzers:\n" . $userPrompt;
}
// Merkfeld (interne Notiz) nur auf Wunsch mitgeben — serverseitig aus den
// Einstellungen gelesen, damit der gespeicherte Stand zaehlt
if (($_POST['mit_merkfeld'] ?? '') === '1') {
    $stmt = getDbConnection()->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'social_merkfeld'");
    $stmt->execute();
    $merkfeld = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($merkfeld !== '') {
        $parts[] = "Interne Notizen (Merkfeld):\n" . $merkfeld;
    }
}
$userInput = implode("\n\n", $parts);

// Presse nur, wenn gewuenscht (Post-Detail sendet das Presse-Merkmal des Anlasses mit)
$mitPresse = ($_POST['mit_presse'] ?? '1') === '1';
$article = $mitPresse ? llmGenerate(llmPromptPress(), $userInput, $provider) : '';
$social  = llmGenerate(llmPromptSocial(), $userInput, $provider);

if ($article === '' && $social === '') {
    http_response_code(502);
    echo json_encode(['error' => 'KI-Antwort leer — API-Key prüfen oder Provider wechseln.']);
    exit;
}

// Hashtags an den Social-Post hängen (falls gepflegt und noch nicht enthalten)
if ($hashtags !== '' && $social !== '' && !str_contains($social, $hashtags)) {
    $social = rtrim($social) . "\n\n" . $hashtags;
}

echo json_encode(['article' => $article, 'social' => $social], JSON_UNESCAPED_UNICODE);

// ---------------------------------------------------------------------------

function raceResultToPromptText(array $data): string
{
    $e    = $data['event'];
    $g    = $data['gesamt'];
    $lines = [
        "Event: {$e['name']}, {$e['datum']}, {$e['ort']}",
        "Teilnehmer gesamt: {$g['teilnehmer']}, Finisher: {$g['finisher']}, Wetter: {$g['wetter']}",
    ];
    foreach ($data['rennen'] as $r) {
        $lines[] = "{$r['kategorie']} ({$r['teilnehmer']} TN): Sieger {$r['sieger']['name']} ({$r['sieger']['verein']}) {$r['sieger']['zeit']}"
            . (isset($r['siegerin']) ? ", Siegerin {$r['siegerin']['name']} ({$r['siegerin']['verein']}) {$r['siegerin']['zeit']}" : '');
    }
    if (!empty($data['highlight'])) {
        $lines[] = 'Highlight: ' . $data['highlight'];
    }
    return implode("\n", $lines);
}
