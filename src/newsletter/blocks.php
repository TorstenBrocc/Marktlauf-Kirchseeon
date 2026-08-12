<?php
/**
 * Newsletter-Baukasten — Block-Katalog + Generierung je Block.
 *
 * Fester Marken-Rahmen bleibt (Master-Template, {{CONTENT}}); variabel ist der Inhalt als
 * geordnete, an-/abschaltbare Blöcke. Je aktivem Block EIN eigener LLM-Call (Fakten + Stil aus
 * der EINEN Design-Quelle: 01_identity.md + 02_style.md). Die Fragmente ergeben zusammengesetzt
 * den {{CONTENT}}-Body. Spec: intern/newsletter-baukasten-spec.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/../llm_client.php';   // llmGenerate()
require_once __DIR__ . '/../logger.php';

/**
 * Block-Katalog. Reihenfolge = Vorschlag in der UI; die Ausgabe-Reihenfolge steuert der Nutzer.
 *
 * @return array<string, array{label:string, hint:string, instruction:string}>
 */
function newsletterBlockCatalog(): array
{
    return [
        'intro' => [
            'label'       => 'Intro / Grußwort',
            'hint'        => 'Kurze, herzliche Einleitung — worum geht es in dieser Ausgabe?',
            'instruction' => 'Schreibe ein kurzes, herzliches Intro/Grußwort (2–4 Sätze) als ein <p>, '
                . 'optional <strong>. Keine Überschrift.',
        ],
        'news' => [
            'label'       => 'News',
            'hint'        => 'Eine Neuigkeit: was ist passiert oder steht an?',
            'instruction' => 'Schreibe einen News-Abschnitt: eine <h2>-Überschrift + 1–2 <p>. '
                . 'Sachlich, lokal, konkret.',
        ],
        'termine' => [
            'label'       => 'Termine',
            'hint'        => 'Datum + Ereignis je Zeile (z. B. „20.09.2026 · Renntag").',
            'instruction' => 'Formatiere die Termine als <h2>Termine</h2> + <ul> mit einem <li> je '
                . 'Termin. Datum mit dem Marken-Trennzeichen „·" vom Ereignis trennen. Nichts erfinden.',
        ],
        'sponsoren_dank' => [
            'label'       => 'Sponsoren-Dank',
            'hint'        => 'Wem danken? Namen/Anlass.',
            'instruction' => 'Schreibe einen kurzen, warmen Dank an die genannten Sponsoren/Unterstützer '
                . '(eine <h2> + ein <p>). Nur die genannten Namen verwenden.',
        ],
        'cta' => [
            'label'       => 'Aktions-Button (CTA)',
            'hint'        => 'Aktion und Ziel-URL, Format „Text | https://…" (z. B. „Jetzt anmelden | https://…").',
            'instruction' => 'Erzeuge GENAU einen Handlungsaufruf als Link im Format '
                . '<p><a href="URL">Text</a></p>. Text und URL stammen aus den Fakten (Muster '
                . '„Text | URL"). Kein weiterer Fließtext.',
        ],
    ];
}

/**
 * Ein Block zu einem HTML-Fragment generieren.
 * Leere Fakten -> '' (Block wird übersprungen); unbekannter Typ -> '' (geloggt).
 * Nutzt Identität/Stil aus src/newsletter/ als gemeinsame Design-Quelle.
 */
function newsletterRenderBlock(string $type, string $fakten, ?string $provider = null): string
{
    $catalog = newsletterBlockCatalog();
    if (!isset($catalog[$type])) {
        logError('newsletterRenderBlock: unbekannter Blocktyp ' . $type);
        return '';
    }
    $fakten = trim($fakten);
    if ($fakten === '') {
        return '';
    }

    $refDir   = __DIR__ . '/';
    $identity = @file_get_contents($refDir . '01_identity.md') ?: '';
    $style    = @file_get_contents($refDir . '02_style.md') ?: '';

    $system = "Du schreibst einen Abschnitt eines Vereins-Newsletters.\n\n"
        . "IDENTITÄT:\n" . $identity . "\n\nSTIL:\n" . $style . "\n\n"
        . "AUFGABE FÜR DIESEN ABSCHNITT:\n" . $catalog[$type]['instruction'] . "\n\n"
        . "Erlaubt sind ausschließlich <p>, <h2>, <ul>/<li>, <a href>, <strong>. "
        . "KEIN <html>/<head>/<body>, keine Inline-Styles, keine Code-Fences, keine Erklärung. "
        . "Nur die genannten Fakten verwenden.";

    $html = trim(llmGenerate($system, $fakten, $provider));
    // evtl. Code-Fences entfernen (wie im Einzel-Generator newsletter_generate.php)
    $html = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', (string) $html);
    return trim((string) $html);
}

/**
 * Geordnete, aktive Blöcke zu einem {{CONTENT}}-Body zusammensetzen.
 *
 * @param list<array{type:string, fakten:string}> $blocks in Ausgabe-Reihenfolge
 * @return string zusammengesetztes HTML ('' wenn kein Block Inhalt liefert)
 */
function newsletterAssembleBlocks(array $blocks, ?string $provider = null): string
{
    $parts = [];
    foreach ($blocks as $b) {
        $frag = newsletterRenderBlock((string) ($b['type'] ?? ''), (string) ($b['fakten'] ?? ''), $provider);
        if ($frag !== '') {
            $parts[] = $frag;
        }
    }
    return implode("\n", $parts);
}
