<?php
/**
 * Brand-Voice-Lader — die EINE Tonalitäts-Quelle für alle Text-Generatoren.
 *
 * Liest `src/brand/voice.md` (VOICE.md-Muster) und liefert die verbatim in Prompts
 * injizierbaren Blöcke: die harten Regeln (für JEDEN Text) + Kanal-Deltas
 * (newsletter/presse/social). Newsletter-Blöcke, `llmPromptPress()` und
 * `llmPromptSocial()` prependen `brandVoiceRules()` + ihr Kanal-Delta, statt eigene
 * Tonalitäts-Prosa zu führen. Spec: intern/social-ds-voice-wp-spec.md.
 */

declare(strict_types=1);

/** Pfad zur kanonischen Voice-Datei. */
function brandVoicePath(): string
{
    return __DIR__ . '/brand/voice.md';
}

/** Inhalt zwischen `<!-- MARKER -->` und `<!-- /MARKER -->` (getrimmt); '' wenn nicht gefunden. */
function brandVoiceExtract(string $marker): string
{
    $raw = @file_get_contents(brandVoicePath());
    if ($raw === false) {
        return '';
    }
    $m = preg_quote($marker, '/');
    if (preg_match('/<!--\s*' . $m . '\s*-->(.*?)<!--\s*\/' . $m . '\s*-->/s', $raw, $hit)) {
        return trim($hit[1]);
    }
    return '';
}

/** Harte Regeln — gehören an den Anfang JEDES Text-Prompts. */
function brandVoiceRules(): string
{
    return brandVoiceExtract('voice:rules');
}

/** Kanal-Delta (newsletter|presse|social). Leerstring bei unbekanntem Kanal. */
function brandVoiceChannel(string $kanal): string
{
    $k = preg_replace('/[^a-z]/', '', strtolower($kanal));
    return brandVoiceExtract('voice:channel:' . $k);
}

/**
 * Fertiger System-Prompt-Kopf für einen Kanal: harte Regeln + Kanal-Delta.
 * Die aufrufende Stelle hängt danach ihre aufgaben-spezifische Anweisung an.
 */
function brandVoiceSystem(string $kanal): string
{
    $rules   = brandVoiceRules();
    $channel = brandVoiceChannel($kanal);
    $parts   = [];
    if ($rules !== '') {
        $parts[] = "MARKEN-STIMME (verbindlich):\n" . $rules;
    }
    if ($channel !== '') {
        $parts[] = "KANAL-VORGABEN:\n" . $channel;
    }
    return implode("\n\n", $parts);
}

/** Ganzer Voice-Text (für den DS-Browser). */
function brandVoiceFull(): string
{
    return (string) (@file_get_contents(brandVoicePath()) ?: '');
}
