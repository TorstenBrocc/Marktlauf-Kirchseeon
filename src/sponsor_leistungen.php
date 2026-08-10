<?php
/**
 * Sponsoring-Leistungen — Katalog + Zustands-Zugriff (Phase 2 der Modell-Vereinheitlichung).
 *
 * Der Katalog ist die strukturierte Fassung der bisherigen Freitext-„Highlights": je Position
 * ein Schlüssel, ein Label, eine Mindest-Stufe (kumulativ: Gold enthält Silber + Bronze) und ein
 * Zelltyp für die Matrix. Der Sponsorenbrief bleibt in Phase 2 unangetastet (eigene Freitext-Quelle);
 * die Vereinheitlichung folgt in Phase 3. Details: intern/sponsoring-modell-spec.md §c.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Ordnung der Sponsoring-Typen. sachsponsor = 0 (keine Leistungen), hauptsponsor = alles.
 */
function sponsorTypRang(?string $typ): int
{
    return match ($typ) {
        'bronze'       => 1,
        'silber'       => 2,
        'gold'         => 3,
        'hauptsponsor' => 4, // bekommt alle Positionen
        default        => 0, // sachsponsor / kein Typ → keine
    };
}

/**
 * Positionen, die auf der Rechnung zu einem Posten zusammengefasst werden. Die Zugehörigkeit
 * steht als `gruppe` an der Katalog-Position — sie wird NICHT aus dem Label geraten. Vorher
 * galt „alles, was mit 'Logo auf' beginnt", und damit landete das Streckenbanner in der
 * Logo-Zeile, obwohl es sachlich ein Banner war (TT, 2026-08-10). Eine Position ohne `gruppe`
 * bleibt ein eigener Posten — der Standardfall ist „nicht zusammenfassen".
 *
 * @return array<string, array{prefix:string, join:string, join_letzter:string}>
 */
function sponsorLeistungGruppen(): array
{
    // Aufzählung: Kommas zwischen allen, "&" vor dem letzten — "Logo auf Website, Urkunde &
    // Startnummer". Das Verbindungswort steht nur einmal im Präfix, nicht vor jedem Ort.
    return [
        'logo' => ['prefix' => 'Logo auf', 'join' => ', ', 'join_letzter' => ' & '],
    ];
}

/**
 * Leistungs-Katalog (Reihenfolge = Spaltenreihenfolge der Matrix).
 * typ: 'haken' | 'haken_text' | 'startplaetze'
 *
 * Optionale Felder:
 *   gruppe      – Zusammenfassung auf der Rechnung (siehe sponsorLeistungGruppen())
 *   kurz        – Bezeichnung innerhalb der Gruppe („Website" in „Logo auf Website & Urkunde")
 *   gruppe_rang – Reihenfolge innerhalb der Gruppe. Bewusst getrennt von der Katalogreihenfolge:
 *                 die Matrix-Spalten stehen nach Stufe, die Aufzählung auf der Rechnung folgt
 *                 der Lesbarkeit („Website, Startnummer & Urkunde", TT 2026-08-10)
 *   aktiv  – false = in dieser Saison nicht angeboten: erscheint weder in der Matrix noch auf
 *            der Rechnung, bleibt aber dokumentiert. Wieder anbieten = auf true setzen.
 *
 * @param bool $inklusiveInaktive true = auch nicht angebotene Positionen zurückgeben
 * @return array<int, array{key:string,label:string,min:string,typ:string,menge?:array<string,int>,gruppe?:string,kurz?:string,aktiv?:bool}>
 */
