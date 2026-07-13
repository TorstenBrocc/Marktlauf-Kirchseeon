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
require_once __DIR__ . '/../../src/raceresult_mock.php';
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

$data   = raceResultMock();
$userInput = raceResultToPromptText($data);

$article = llmGenerate(llmPromptPress(),  $userInput, $provider);
$social  = llmGenerate(llmPromptSocial(), $userInput, $provider);

if ($article === '' && $social === '') {
    http_response_code(502);
    echo json_encode(['error' => 'KI-Antwort leer — API-Key prüfen oder Provider wechseln.']);
    exit;
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
