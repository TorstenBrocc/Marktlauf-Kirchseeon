<?php
/**
 * Sponsoring-Rechnung — Stammdaten und Hilfsfunktionen.
 *
 * Die Vereins-Stammdaten stehen bewusst hier im Repo (nicht in der geheimen
 * storage/config.php): sie erscheinen ohnehin auf jeder Rechnung und sind damit
 * nicht vertraulich. So muss niemand die Server-Config anfassen.
 */

declare(strict_types=1);

/**
 * Absender-/Vereins-Stammdaten für den Rechnungskopf und den Zahlungshinweis.
 * BIC bewusst nicht gesetzt: für SEPA-Inlandsüberweisungen genügt die IBAN,
 * und der Wert soll nicht geraten werden.
 */
function rechnungStammdaten(): array
{
    return [
        'verein'        => 'ATSV Kirchseeon e.V.',
        'abteilung'     => 'Abteilung Marktlauf',
        'strasse'       => 'Sportplatzweg 7',
        'plz'           => '85614',
        'ort'           => 'Kirchseeon',
        'ust_id'        => 'DE400543484',      // USt-IdNr.
        'steuernummer'  => '114/107/00133',    // Steuernummer
        'kontoinhaber'  => 'ATSV Kirchseeon e.V. – Abteilung Marktlauf',
        'iban'          => 'DE65 7025 0150 0000 4428 48',
        'bank'          => 'Kreissparkasse München Starnberg Ebersberg',
        'ust_satz'      => 19.0,               // Regelsteuersatz für aktive Werbeleistung
        'kassier_email' => 'kassier@atsv-kirchseeon.de', // Empfänger der Anstoß-Mail
    ];
}

/**
 * Standard-Leistungszeitraum für einen neuen Rechnungsentwurf, z. B. "Marktlauf 2026".
 */
function leistungszeitraumDefault(): string
{
    return 'Marktlauf ' . date('Y');
}

/**
 * Vorbelegter, konkreter Leistungstext je Paket (§14 verlangt eine konkrete
 * Leistungsbeschreibung — nicht bloß "Sponsoring"). Frei überschreibbar in der Maske.
 */
function paketLeistungDefault(?string $paket, string $zeitraum): string
{
    $z = trim($zeitraum) !== '' ? $zeitraum : leistungszeitraumDefault();
    switch ($paket) {
        case 'hauptsponsor':
            return "Hauptsponsoring $z: Logo auf Startnummer und Zieleinlauf-Banner, "
                 . "Bandenwerbung, Nennung auf Website und in den Social-Media-Kanälen.";
        case 'gold':
            return "Gold-Sponsoring $z: Bandenwerbung am Veranstaltungsgelände, "
                 . "Logo auf Website und in den Social-Media-Kanälen.";
        case 'silber':
            return "Silber-Sponsoring $z: Logo auf der Veranstaltungswebsite.";
        case 'bronze':
            return "Bronze-Sponsoring $z: namentliche Nennung auf der Veranstaltungswebsite.";
        default:
            return "Sponsoring $z gemäß unserer Vereinbarung.";
    }
}

/**
 * Prüft das Format der fortlaufenden Rechnungsnummer: NN-JJJJ
 * (1–4 Ziffern, Bindestrich, vierstelliges Jahr), z. B. "5-2026" oder "05-2026".
 */
function rechnungsnummerGueltig(string $nummer): bool
{
    return (bool) preg_match('/^\d{1,4}-\d{4}$/', trim($nummer));
}

/**
 * Rechnet aus dem Netto-Betrag (summe) USt und Brutto. summe gilt als NETTO.
 * Rückgabe: ['netto' => .., 'ust_satz' => .., 'ust_betrag' => .., 'brutto' => ..]
 */
function rechnungBetraege(float $netto, ?float $ustSatz = null): array
{
    $satz      = $ustSatz ?? rechnungStammdaten()['ust_satz'];
    $netto     = round($netto, 2);
    $ustBetrag = round($netto * $satz / 100, 2);
    $brutto    = round($netto + $ustBetrag, 2);

    return [
        'netto'      => $netto,
        'ust_satz'   => $satz,
        'ust_betrag' => $ustBetrag,
        'brutto'     => $brutto,
    ];
}