function sponsorLeistungenKatalog(bool $inklusiveInaktive = false): array
{
    $katalog = [
        // Entfallene Positionen (nicht wieder aufnehmen, ohne mit TT zu sprechen):
        //   Startertüten-Branding    – 2026-08-10 ersatzlos gestrichen.
        //   Logo auf Streckenbanner  – 2026-08-10 gestrichen; war zudem kein Logo-, sondern ein
        //                              Banner-Thema und wurde deshalb falsch zusammengefasst.
        ['key' => 'logo_website',     'label' => 'Logo auf Website',            'min' => 'bronze', 'typ' => 'haken',
         'gruppe' => 'logo', 'kurz' => 'Website', 'gruppe_rang' => 1],
        // Logo-Platzierung, keine eigenständige Leistung (TT, 2026-08-10): das Sponsorlogo
        // erscheint auf den Urkunden. Label deshalb "Logo auf Urkunde", damit Matrix und
        // Rechnung dasselbe sagen.
        ['key' => 'urkunde',          'label' => 'Logo auf Urkunde',            'min' => 'bronze', 'typ' => 'haken',
         'gruppe' => 'logo', 'kurz' => 'Urkunde', 'gruppe_rang' => 4],
        ['key' => 'dankesschreiben',  'label' => 'Dankesschreiben',             'min' => 'bronze', 'typ' => 'haken'],
        ['key' => 'logo_startnummer', 'label' => 'Logo auf Startnummer',        'min' => 'silber', 'typ' => 'haken',
         'gruppe' => 'logo', 'kurz' => 'Startnummer', 'gruppe_rang' => 2],
        ['key' => 'presse',           'label' => 'Namensnennung Presse',        'min' => 'silber', 'typ' => 'haken'],
        // 2026 nicht angeboten (kein Lauf-Shirt). Für ein Folgejahr genügt aktiv => true.
        ['key' => 'logo_shirt',       'label' => 'Logo auf Lauf-Shirt',         'min' => 'silber', 'typ' => 'haken',
         'gruppe' => 'logo', 'kurz' => 'Lauf-Shirt', 'gruppe_rang' => 3, 'aktiv' => false],
        ['key' => 'startplaetze',     'label' => 'Startplätze',                 'min' => 'bronze', 'typ' => 'startplaetze',
         'menge' => ['bronze' => 1, 'silber' => 3, 'gold' => 5]],
        ['key' => 'banner',           'label' => 'Banner im Start-/Zielbereich', 'min' => 'gold',  'typ' => 'haken_text'],
        ['key' => 'stand',            'label' => 'eigener Stand inkl. Fläche',  'min' => 'gold',   'typ' => 'haken'],
        ['key' => 'moderation',       'label' => 'Moderations-Erwähnung',       'min' => 'gold',   'typ' => 'haken'],
    ];

    if ($inklusiveInaktive) {
        return $katalog;
    }
    return array_values(array_filter(
        $katalog,
        static fn (array $p): bool => ($p['aktiv'] ?? true) !== false
    ));
}

/** Gilt eine Position laut Typ (kumulativ)? */
function sponsorLeistungGilt(array $position, ?string $typ): bool
{
    $rang = sponsorTypRang($typ);
    return $rang > 0 && $rang >= sponsorTypRang($position['min']);
}

/** Startplätze-Stückzahl für einen Typ (null = individuell, z. B. Hauptsponsor). */
function sponsorStartplaetzeMenge(array $position, ?string $typ): ?int
{
    return $position['menge'][$typ] ?? null;
}

/** Gültige Katalog-Schlüssel (für Whitelist in der API). */
function sponsorLeistungKeys(): array
{
    return array_map(static fn (array $p): string => $p['key'], sponsorLeistungenKatalog());
}

/**
 * Gespeicherter Zustand je Sponsor: [position => ['vereinbart'=>bool, 'freitext'=>string]].
 * Positionen ohne Zeile gelten als Standard (vereinbart = Position gilt laut Typ).
 */
