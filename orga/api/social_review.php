<?php
/**
 * KI-Gegenpruefung der Entwuerfe (POST + CSRF) — nur Admin/Orga.
 * Zweiter LLM-Pass: prueft Social-Post + Presse-Artikel gegen die verbindlichen
 * Voice-Regeln (src/brand/voice.md) und den Nutzer-Kontext. Cross-Check:
 * bevorzugt prueft der jeweils ANDERE Provider als der, der generiert hat;
 * ist der nicht verfuegbar (Key/Quota), prueft derselbe.
 * Response: {"review":"...","provider":"gemini|mistral"} oder {"error":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
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

$article = trim($_POST['article'] ?? '');
$social  = trim($_POST['social'] ?? '');
if ($article === '' && $social === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Kein Entwurf zum Gegenprüfen — bitte zuerst generieren oder eingeben.']);
    exit;
}

// Provider des Generats bestimmen; geprueft wird bevorzugt vom anderen
$genProvider = $_POST['provider'] ?? '';
if (!in_array($genProvider, ['gemini', 'mistral'], true)) {
    try {
        $genProvider = llmActiveProvider(getDbConnection());
    } catch (Throwable $e) {
        logError('social_review: Provider-Lookup fehlgeschlagen: ' . $e->getMessage());
        $genProvider = 'gemini';
    }
}
$reviewer = $genProvider === 'gemini' ? 'mistral' : 'gemini';

// System-Prompt: strenger Lektor, prueft gegen dieselbe Voice-Quelle wie die Generierung
$sysParts = [
    'Du bist strenger Lektor des ATSV Kirchseeon Marktlauf. Prüfe die unten stehenden '
    . 'Entwürfe gegen die folgenden verbindlichen Regeln und auf sachliche Plausibilität '
    . 'gegenüber dem angegebenen Kontext (Zahlen, Daten, Behauptungen). Verfasse KEINE Neufassung.',
];
$rules = brandVoiceRules();
if ($rules !== '') {
    $sysParts[] = "MARKEN-STIMME (verbindlich):\n" . $rules;
}
if ($social !== '' && ($delta = brandVoiceChannel('social')) !== '') {
    $sysParts[] = "KANAL-VORGABEN SOCIAL-POST:\n" . $delta;
}
if ($article !== '' && ($delta = brandVoiceChannel('presse')) !== '') {
    $sysParts[] = "KANAL-VORGABEN PRESSE-ARTIKEL:\n" . $delta;
}
// Meta-Richtlinien + Social-Best-Practices — nur fuer den Social-Post (IG/FB), nicht Presse.
if ($social !== '') {
    $sysParts[] = "META-RICHTLINIEN & BEST PRACTICES (nur Social-Post, Instagram/Facebook):\n"
        . "Prüfe zusätzlich, ob der Social-Post gegen Metas Content-/Community- oder Werbe-"
        . "Richtlinien verstoßen könnte: irreführende oder unbelegte Behauptungen, Superlative/"
        . "Heilsversprechen, „Engagement-Bait“ (Aufruf zu Like/Teilen/Markieren nur um Reichweite), "
        . "fremde Marken-/Urheberrechte, personenbezogene Daten Dritter ohne Einwilligung. "
        . "Prüfe außerdem gängige Best Practices für Vereins-Posts: starker Hook in Zeile 1, "
        . "genau ein Call-to-Action, gute Lesbarkeit (kurze Sätze, Emojis gezielt statt in Ketten), "
        . "auf Instagram kein „klick den Link“ (Caption-Links sind dort nicht klickbar). "
        . "WICHTIG: Dein Wissen über Metas Richtlinien kann veraltet sein — kennzeichne Unsicheres "
        . "als Hinweis, nicht als Tatsache. Dies ersetzt keine offizielle Freigabe.";
}
$sysParts[] = 'ANTWORTFORMAT: je Entwurf eine kurze Liste konkreter Beanstandungen — '
    . 'verletzte Regel, Zitat der betroffenen Stelle, knapper Korrekturvorschlag. '
    . 'Ist ein Entwurf regelkonform und plausibel, schreibe das ausdrücklich. '
    . 'Antworte auf Deutsch, ohne Einleitung.';
$system = implode("\n\n", $sysParts);

// Nutzer-Kontext + Entwuerfe
$parts = [];
$eckdaten = socialEckdaten($pdo ?? getDbConnection());
if ($eckdaten !== '') {
    $parts[] = "Verbindliche Eckdaten (Abweichungen sind Fehler und müssen beanstandet werden):\n" . $eckdaten;
}
$anlaesse = socialAnlaesse();
$anlass = $_POST['anlass'] ?? '';
if (isset($anlaesse[$anlass])) {
    $parts[] = 'Kontext — Anlass: ' . $anlaesse[$anlass]['prompt'];
    $ausschluss = trim((string) ($anlaesse[$anlass]['ausschluss'] ?? ''));
    if ($ausschluss !== '') {
        $parts[] = "Kontext — was NICHT rein darf (Verstoß beanstanden):\n" . $ausschluss;
    }
}
$stichpunkte = trim($_POST['stichpunkte'] ?? '');
if ($stichpunkte !== '') {
    $parts[] = "Kontext — Fakten/Stichpunkte:\n" . $stichpunkte;
}
if ($social !== '') {
    $parts[] = "ENTWURF SOCIAL-POST:\n" . $social;
}
if ($article !== '') {
    $parts[] = "ENTWURF PRESSE-ARTIKEL:\n" . $article;
}
$userInput = implode("\n\n", $parts);

$review = llmGenerate($system, $userInput, $reviewer);
$used   = $reviewer;
if ($review === '') {
    // Cross-Provider nicht verfuegbar (Key/Quota) → derselbe Provider prueft
    $review = llmGenerate($system, $userInput, $genProvider);
    $used   = $genProvider;
}
if ($review === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Gegenprüfung fehlgeschlagen — API-Keys/Quota prüfen oder Provider wechseln.']);
    exit;
}

// Ergebnis am Post persistieren (Post-Detail sendet post_id; alter Orchestrator nicht)
$postId = (int) ($_POST['post_id'] ?? 0);
if ($postId > 0) {
    try {
        getDbConnection()->prepare(
            'UPDATE post_race_contents
                SET geprueft_am = NOW(), geprueft_provider = :p, geprueft_ergebnis = :r
              WHERE id = :id'
        )->execute(['p' => $used, 'r' => $review, 'id' => $postId]);
    } catch (Throwable $e) {
        logError('social_review: Persistieren fehlgeschlagen: ' . $e->getMessage());
    }
}

echo json_encode(['review' => $review, 'provider' => $used], JSON_UNESCAPED_UNICODE);
