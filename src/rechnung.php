<?php
/**
 * Sponsoring-Rechnung — Stammdaten und Hilfsfunktionen.
 *
 * Die Vereins-Stammdaten stehen bewusst hier im Repo (nicht in der geheimen
 * storage/config.php): sie erscheinen ohnehin auf jeder Rechnung und sind damit
 * nicht vertraulich. So muss niemand die Server-Config anfassen.
 */

declare(strict_types=1);

// sponsorTypRang(): kanonische Rangordnung der Pakete (bronze < silber < gold < hauptsponsor).
// Der kumulative Leistungstext braucht sie — die Ordnung wird nicht zweitgeführt.
require_once __DIR__ . '/sponsor_leistungen.php';

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

        // Footer-Kontaktdaten (aus dem Vereinsbriefkopf). Mail + Web sind bewusst die
        // Marktlauf-Adressen, nicht die des Gesamtvereins: die Rechnung kommt aus der
        // Abteilung, und Rückfragen sollen dort landen.
        'telefon'       => '08091/9313',
        'telefax'       => '08091/563966',
        'web'           => 'https://atsv-kirchseeon-marktlauf.de',
        'email'         => 'info@atsv-kirchseeon-marktlauf.de',
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
            'highlights' => 'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben, 1 Startplatz'],
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
 * Nur ganze Euro-Beträge erwartet (Paket-Listenpreise). Paketpreise sind immer netto;
 * ein abweichender Brutto-Fall wird ausschließlich pro Sponsor gesetzt (rechnung_betrag_brutto).
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
 * Einzelposten eines Pakets, kumulativ über alle enthaltenen Stufen: Silber = Bronze + Silber,
 * Gold = Bronze + Silber + Gold, Hauptsponsor = alles. Genau so sind die Paket-Highlights
 * gepflegt — die höheren Stufen beginnen dort mit "+" ("zusätzlich zum kleineren Paket"), was in
 * der Brief-Tabelle passt, auf der Rechnung aber allein stand. Das "+" fällt hier weg.
 *
 * Einzige Ausnahme von der Summierung sind die Startplätze: sie stapeln sich nicht, es gilt die
 * Stückzahl der höchsten Stufe, die eine nennt (Silber 3 statt 1+3). Der Posten wandert dabei
 * ans Ende der Liste.
 *
 * @param array<string,array{name?:string,highlights?:string}> $pakete alle Pakete (sponsoringPakete())
 * @return array<int,string> Posten in Reihenfolge Bronze → gebuchtes Paket
 */
function paketLeistungsposten(array $pakete, ?string $paketKey): array
{
    $zielRang = sponsorTypRang($paketKey);
    if ($zielRang <= 0) {
        return [];
    }

    $posten     = [];
    $startplatz = null;
    $gesehen    = [];
    foreach (['bronze', 'silber', 'gold', 'hauptsponsor'] as $stufe) {
        if (sponsorTypRang($stufe) > $zielRang) {
            break;
        }
        // Führendes "+" der kumulativen Schreibweise entfernen, dann an Kommas zerlegen.
        $high = ltrim(trim((string) ($pakete[$stufe]['highlights'] ?? '')), '+ ');
        foreach (explode(',', $high) as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            if (preg_match('/startpl(atz|ätze)/iu', $seg) === 1) {
                $startplatz = $seg; // höhere Stufe ersetzt die niedrigere, statt zu addieren
                continue;
            }
            $key = mb_strtolower($seg);
            if (isset($gesehen[$key])) {
                continue; // dieselbe Leistung in zwei Stufen nur einmal nennen
            }
            $gesehen[$key] = true;
            $posten[]      = $seg;
        }
    }
    if ($startplatz !== null) {
        $posten[] = $startplatz;
    }
    return $posten;
}

/**
 * Konkreter Leistungstext für die Rechnung (§14: nicht bloß "Sponsoring") — das gebuchte Paket
 * vollständig ausgeschrieben, inklusive der Leistungen der kleineren Stufen.
 */
function paketLeistung(array $pakete, ?string $paketKey, string $zeitraum): string
{
    $z      = trim($zeitraum) !== '' ? $zeitraum : leistungszeitraumDefault();
    $name   = trim((string) ($pakete[$paketKey ?? '']['name'] ?? '')) ?: 'Sponsoring';
    $posten = paketLeistungsposten($pakete, $paketKey);
    if ($posten === []) {
        return "$name-Sponsoring $z gemäß unserer Vereinbarung.";
    }
    return "$name-Sponsoring $z: " . implode(', ', $posten) . '.';
}

/**
 * Netto/USt/Brutto für einen Sponsor. Der Betrag kommt immer aus dem gebuchten Paket und ist
 * netto — außer der Sponsor hat den Brutto-Haken gesetzt ($istBrutto), dann wird die USt aus dem
 * Paketpreis herausgerechnet. Wirft InvalidArgumentException, wenn kein Betrag hinterlegt ist.
 */
function rechnungBetraegeFuerSponsor(array $sponsor, array $paketDef, ?float $ustSatz = null, bool $istBrutto = false): array
{
    $satz = $ustSatz ?? rechnungStammdaten()['ust_satz'];

    // Betrag kommt aus dem Betrag-Feld des Sponsors (summe) — typgesteuert vorbefüllt
    // (Gold/Silber/Bronze aus Pakettarif, Hauptsponsor individuell). netto, sofern kein Brutto-Haken.
    $preis = (float) ($sponsor['summe'] ?? 0);
    if ($preis <= 0) {
        throw new InvalidArgumentException('Betrag (kein Betrag am Sponsor hinterlegt)');
    }
    return rechnungBetraegeAusBetrag($preis, $istBrutto, $satz);
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