function sponsorLeistungenState(PDO $pdo, int $sponsorId): array
{
    $stmt = $pdo->prepare('SELECT position, vereinbart, freitext FROM sponsor_leistungen WHERE sponsor_id = :id');
    $stmt->execute(['id' => $sponsorId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['position']] = [
            'vereinbart' => (int) $row['vereinbart'] === 1,
            'freitext'   => (string) ($row['freitext'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Stückzahl der freien Startplätze laut Paket — dieselbe Katalog-Quelle, aus der die Matrix
 * ihre Zahl anzeigt (Silber 3 / Gold 5).
 * null = es gibt keine Zahl, die im Brief stehen könnte: entweder gilt die Position für den Typ
 * nicht (Bronze/Sachsponsor/kein Typ) oder die Menge ist individuell (Hauptsponsor, Matrix: „indiv.").
 */
function sponsorStartplaetzeAnzahl(?string $typ): ?int
{
    foreach (sponsorLeistungenKatalog() as $pos) {
        if ($pos['key'] === 'startplaetze') {
            return sponsorLeistungGilt($pos, $typ) ? sponsorStartplaetzeMenge($pos, $typ) : null;
        }
    }
    return null;
}

/**
 * Sind für diesen Sponsor überhaupt Startplätze vereinbart?
 *
 * Nicht dasselbe wie „hat eine Stückzahl": der Hauptsponsor bekommt Startplätze, aber in
 * individueller Menge (`sponsorStartplaetzeAnzahl` liefert dort null). Umgekehrt kann die Position
 * in der Matrix pro Sponsor abgewählt sein — dann gilt sie nicht, egal was das Paket sagt.
 * Genau diese Frage entscheidet, ob beim Bestätigungs-Versand ein fehlender Gutscheincode
 * überhaupt ein Problem ist.
 */
function sponsorStartplaetzeVereinbart(PDO $pdo, int $sponsorId, ?string $typ): bool
{
    $pos = null;
    foreach (sponsorLeistungenKatalog() as $p) {
        if ($p['key'] === 'startplaetze') {
            $pos = $p;
            break;
        }
    }
    if ($pos === null) {
        return false;
    }
    $state = $sponsorId > 0 ? sponsorLeistungenState($pdo, $sponsorId) : [];
    // Zeile vorhanden → der gesetzte Haken gewinnt; sonst der Paket-Default.
    return isset($state['startplaetze'])
        ? $state['startplaetze']['vereinbart']
        : sponsorLeistungGilt($pos, $typ);
}

/**
 * Gutscheincode eines Sponsors — der Freitext der Position „Startplätze" aus der Leistungs-Matrix.
 * Leerstring, wenn kein Sponsor gewählt ist oder noch kein Code hinterlegt wurde; die weiche
 * Prüfung beim Bestätigungs-Versand (G3) hängt genau an diesem Leerstring.
 */
function sponsorGutscheincode(PDO $pdo, int $sponsorId): string
{
    if ($sponsorId <= 0) {
        return '';
    }
    $stmt = $pdo->prepare(
        "SELECT freitext FROM sponsor_leistungen WHERE sponsor_id = :id AND position = 'startplaetze'"
    );
    $stmt->execute(['id' => $sponsorId]);
    return trim((string) ($stmt->fetchColumn() ?: ''));
}

/**
 * Zustand einer Zelle setzen (Upsert). $vereinbart/$freitext getrennt setzbar; null = nicht ändern
 * (der jeweils andere Wert bleibt erhalten bzw. fällt auf Default zurück).
 */
function sponsorLeistungSet(PDO $pdo, int $sponsorId, string $position, ?bool $vereinbart, ?string $freitext): void
{
    // Bestehende Zeile lesen, damit Teil-Updates den anderen Wert nicht verlieren.
    $cur = $pdo->prepare('SELECT vereinbart, freitext FROM sponsor_leistungen WHERE sponsor_id = :id AND position = :p');
    $cur->execute(['id' => $sponsorId, 'p' => $position]);
    $row = $cur->fetch();

    $ver = $vereinbart !== null ? ($vereinbart ? 1 : 0) : ($row ? (int) $row['vereinbart'] : 1);
    $txt = $freitext   !== null ? $freitext : ($row ? (string) ($row['freitext'] ?? '') : '');

    $stmt = $pdo->prepare(
        'INSERT INTO sponsor_leistungen (sponsor_id, position, vereinbart, freitext)
         VALUES (:id, :p, :v, :t)
         ON DUPLICATE KEY UPDATE vereinbart = :v2, freitext = :t2'
    );
    $stmt->execute(['id' => $sponsorId, 'p' => $position, 'v' => $ver, 't' => $txt, 'v2' => $ver, 't2' => $txt]);
}
