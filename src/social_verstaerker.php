<?php
/**
 * Verstaerker-Handgriffe — EINE Quelle (Post-Wirkung-Spec S6 / 5.B). Wird an drei Stellen
 * gerendert: Erfolgspanel im Post-Detail (orga/social_post.php, Schritt 4), Post-live-Mail
 * (orga/api/post_dispatch.php) und die vorwaertsgerichtete Ausbau-Liste. An einer Stelle
 * aendern -> alle ziehen mit.
 */

declare(strict_types=1);

/**
 * „Erste Stunde" — was den frisch veroeffentlichten Post sofort traegt (Algorithmus belohnt
 * schnelle Interaktion). Reihenfolge = Prioritaet.
 *
 * @return list<string>
 */
function socialVerstaerkerErsteStunde(): array
{
    return [
        'Post liken und mit 1 Kommentar anschieben (Frage/Emoji reicht).',
        'In die eigene Instagram-Story teilen (gern mit Sticker/Reaktion).',
        'Link an Familie & Lauffreunde weiterschicken — „Sends" zaehlen beim Algorithmus am staerksten.',
        'Falls du in lokalen Facebook-Gruppen bist: dort teilen (Regeln beachten, eigener Anmoderationssatz).',
        'Kommentare, die du siehst, schnell und freundlich beantworten.',
    ];
}

/**
 * „Reichweite ausbauen" — vorwaertsgerichtete Handgriffe, die ueber die erste Stunde
 * hinaus wirken (teils manuelle Meta-Handgriffe, bewusst als Check-Liste).
 *
 * @return list<string>
 */
function socialVerstaerkerAusbau(): array
{
    return [
        'Reels: roher 10–20-s-Handy-Clip vom Renntag ODER Foto-Slideshow-Reel (Fotos + Musik) — staerkster Reichweiten-Hebel fuer einen kleinen Account.',
        'Carousel fuer Save-Themen (mehrere Bilder) — gewinnt Speichern/Engagement.',
        'IG-Story mit Link-Sticker koppeln (seit 2026 fuer alle Accounts) — macht den Link auf Instagram klickbar.',
        'Standort-Tag setzen: Kirchseeon / JEK — bringt lokale Reichweite.',
        'Interaktive Story-Sticker: Countdown-Sticker zum Renntag, Umfrage — Interaktion treibt den Algorithmus.',
        'Collab-Post mit einem Partner/Sponsor (Co-Autor) — erscheint bei beiden Publika.',
    ];
}

/**
 * Verfahrensweise fuer Sponsor-/Partner-Posts ("corporate posts") — zusammenhaengende
 * Anleitung (Post-Wirkung-Spec 5.C). Wird auf den Sponsoren-Themen im Post-Detail angezeigt,
 * damit die Schritte an EINER Stelle stehen statt verstreut. Reihenfolge = Ablauf.
 *
 * @return list<string>
 */
function socialSponsorPostAnleitung(): array
{
    return [
        'Thema wählen: „Dank an Sponsoren & Partner" (alle gesammelt) oder „Sponsorenvorstellung" (ein Spotlight).',
        'Text generieren lassen: die KI stellt den Bezug Kernkompetenz ↔ Marktlauf selbst her — pflege dafür die Kernkompetenz am Sponsor (Sponsor-Maske).',
        'Einheitlich-warm bleiben: „mit Unterstützung von …" / „Danke an …" — keine Stufen-Titel („Gold-Sponsor") öffentlich.',
        'Logo kommt automatisch aus dem Sponsor-Datensatz (eine Quelle wie die Website-Rotation) — höchstens 3 Logos, sonst wirkt es überladen.',
        'Nach dem Posten: Sponsor markieren (@handle) und — für doppelte Reichweite — als Collab-Co-Autor einladen (der Sponsor muss annehmen).',
        'Personen-Fotos nur mit Freigabe; Emotion vor Aufzählung — der Post soll für sich sprechen.',
    ];
}

/**
 * Sponsor-Tag-/Collab-Erinnerungen (S5-Kopplung): nur auf Sponsoren-Themen und nur fuer
 * Sponsoren mit gepflegtem Social-Handle. Der Meta-Handgriff (markieren/als Collab einladen)
 * bleibt manuell. Leer, wenn keine Handles vorliegen (oder Migration 077 noch offen).
 *
 * @return list<string>
 */
function socialVerstaerkerSponsorTags(PDO $pdo, string $anlassKey): array
{
    if (!in_array($anlassKey, ['sponsoren_dank', 'sponsorenvorstellung', 'renntag', 'danke'], true)) {
        return [];
    }
    if (!function_exists('socialSponsoren')) {
        require_once __DIR__ . '/social_sponsoren.php';
    }
    $out = [];
    foreach (socialSponsoren($pdo) as $s) {
        if ($s['social_handle'] !== '') {
            $out[] = $s['firma'] . ' taggen / als Collab einladen: ' . $s['social_handle'];
        }
    }
    return $out;
}
