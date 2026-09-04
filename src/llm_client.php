<?php
/**
 * LLM-Bridge: Gemini (Google AI) + Mistral via rohem PHP-cURL.
 *
 * Verifizierte API-Shapes (2026-07-13):
 *   Gemini  POST https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=KEY
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

/** Gemini-Modell (v1beta). Zentral, damit ein Versionswechsel eine Ein-Zeilen-Sache ist. */
const GEMINI_MODEL = 'gemini-3.6-flash';

/**
 * Groq-Modell (OpenAI-kompatibel). llama-3.3-70b ist "Enterprise" und auf unserem Free-Key
 * NICHT verfuegbar (HTTP 404, live geprueft 2026-09-04) -> gpt-oss-120b (OpenAIs grosses
 * offenes Modell, fuer den Key freigeschaltet, HTTP 200). Ein-Zeilen-Swap-Alternativen:
 * 'openai/gpt-oss-20b' (schneller), 'qwen/qwen3.8-27b'.
 */
const GROQ_MODEL = 'openai/gpt-oss-120b';

/**
 * Klartext-Grund, warum die letzte LLM-Antwort leer blieb (HTTP-Status/Key-Fehler,
 * Safety-Block, finishReason). Fuer die Admin-Fehlermeldung — nicht fuer Endnutzer.
 * Aufruf mit Argument setzt, ohne liest. Jeder Generate-Aufruf setzt zu Beginn zurueck.
 */
function llmLastError(?string $set = null): string
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

/**
 * Aktiven Provider aus einstellungen lesen (Default: gemini).
 */
function llmActiveProvider(PDO $pdo): string
{
    $stmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'llm_provider' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $provider = $row['value'] ?? 'gemini';
    return in_array($provider, ['gemini', 'mistral', 'groq'], true) ? $provider : 'gemini';
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

    // Aktiver Provider zuerst, danach die anderen als automatischer Fallback:
    // Limit/Ausfall eines Providers soll die Generierung nicht komplett blockieren
    // (Lehre 2026-09-04: Mistral-429 + falsches Gemini-Modell = Totalausfall).
    $praeferenz  = ['gemini', 'groq', 'mistral'];
    $reihenfolge = array_values(array_unique(array_merge([$provider], $praeferenz)));

    $fehler = [];
    foreach ($reihenfolge as $i => $p) {
        $text = match ($p) {
            'mistral' => llmGenerateMistral($systemPrompt, $userInput),
            'groq'    => llmGenerateGroq($systemPrompt, $userInput),
            default   => llmGenerateGemini($systemPrompt, $userInput),
        };
        if ($text !== '') {
            if ($i > 0) {
                logError('llmGenerate: Fallback auf "' . $p . '" erfolgreich, nachdem "'
                    . $reihenfolge[0] . '" ausfiel (' . ($fehler[$reihenfolge[0]] ?? 'leer') . ').');
            }
            return $text;
        }
        $fehler[$p] = llmLastError();
    }

    // Kein Provider lieferte Text -> sprechende Sammelmeldung fuer die Admin-Ansicht.
    $teile = [];
    foreach ($reihenfolge as $p) {
        $teile[] = $p . ': ' . ($fehler[$p] ?: 'leer');
    }
    llmLastError('Kein KI-Provider verfuegbar (' . implode(' | ', $teile) . ').');
    return '';
}

