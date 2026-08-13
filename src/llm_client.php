<?php
/**
 * LLM-Bridge: Gemini (Google AI) + Mistral via rohem PHP-cURL.
 *
 * Verifizierte API-Shapes (2026-07-13):
 *   Gemini  POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=KEY
 *           Body: {"contents":[{"role":"user","parts":[{"text":"..."}]}],"systemInstruction":{"parts":[{"text":"..."}]}}
 *           Response: candidates[0].content.parts[0].text
 *
 *   Mistral POST https://api.mistral.ai/v1/chat/completions
 *           Header: Authorization: Bearer KEY
 *           Body: {"model":"mistral-small-latest","messages":[{"role":"system","content":"..."},{"role":"user","content":"..."}]}
 *           Response: choices[0].message.content
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/brand_voice.php';

/**
 * Aktiven Provider aus einstellungen lesen (Default: gemini).
 */
function llmActiveProvider(PDO $pdo): string
{
    $stmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'llm_provider' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $provider = $row['value'] ?? 'gemini';
    return in_array($provider, ['gemini', 'mistral'], true) ? $provider : 'gemini';
}

/**
 * Text-Completion via gewähltem Provider.
 *
 * @throws RuntimeException bei Konfigurationsfehler
 * @return string Generierter Text, oder '' bei API-Fehler (Fehler geloggt)
 */
function llmGenerate(string $systemPrompt, string $userInput, ?string $provider = null): string
{
    if ($provider === null) {
        try {
            $pdo = getDbConnection();
            $provider = llmActiveProvider($pdo);
        } catch (Throwable $e) {
            logError('llmGenerate: DB-Fehler beim Provider-Lookup: ' . $e->getMessage());
            $provider = 'gemini';
        }
    }

    return match ($provider) {
        'mistral' => llmGenerateMistral($systemPrompt, $userInput),
        default   => llmGenerateGemini($systemPrompt, $userInput),
    };
}

function llmGenerateGemini(string $systemPrompt, string $userInput): string
{
    $config = getConfig();
    $apiKey = $config['gemini_api_key'] ?? '';
    if ($apiKey === '') {
        logError('llmGenerateGemini: gemini_api_key nicht konfiguriert');
        return '';
    }

    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);
    $body = json_encode([
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userInput]]],
        ],
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'generationConfig' => [
            'maxOutputTokens' => 1200,
            'temperature'     => 0.7,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $raw = llmCurlPost($url, $body, ['Content-Type: application/json']);
    if ($raw === null) {
        return '';
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            logError('llmGenerateGemini: leere Antwort — ' . substr($raw, 0, 300));
        }
        return $text;
    } catch (JsonException $e) {
        logError('llmGenerateGemini: JSON-Parse-Fehler: ' . $e->getMessage() . ' — ' . substr($raw, 0, 300));
        return '';
    }
}

function llmGenerateMistral(string $systemPrompt, string $userInput): string
{
    $config = getConfig();
    $apiKey = $config['mistral_api_key'] ?? '';
    if ($apiKey === '') {
        logError('llmGenerateMistral: mistral_api_key nicht konfiguriert');
        return '';
    }

    $url  = 'https://api.mistral.ai/v1/chat/completions';
    $body = json_encode([
        'model'    => 'mistral-small-latest',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userInput],
        ],
        'max_tokens'  => 1200,
        'temperature' => 0.7,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $raw = llmCurlPost($url, $body, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    if ($raw === null) {
        return '';
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $text = $data['choices'][0]['message']['content'] ?? '';
        if ($text === '') {
            logError('llmGenerateMistral: leere Antwort — ' . substr($raw, 0, 300));
        }
        return $text;
    } catch (JsonException $e) {
        logError('llmGenerateMistral: JSON-Parse-Fehler: ' . $e->getMessage() . ' — ' . substr($raw, 0, 300));
        return '';
    }
}

/**
 * Roher cURL-POST. Gibt Response-Body zurück, null bei HTTP-Fehler oder cURL-Fehler.
 *
 * @param string[] $headers
 */
function llmCurlPost(string $url, string $body, array $headers): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        logError('llmCurlPost: cURL-Fehler: ' . $curlErr . ' — URL: ' . $url);
        return null;
    }

    if ($httpCode === 429) {
        logError('llmCurlPost: Rate-Limit (429) — URL: ' . $url);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        logError('llmCurlPost: HTTP ' . $httpCode . ' — URL: ' . $url . ' — Body: ' . substr((string)$raw, 0, 300));
        return null;
    }

    return (string)$raw;
}

// ---------------------------------------------------------------------------
// System-Prompts
// ---------------------------------------------------------------------------

function llmPromptPress(): string
{
    $task = <<<PROMPT
AUFGABE: Verfasse einen sachlichen, informativen Pressebeitrag (Stil einer lokalen Tageszeitung im
Landkreis Ebersberg) passend zum unten genannten Anlass und den Fakten/Stichpunkten. Beachte
zusätzliche Anweisungen des Nutzers, falls vorhanden. Beginne direkt mit dem Text, ohne Überschrift.
PROMPT;
    return brandVoiceSystem('presse') . "\n\n" . $task;
}

function llmPromptSocial(): string
{
    $task = <<<PROMPT
AUFGABE: Verfasse einen Social-Media-Post (Instagram/Facebook) passend zum unten genannten Anlass
und den Fakten/Stichpunkten. Beachte zusätzliche Anweisungen des Nutzers, falls vorhanden. Beginne
direkt mit dem Post-Text, ohne Einleitung oder Erklärung.
PROMPT;
    return brandVoiceSystem('social') . "\n\n" . $task;
}
