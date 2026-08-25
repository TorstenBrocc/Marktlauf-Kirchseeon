<?php
/**
 * Grafik-Defaults je Thema — EINE Quelle fuer die Ableitungen, die aus dem Anlass
 * folgen (Post-Wirkung-Spec §4/§5): Layout, CTA, QR-Ziel. Frueher als match() direkt
 * in orga/vorlagen.php; hierher gehoben, damit die Kaskaden-Anzeige im Post-Detail
 * (orga/social_post.php, „Schritt-0-Thema-Karte") dieselbe Wahrheit anzeigt, statt sie
 * zu duplizieren. Reine, DB-freie Funktionen.
 */

declare(strict_types=1);

/** Layout-Vorwahl je Thema: Renntag -> Ergebnis-Card, Anmeldung -> Poster, sonst Themen-Vorlage. */
function socialLayoutKey(string $anlassKey): string
{
    return match ($anlassKey) {
        'renntag'         => 'renntag',
        'anmeldung_offen' => 'anmeldung',
        default           => 'thema',
    };
}

/** Menschenlesbares Label zum Layout-Key (fuer die Kaskaden-Anzeige). */
function socialLayoutLabel(string $layoutKey): string
{
    return match ($layoutKey) {
        'renntag'   => 'Renntag-Ergebnis',
        'anmeldung' => 'Anmeldungs-Poster',
        default     => 'Themen-Post',
    };
}

/** CTA-Vorwahl je Thema (Grafik-Button + Kaskaden-Anzeige). */
function socialCtaDefault(string $anlassKey): string
{
    if (in_array($anlassKey, ['helfer', 'helfer_gesucht'], true)) {
        return 'Jetzt Helfer werden!';
    }
    if (in_array($anlassKey, ['danke', 'renntag', 'eventtag'], true)) {
        return 'Danke fürs Dabeisein!';
    }
    return 'Jetzt anmelden!';
}

/**
 * QR-Ziel-Vorwahl je Thema. Helfer-Themen zielen auf den Token-Link, wenn ein aktiver
 * Helfer-Token existiert ($helferTokenVorhanden), sonst auf die Website.
 */
function socialQrKey(string $anlassKey, bool $helferTokenVorhanden = false): string
{
    return match ($anlassKey) {
        'helfer', 'helfer_gesucht'     => $helferTokenVorhanden ? 'helfer' : 'website',
        'renntag', 'danke', 'eventtag' => 'website',
        default                        => 'anmeldung',
    };
}

/** Menschenlesbares Label zum QR-Ziel-Key (fuer die Kaskaden-Anzeige). */
function socialQrLabel(string $qrKey): string
{
    return match ($qrKey) {
        'anmeldung'     => 'Anmeldung (Website)',
        'registrierung' => 'RaceResult-Registrierung',
        'website'       => 'Website-Startseite',
        'helfer'        => 'Helfer-Anmeldung',
        default         => 'Website',
    };
}

/**
 * Tatsaechliche Ziel-URL zum QR-Ziel-Key (Spiegel der Zuordnung in orga/vorlagen.php:104-121).
 * Genutzt fuer die Vorbelegung des ersten Kommentars (Link + Hashtags). $appUrl kommt aus
 * getConfig()['app']['url']; $helferToken nur setzen, wenn ein aktiver Token vorliegt.
 */
function socialQrUrl(string $qrKey, string $appUrl, ?string $helferToken = null): string
{
    $appUrl = rtrim($appUrl, '/');
    return match ($qrKey) {
        'anmeldung'     => $appUrl . '/#anmeldung',
        'registrierung' => 'https://my.raceresult.com/412617/registration',
        'helfer'        => $helferToken !== null && $helferToken !== ''
            ? $appUrl . '/helfer-anmeldung.php?token=' . rawurlencode($helferToken)
            : $appUrl,
        default         => $appUrl, // website
    };
}

/**
 * Format-Vorwahl je Thema (Post-Wirkung-Spec 5.B/5.D): Story 9:16 fuer die klaren
 * Story-Themen (Reminder/Live), sonst Portrait 4:5 als Standard-Feed. Quadrat bleibt
 * manueller Fallback. Rueckgabe = Key aus RT_FORMATS (portrait|grid34|square|story).
 */
function socialFormatDefault(string $anlassKey): string
{
    return match ($anlassKey) {
        'countdown_7', 'morgen', 'eventtag' => 'story',
        default                             => 'portrait',
    };
}

/**
 * Bild-Default je Thema (Post-Wirkung-Spec 5.C.10): 'foto' fuer Stimmungs-/Beweis-Themen
 * (echtes Foto traegt den Post), 'grafik' fuer Termin-/Fakten-Themen (Grafik allein).
 */
function socialBildDefault(string $anlassKey): string
{
    return in_array($anlassKey, ['renntag', 'danke', 'eventtag', 'strecke'], true) ? 'foto' : 'grafik';
}

/**
 * Logo-Fuehrung je Thema (Post-Wirkung-Spec 5.C.11): welche der festen Logos die Grafik
 * fuehren. Rueckgabe = Keys aus {marktlauf, atsv, gemeinde}, Reihenfolge = Fuehrung.
 * Sponsor-Logos kommen separat (S5, nur auf Sponsoren-Themen). Deckel 3 (5.C.12) greift
 * zusaetzlich in der Bedienung.
 *  - Gemeinde nur im Kooperations-Kontext (Ankuendigung, Nachhaltigkeit, Energie-&-Umwelttag).
 *  - ATSV fuehrt allein auf Vereins-/Danke-/Helfer-/Sponsoren-Themen.
 *  - sonst Marktlauf-Wortmarke + ATSV (Event-/Termin-Grafiken).
 */
function socialLogoFuehrung(string $anlassKey): array
{
    if (in_array($anlassKey, ['ankuendigung', 'nachhaltigkeit', 'energie_umwelttag'], true)) {
        return ['marktlauf', 'atsv', 'gemeinde'];
    }
    if (in_array($anlassKey, ['helfer', 'helfer_gesucht', 'sponsoren_dank', 'renntag', 'sponsorenvorstellung', 'eventtag', 'danke'], true)) {
        return ['atsv'];
    }
    return ['marktlauf', 'atsv'];
}