function llmGenerateGemini(string $systemPrompt, string $userInput): string
{
    llmLastError('');
    $config = getConfig();
    $apiKey = $config['gemini_api_key'] ?? '';
    if ($apiKey === '') {
        llmLastError('Gemini-API-Key fehlt (storage/config.php: gemini_api_key).');
        logError('llmGenerateGemini: gemini_api_key nicht konfiguriert');
        return '';
    }

    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . urlencode($apiKey);
    $body = json_encode([
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userInput]]],
        ],
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'generationConfig' => [
            'maxOutputTokens' => 1200,
            'temperature'     => 0.8,
            // Gemini 3.x steuert Thinking ueber thinkingLevel (minimal|low|medium|high),
            // NICHT mehr ueber das Legacy-thinkingBudget — das gibt sonst HTTP 400
            // INVALID_ARGUMENT (Vorfall 2026-09-04). 'low' laesst etwas Nachdenken zu
            // (bessere Formulierung) ohne das Output-Budget zu sprengen — mit 90-s-Timeout
            // vertretbar (TT 2026-09-04, Qualitaets-Nudge). Doku:
            // https://ai.google.dev/gemini-api/docs/generate-content/thinking
            'thinkingConfig'  => ['thinkingLevel' => 'low'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    // Bekanntes Gemini-Verhalten: HTTP 200 mit fehlenden parts / leerem Text tritt
    // sporadisch auf (finishReason STOP ohne Inhalt). Einmal wiederholen, bevor wir
    // aufgeben; ein inhaltlicher Block (SAFETY o. ä.) bricht sofort ab.
    for ($versuch = 1; $versuch <= 2; $versuch++) {
        $raw = llmCurlPost($url, $body, ['Content-Type: application/json']);
        if ($raw === null) {
            return ''; // Grund hat llmCurlPost gesetzt (HTTP-Status, Key-Fehler, Netzwerk)
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            llmLastError('Gemini-Antwort nicht lesbar (JSON-Fehler).');
            logError('llmGenerateGemini: JSON-Parse-Fehler: ' . $e->getMessage() . ' — ' . substr($raw, 0, 300));
            return '';
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text !== '') {
            return $text;
        }

        // Leer: Grund bestimmen. blockReason (Prompt geblockt) hat Vorrang vor finishReason.
        $grund = $data['promptFeedback']['blockReason'] ?? ($data['candidates'][0]['finishReason'] ?? '');
        if (in_array($grund, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) {
            llmLastError('Gemini hat den Text blockiert (' . $grund . ') — Fakten/Anweisung anpassen.');
            logError('llmGenerateGemini: blockiert (' . $grund . ') — ' . substr($raw, 0, 300));
            return ''; // Wiederholen zwecklos
        }
        llmLastError('Gemini lieferte keinen Text' . ($grund !== '' ? ' (finishReason=' . $grund . ')' : '') . '.');
        logError('llmGenerateGemini: leere Antwort (Versuch ' . $versuch . '/2, ' . ($grund ?: 'ohne finishReason') . ') — ' . substr($raw, 0, 300));
    }

    return '';
}

function llmGenerateMistral(string $systemPrompt, string $userInput): string
{
    llmLastError('');
    $config = getConfig();
    $apiKey = $config['mistral_api_key'] ?? '';
    if ($apiKey === '') {
        llmLastError('Mistral-API-Key fehlt (storage/config.php: mistral_api_key).');
        logError('llmGenerateMistral: mistral_api_key nicht konfiguriert');
        return '';
    }

    // Selbst-Drosselung: Mistral-Free erlaubt nur 1 Request/Sekunde (admin.mistral.ai
    // /plateforme/limits, mistral-small = 1,00 RPS). Beim Generieren feuern wir zwei
    // Calls kurz hintereinander (Presse + Social) -> der zweite lief sonst in HTTP 429
    // (Vorfall 2026-09-04). Mindestabstand 1,1 s zwischen zwei Mistral-Calls desselben
    // Requests einhalten; greift nur, wenn Mistral zweimal drankommt (statisch je Prozess).
    static $letzterMistralCall = 0.0;
    if ($letzterMistralCall > 0.0) {
        $abstand = microtime(true) - $letzterMistralCall;
        if ($abstand < 1.1) {
            usleep((int) ((1.1 - $abstand) * 1_000_000));
        }
    }
    $letzterMistralCall = microtime(true);

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
            llmLastError('Mistral lieferte keinen Text.');
            logError('llmGenerateMistral: leere Antwort — ' . substr($raw, 0, 300));
        }
        return $text;
    } catch (JsonException $e) {
        llmLastError('Mistral-Antwort nicht lesbar (JSON-Fehler).');
        logError('llmGenerateMistral: JSON-Parse-Fehler: ' . $e->getMessage() . ' — ' . substr($raw, 0, 300));
        return '';
    }
}

