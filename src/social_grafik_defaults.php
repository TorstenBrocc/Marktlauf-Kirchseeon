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
