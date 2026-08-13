<?php
/**
 * Anlaesse/Themen der Social-Media-Strecke — EINE Quelle fuer Maske und APIs.
 * Die Maske (orga/social_orchestrator.php) rendert ihr Select hieraus,
 * social_generate.php nimmt die Prompt-Beschreibung, social_prompt.php
 * validiert gegen die Keys. Neue Anlaesse nur hier ergaenzen.
 */

declare(strict_types=1);

/**
 * @return array<string, array{gruppe: string, ui: string, prompt: string}>
 *   key => gruppe (Optgroup-Label), ui (kurzes Select-Label),
 *   prompt (Beschreibung des Anlasses fuer das LLM)
 */
function socialAnlaesse(): array
{
    return [
        // Standard
        'allgemein' => [
            'gruppe' => 'Standard',
            'ui'     => 'Allgemeiner Beitrag',
            'prompt' => 'Allgemeiner Vereins-/Event-Beitrag',
        ],
        'ankuendigung' => [
            'gruppe' => 'Standard',
            'ui'     => 'Ankündigung des Events',
            'prompt' => 'Ankündigung des Events (Vorschau, Aufruf zur Anmeldung)',
        ],
        'countdown' => [
            'gruppe' => 'Standard',
            'ui'     => 'Countdown / Vorfreude',
            'prompt' => 'Countdown / Vorfreude vor dem Event',
        ],
        'sponsoren_dank' => [
            'gruppe' => 'Standard',
            'ui'     => 'Dank an Sponsoren & Partner',
            'prompt' => 'Dank an Sponsoren und Partner',
        ],
        'helfer' => [
            'gruppe' => 'Standard',
            'ui'     => 'Helfer-Aufruf / -Dank',
            'prompt' => 'Helfer-Aufruf / Dank an Helfer',
        ],
        'renntag' => [
            'gruppe' => 'Standard',
            'ui'     => 'Renntag-Nachbericht (nutzt RaceResult-Daten)',
            'prompt' => 'Nachbericht zum Renntag (Ergebnisdaten siehe unten)',
        ],
        // Contentplan 2026
        'save_the_date' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Save the Date',
            'prompt' => 'Save the Date — Termin des Marktlaufs ankündigen, Vorfreude wecken',
        ],
        'warum_mitlaufen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Warum mitlaufen? (5 Gründe)',
            'prompt' => 'Warum mitlaufen? Fünf gute Gründe für die Teilnahme nennen',
        ],
        'strecke' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Strecke entdecken',
            'prompt' => 'Strecke entdecken — Streckenverlauf und Besonderheiten vorstellen',
        ],
        'nachhaltigkeit' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Nachhaltig laufen',
            'prompt' => 'Nachhaltig laufen — Umwelt- und Nachhaltigkeitsaspekte des Events',
        ],
        'anmeldung_offen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Anmeldung geöffnet',
            'prompt' => 'Anmeldung geöffnet — Aufruf, sich jetzt anzumelden',
        ],
        'helfer_gesucht' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Helfer gesucht',
            'prompt' => 'Helfer gesucht — Aufruf, sich als Helfer für das Event zu melden',
        ],
        'sponsorenvorstellung' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Sponsorenvorstellung',
            'prompt' => 'Sponsorenvorstellung — einen Sponsor/Partner des Events vorstellen',
        ],
        'countdown_30' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '30-Tage-Countdown',
            'prompt' => '30-Tage-Countdown — noch 30 Tage bis zum Event',
        ],
        'trainingstipp' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Trainingstipp',
            'prompt' => 'Trainingstipp für die Vorbereitung auf den Lauf',
        ],
        'energie_umwelttag' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Energie- & Umwelttag',
            'prompt' => 'Energie- & Umwelttag — Hinweis auf den Aktionstag rund um das Event',
        ],
        'countdown_7' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => '7-Tage-Countdown',
            'prompt' => '7-Tage-Countdown — noch eine Woche bis zum Event',
        ],
        'morgen' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Morgen geht\'s los',
            'prompt' => 'Morgen geht es los — letzter Aufruf am Vortag des Events',
        ],
        'eventtag' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Eventtag (live)',
            'prompt' => 'Eventtag — Live-Beitrag vom Renntag selbst',
        ],
        'danke' => [
            'gruppe' => 'Contentplan 2026',
            'ui'     => 'Danke / Rückblick',
            'prompt' => 'Danke & Rückblick nach dem Event',
        ],
    ];
}