function llmGenerateGroq(string $systemPrompt, string $userInput): string
{
    llmLastError('');
    $config = getConfig();
    $apiKey = $config['groq_api_key'] ?? '';
    if ($apiKey === '') {
        llmLastError('Groq-API-Key fehlt (storage/config.php: groq_api_key).');
        logError('llmGenerateGroq: groq_api_key nicht konfiguriert');
        return '';
    }

    // OpenAI-kompatibel (https://console.groq.com/docs/openai) — gleiche Body-Form wie Mistral.
    $url  = 'https://api.groq.com/openai/v1/chat/completions';
    $body = json_encode([
        'model'    => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userInput],
        ],
        'max_tokens'  => 1200,
        'temperature' => 0.8,
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
            llmLastError('Groq lieferte keinen Text.');
            logError('llmGenerateGroq: leere Antwort — ' . substr($raw, 0, 300));
        }
        return $text;
    } catch (JsonException $e) {
        llmLastError('Groq-Antwort nicht lesbar (JSON-Fehler).');
        logError('llmGenerateGroq: JSON-Parse-Fehler: ' . $e->getMessage() . ' — ' . substr($raw, 0, 300));
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
        CURLOPT_CONNECTTIMEOUT => 10,
        // Reasoning-Modelle (Gemini 3.x) "denken" sichtbar; 30 s reichten nicht und
        // rissen den Auto-Entwurf beim Oeffnen (Vorfall 2026-09-04, "timed out after
        // 30004 ms"). 90 s je Call; PHP max_execution_time auf Strato = 240 s deckt die
        // zwei sequentiellen Calls (Presse + Social) ab.
        CURLOPT_TIMEOUT        => 90,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        llmLastError('Netzwerkfehler beim Provider-Aufruf (' . $curlErr . ').');
        logError('llmCurlPost: cURL-Fehler: ' . $curlErr . ' — URL: ' . $url);
        return null;
    }

    if ($httpCode === 429) {
        llmLastError('Provider-Limit erreicht (HTTP 429) — kurz warten und erneut versuchen.');
        logError('llmCurlPost: Rate-Limit (429) — URL: ' . $url);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        // Fehlermeldung des Providers herausziehen (Gemini/Mistral: {"error":{"message":...}})
        $msg = '';
        $err = json_decode((string)$raw, true);
        if (is_array($err)) {
            $msg = (string) ($err['error']['message'] ?? ($err['message'] ?? ''));
        }
        $hint = ($httpCode === 400 || $httpCode === 401 || $httpCode === 403)
            ? ' — API-Key ungültig oder ohne Berechtigung.' : '';
        llmLastError('Provider-Fehler HTTP ' . $httpCode . ($msg !== '' ? ': ' . $msg : '') . $hint);
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
AUFGABE: Verfasse einen fertigen, lebendigen Social-Media-Post (Instagram/Facebook) zum unten
genannten Anlass. Emotion vor Fakten, weniger ist mehr: trag einen menschlichen Moment oder ein
Gefühl, kein Datenblatt. Die Fakten/Stichpunkte sind nur dein Rohmaterial — forme daraus einen
echten Post. KEINE Aneinanderreihung oder Aufzählung der Fakten.
- Erste Zeile = ein starker Hook von höchstens ~125 Zeichen, der zum Weiterlesen zieht; er steht
  ganz vorn (keine Überschrift, kein „Anlass: …"). Nur die ersten ~125 Zeichen zeigt die App vor
  dem „… mehr" — die erste Zeile entscheidet über das Weiterlesen.
- Danach flüssiger Fließtext in der Vereins-Stimme, der die Fakten natürlich einwebt.
- Schließe mit GENAU EINEM zum Anlass passenden Handlungsaufruf, natürlich eingewoben — nicht
  stapeln, kein Baukasten-Anhängsel. Wähle den einen, der hier am stärksten zieht (z. B. anmelden,
  mitlaufen, teilen, speichern, in den Kommentaren markieren).
- Benenne einen Link kanal-bewusst: die Instagram-Caption ist nicht klickbar, Facebook schon.
  Nenne deshalb das Ziel sprachlich (z. B. „Anmeldung auf atsv-kirchseeon-marktlauf.de", „Link in
  der Bio" oder „QR-Code auf dem Bild") statt „klick hier/oben".
Beachte zusätzliche Anweisungen des Nutzers, falls vorhanden. Beginne direkt mit dem Post-Text,
ohne Einleitung oder Erklärung. Schreibe KEINE Hashtags — sie werden separat ergänzt. Erfinde
keine Daten, Uhrzeiten oder Orte: nutze ausschließlich die Angaben aus Eckdaten und Fakten; fehlt
eine Angabe, lass sie weg.
PROMPT;
    return brandVoiceSystem('social') . "\n\n" . $task;
}
