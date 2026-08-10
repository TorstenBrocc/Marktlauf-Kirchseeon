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
 * Leistungs-Katalog (Reihenfolge = Spaltenreihenfolge der Matrix).
 * typ: 'haken' | 'haken_text' | 'startplaetze'
 * @return array<int, array{key:string,label:string,min:string,typ:string,menge?:array<string,int>}>
 */
function sponsorLeistungenKatalog(): array
{
    return [
        ['key' => 'logo_website',        'label' => 'Logo auf Website',           'min' => 'bronze', 'typ' => 'haken'],
        ['key' => 'startertueten',       'label' => 'Startertüten-Branding',      'min' => 'bronze', 'typ' => 'haken_text'],
        ['key' => 'urkunde',             'label' => 'Urkunde',                    'min' => 'bronze', 'typ' => 'haken'],
        ['key' => 'dankesschreiben',     'label' => 'Dankesschreiben',            'min' => 'bronze', 'typ' => 'haken'],
        ['key' => 'logo_startnummer',    'label' => 'Logo auf Startnummer',       'min' => 'silber', 'typ' => 'haken'],
        ['key' => 'logo_streckenbanner', 'label' => 'Logo auf Streckenbanner',    'min' => 'silber', 'typ' => 'haken'],
        ['key' => 'presse',              'label' => 'Namensnennung Presse',       'min' => 'silber', 'typ' => 'haken'],
        ['key' => 'logo_shirt',          'label' => 'Logo auf Lauf-Shirt',        'min' => 'silber', 'typ' => 'haken'],
        ['key' => 'startplaetze',        'label' => 'Startplätze',                'min' => 'bronze', 'typ' => 'startplaetze',
         'menge' => ['bronze' => 1, 'silber' => 3, 'gold' => 5]],
        ['key' => 'banner',              'label' => 'Banner Start-/Zielbereich',  'min' => 'gold',   'typ' => 'haken_text'],
        ['key' => 'stand',               'label' => 'eigener Stand inkl. Fläche', 'min' => 'gold',   'typ' => 'haken'],
        ['key' => 'moderation',          'label' => 'Moderations-Erwähnung',      'min' => 'gold',   'typ' => 'haken'],
    ];
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
