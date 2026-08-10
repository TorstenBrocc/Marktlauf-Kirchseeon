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
 * die Paket-Einstellungen in orga/anschreiben_einstellungen.php. Preise sind NETTO zu verstehen.
 */
function sponsoringPaketeDefaults(): array
{
    return [
        'hauptsponsor' => ['name' => 'Hauptsponsor', 'investition' => 'auf Anfrage',
            'highlights' => 'Zentraler Partner des Events, maximale Sichtbarkeit auf allen Kanälen'],
        'gold' => ['name' => 'Gold', 'investition' => '1.000 €',
            'highlights' => 'Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fläche, 5 Startplätze, Moderations-Erwähnungen'],
        'silber' => ['name' => 'Silber', 'investition' => '500 €',
            'highlights' => 'Logo auf Startnummer, Namensnennung Presse, 3 Startplätze'],
        'bronze' => ['name' => 'Bronze', 'investition' => '250 €',
            'highlights' => 'Logo auf Website, Urkunde, Dankesschreiben, 1 Startplatz'],
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
 * Leistungsposten für die Rechnung — aus dem Leistungskatalog (`sponsorLeistungenKatalog()`),
 * nicht aus den Paket-Freitexten. Der Katalog ist die einzige Stelle, die weiß, was ein Paket
 * wirklich enthält: die Stufen sind kumulativ (Silber = Bronze + Silber, Gold = Bronze + Silber
 * + Gold, Hauptsponsor = alles), und die Startplätze summieren sich nicht, sondern haben je
 * Stufe eine eigene Stückzahl.
 *
 * Maßgeblich ist die Leistungs-Matrix des Sponsors: existiert dort eine Zeile, gewinnt der
 * gesetzte Haken — eine abgewählte Leistung erscheint nicht auf der Rechnung, eine zusätzlich
 * vereinbarte schon. Ohne Zeile gilt der Paket-Default.
 *
 * Zusammengefasst wird nur, was im Katalog eine gemeinsame `gruppe` trägt (siehe
 * `sponsorLeistungGruppen()`) — etwa die Logo-Platzierungen zu „Logo auf Website, auf
 * Startnummer". Positionen ohne Gruppe bleiben eigene Posten; aus dem Label wird nichts
 * abgeleitet. Eine Gruppe steht an der Stelle ihres ersten Mitglieds in der Katalogreihenfolge.
 *
 * @param array<string,array{vereinbart:bool,freitext:string}> $state Matrix-Zustand des Sponsors
 * @return array<int,string>
 */
function rechnungLeistungsposten(?string $typ, array $state = []): array
{
    $gruppen  = sponsorLeistungGruppen();
    $posten   = [];        // Position im Ergebnis => Text
    $sammler  = [];        // Gruppe => Liste der Kurznamen
    $gruppePos = [];       // Gruppe => Position, an der sie ausgegeben wird

    foreach (sponsorLeistungenKatalog() as $pos) {
        $key  = $pos['key'];
        $gilt = isset($state[$key]) ? $state[$key]['vereinbart'] : sponsorLeistungGilt($pos, $typ);
        if (!$gilt) {
            continue;
        }

        $gruppe = $pos['gruppe'] ?? '';
        if ($gruppe !== '' && isset($gruppen[$gruppe])) {
            if (!isset($sammler[$gruppe])) {
                $sammler[$gruppe]   = [];
                $gruppePos[$gruppe] = count($posten);
                $posten[]           = ''; // Platzhalter, wird unten gefüllt
            }
            $sammler[$gruppe][] = [
                'rang' => $pos['gruppe_rang'] ?? PHP_INT_MAX,
                'text' => $pos['kurz'] ?? $pos['label'],
            ];
            continue;
        }

        if ($key === 'startplaetze') {
            $anzahl = sponsorStartplaetzeMenge($pos, $typ);
            // null = individuelle Menge (Hauptsponsor) -> ohne Zahl nennen, nichts erfinden.
            // Geschütztes Leerzeichen, damit die Zahl nicht allein am Zeilenende stehen bleibt.
            $posten[] = $anzahl === null
                ? 'Startplätze'
                : $anzahl . "\u{00A0}" . ($anzahl === 1 ? 'Startplatz' : 'Startplätze');
            continue;
        }

        // Freitexte der Matrix bleiben bewusst außen vor: bei den Startplätzen steht dort der
        // RaceResult-Gutscheincode, der nichts auf einer Rechnung zu suchen hat.
        $posten[] = $pos['label'];
    }

    // Gruppen zusammensetzen: "Logo auf Website, Urkunde & Startnummer". Geschützte Leerzeichen
    // halten das Präfix am ersten Ort und das "&" an seinem Vorgänger, damit beim Umbruch keine
    // Zeile mit "auf" oder "&" endet bzw. beginnt.
    foreach ($sammler as $gruppe => $teile) {
        $g = $gruppen[$gruppe];
        // Reihenfolge innerhalb der Gruppe aus `gruppe_rang`, nicht aus der Katalogreihenfolge.
        usort($teile, static fn (array $a, array $b): int => $a['rang'] <=> $b['rang']);
        $teile   = array_column($teile, 'text');
        $letzter = array_pop($teile);
        $liste   = $teile === []
            ? $letzter
            : implode($g['join'], $teile) . "\u{00A0}" . ltrim($g['join_letzter']) . $letzter;
        $posten[$gruppePos[$gruppe]] = rtrim($g['prefix']) . "\u{00A0}" . $liste;
    }
    return $posten;
}

/**
 * Konkreter Leistungstext für die Rechnung (§14: nicht bloß "Sponsoring") — das gebuchte Paket
 * vollständig ausgeschrieben, so wie es in der Leistungs-Matrix steht. Posten sind mit " · "
 * getrennt, damit die Kommas innerhalb der Logo-Aufzählung erhalten bleiben. Vor dem Trennpunkt
 * steht ein geschütztes Leerzeichen, damit er beim Umbruch am Zeilenende hängt statt am Anfang
 * der nächsten Zeile zu landen.
 */
function paketLeistung(array $pakete, ?string $paketKey, string $zeitraum, array $state = []): string
{
    $z    = trim($zeitraum) !== '' ? $zeitraum : leistungszeitraumDefault();
    $name = trim((string) ($pakete[$paketKey ?? '']['name'] ?? ''));
    // Ohne Paketnamen (z. B. Sachsponsor) bleibt es bei "Sponsoring" — nicht "Sponsoring-Sponsoring".
    $bezeichnung = $name !== '' ? "$name-Sponsoring" : 'Sponsoring';
    $posten      = rechnungLeistungsposten($paketKey, $state);
    if ($posten === []) {
        return "$bezeichnung $z gemäß unserer Vereinbarung.";
    }
    return "$bezeichnung $z: " . implode("\u{00A0}· ", $posten) . '.';
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
