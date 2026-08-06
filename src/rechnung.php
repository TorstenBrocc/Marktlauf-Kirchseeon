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
        'ust_satz'      => 19.0,               // Regelsteuersatz für aktive Werbeleistung
        'kassier_email' => 'kassier@atsv-kirchseeon.de', // Empfänger der Anstoß-Mail

        // Zahlungskonto im Rechnungsblock: Kreissparkasse, Kontoinhaber ohne Abteilung
        'kontoinhaber'  => 'ATSV Kirchseeon e. V.',
        'iban'          => 'DE23 7025 0150 0000 4438 95',
        'bank'          => 'Kreissparkasse München Starnberg Ebersberg',

        // Footer-Kontaktdaten (aus dem Vereinsbriefkopf)
        'telefon'       => '08091/9313',
        'telefax'       => '08091/563966',
        'web'           => 'www.atsv-kirchseeon.de',
        'email'         => 'atsv@atsv-kirchseeon.de',
        'burozeiten'    => 'Bürozeiten: Dienstag 18–19 Uhr',
        // Footer-Bankverbindungen (beide)
        'bank1_name'    => 'Kreissparkasse München Starnberg Ebersberg',
        'bank1_iban'    => 'DE23 7025 0150 0000 4438 95',
        'bank2_name'    => 'Raiffeisen-Volksbank Ebersberg',
        'bank2_iban'    => 'DE06 7016 9450 0003 7176 58',
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
 * Kanonische Paket-Definition (Fallback). Die maßgebliche Quelle ist die Einstellung
 * `sponsoring_pakete` (JSON, im Sponsorenbrief-Bereich editierbar) — dieselben Werte wie
 * die `paketeDefaults` in sponsor_briefe.php. Preise sind NETTO zu verstehen.
 */
function sponsoringPaketeDefaults(): array
{
    return [
        'hauptsponsor' => ['name' => 'Hauptsponsor', 'investition' => 'auf Anfrage',
            'highlights' => 'Zentraler Partner des Events, maximale Sichtbarkeit auf allen Kanälen'],
        'gold' => ['name' => 'Gold', 'investition' => '1.000 €',
            'highlights' => 'Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fläche, 5 Startplätze, Moderations-Erwähnungen'],
        'silber' => ['name' => 'Silber', 'investition' => '500 €',
            'highlights' => 'Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze'],
        'bronze' => ['name' => 'Bronze', 'investition' => '250 €',
            'highlights' => 'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben'],
    ];
}

/**
 * Paket-Definition als Map key=>['name','investition','highlights'], gemergt aus der
 * Einstellung `sponsoring_pakete` (maßgeblich) über die Defaults.
 */
function sponsoringPakete(?PDO $pdo = null): array
{
    $pakete = sponsoringPaketeDefaults();
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'sponsoring_pakete'");
            $json = $stmt ? $stmt->fetchColumn() : false;
            if ($json) {
                $decoded = json_decode((string) $json, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $p) {
                        if (!empty($p['key'])) {
                            $pakete[$p['key']] = array_merge($pakete[$p['key']] ?? [], $p);
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            // Einstellung/Tabelle nicht verfügbar -> Defaults
        }
    }
    return $pakete;
}

/**
 * Parst einen investition-String ("1.000 €") zu einem Float; "auf Anfrage" -> null.
 * Nur ganze Euro-Beträge erwartet (Paket-Listenpreise). Ob der Wert netto oder brutto
 * ist, entscheidet der globale Schalter (rechnungGlobalBrutto), nicht diese Funktion.
 */
function paketBetrag(?string $investition): ?float
{
    $digits = preg_replace('/[^0-9]/', '', (string) $investition);
    if ($digits === '' || $digits === null) {
        return null;
    }
    return (float) $digits;
}

/**
 * Globaler Schalter „Sponsoringbeträge sind brutto" (Einstellung `rechnung_betraege_brutto`).
 * Default false = netto (+USt). Nächstes Jahr im Admin-Bereich ohne Code umstellbar.
 */
function rechnungGlobalBrutto(?PDO $pdo = null): bool
{
    if ($pdo === null) {
        return false;
    }
    try {
        $v = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'rechnung_betraege_brutto'")->fetchColumn();
        return $v === '1';
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Rechnet aus einem Betrag Netto/USt/Brutto — je nachdem, ob der Betrag brutto oder netto ist.
 */
function rechnungBetraegeAusBetrag(float $betrag, bool $istBrutto, ?float $ustSatz = null): array
{
    $satz = $ustSatz ?? rechnungStammdaten()['ust_satz'];
    if ($istBrutto) {
        $brutto = round($betrag, 2);
        $netto  = round($brutto / (1 + $satz / 100), 2);
        return ['netto' => $netto, 'ust_satz' => $satz, 'ust_betrag' => round($brutto - $netto, 2), 'brutto' => $brutto];
    }
    return rechnungBetraege($betrag, $satz);
}

/**
 * Konkreter Leistungstext aus der Paket-Definition (§14: nicht bloß "Sponsoring").
 */
function paketLeistung(array $paketDef, string $zeitraum): string
{
    $z    = trim($zeitraum) !== '' ? $zeitraum : leistungszeitraumDefault();
    $name = trim((string) ($paketDef['name'] ?? '')) ?: 'Sponsoring';
    $high = trim((string) ($paketDef['highlights'] ?? ''));
    if ($high === '') {
        return "$name-Sponsoring $z gemäß unserer Vereinbarung.";
    }
    return "$name-Sponsoring $z: $high.";
}

/**
 * Netto/USt/Brutto für einen Sponsor. Reihenfolge:
 *   1) abweichender Betrag (netto — oder brutto, wenn rechnung_betrag_brutto)
 *   2) Paket-Listenpreis (netto)
 * Wirft InvalidArgumentException, wenn kein Betrag ermittelbar ist.
 */
function rechnungBetraegeFuerSponsor(array $sponsor, array $paketDef, ?float $ustSatz = null, bool $globalBrutto = false): array
{
    $satz     = $ustSatz ?? rechnungStammdaten()['ust_satz'];
    $override = $sponsor['rechnung_betrag'] ?? null;

    // Abweichender Betrag pro Sponsor: eigener brutto-Haken (übersteuert den globalen Default)
    if ($override !== null && $override !== '' && (float) $override > 0) {
        return rechnungBetraegeAusBetrag((float) $override, !empty($sponsor['rechnung_betrag_brutto']), $satz);
    }

    // Paket-Listenpreis: netto oder brutto je nach globalem Schalter
    $preis = paketBetrag($paketDef['investition'] ?? null);
    if ($preis === null || $preis <= 0) {
        throw new InvalidArgumentException('Betrag (Paket ohne Festpreis — bitte abweichenden Betrag setzen)');
    }
    return rechnungBetraegeAusBetrag($preis, $globalBrutto, $satz);
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
