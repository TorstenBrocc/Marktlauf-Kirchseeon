<?php
/**
 * Social-Kopplung der Sponsoren (Post-Wirkung-Spec S5 / 5.C.9) — EINE Quelle, die die
 * aktuell bestaetigten Sponsoren nach Stufe/Prioritaet liest und (a) den Text-Prompt
 * (orga/api/social_generate.php) und (b) die Grafik-Logo-Slots (orga/vorlagen.php) speist.
 * Namen kommen aus `sponsors` (nicht aus `sponsoring_pakete` — das bleibt der Preis-Katalog).
 *
 * DEFENSIV gegen die noch nicht angewandte Migration 077: die SSOT-Felder social_handle
 * und kernkompetenz werden nur gelesen, wenn die Spalten existieren. So laeuft der Code
 * VOR und NACH der Migration (Spec §8: Bau mit leerem Kernkompetenz-Feld, Daten spaeter).
 */

declare(strict_types=1);

/** Existiert eine Spalte in `sponsors`? Einmal je Request ermittelt und gecacht. */
function sponsorsHatSpalte(PDO $pdo, string $spalte): bool
{
    static $spalten = null;
    if ($spalten === null) {
        $spalten = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM sponsors');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $spalten[(string) $row['Field']] = true;
            }
        } catch (PDOException $e) {
            $spalten = [];
        }
    }
    return isset($spalten[$spalte]);
}

/**
 * Aktuell bestaetigte Sponsoren, nach Paket-Rang (Haupt > Gold > Silber > Bronze > Sach)
 * und Prioritaet/Firma sortiert.
 *
 * @return list<array{firma: string, paket: ?string, website: ?string, logo: ?string,
 *                     social_handle: string, kernkompetenz: string}>
 */
function socialSponsoren(PDO $pdo): array
{
    $hatHandle = sponsorsHatSpalte($pdo, 'social_handle');
    $hatKern   = sponsorsHatSpalte($pdo, 'kernkompetenz');

    $spalten = ['firma', 'paket', 'website', 'logo_web_asset'];
    if ($hatHandle) { $spalten[] = 'social_handle'; }
    if ($hatKern)   { $spalten[] = 'kernkompetenz'; }

    $sql = 'SELECT ' . implode(', ', $spalten) . "
              FROM sponsors
             WHERE status IN ('zugesagt', 'abgerechnet', 'bezahlt')
               AND kein_kontakt = 0
          ORDER BY FIELD(paket, 'hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'),
                   (prioritaet IS NULL), prioritaet ASC, firma ASC";
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $logoDatei = trim((string) ($r['logo_web_asset'] ?? ''));
        $out[] = [
            'firma'         => (string) $r['firma'],
            'paket'         => $r['paket'] !== null ? (string) $r['paket'] : null,
            'website'       => $r['website'] !== null ? (string) $r['website'] : null,
            'logo'          => $logoDatei !== '' ? 'assets/sponsoren-live/' . $logoDatei : null,
            'social_handle' => $hatHandle ? trim((string) ($r['social_handle'] ?? '')) : '',
            'kernkompetenz' => $hatKern   ? trim((string) ($r['kernkompetenz'] ?? '')) : '',
        ];
    }
    return $out;
}

/**
 * Sponsor-Fakten fuer den Text-Prompt (nur auf Sponsoren-Themen). Haupt- und Gold-Sponsoren
 * werden namentlich genannt (mit Kernkompetenz, falls gepflegt), der Rest gesammelt.
 * Leerer Rueckgabewert, wenn keine bestaetigten Sponsoren vorliegen.
 */
function socialSponsorenPromptBlock(PDO $pdo, string $anlassKey): string
{
    $sponsoren = socialSponsoren($pdo);
    if ($sponsoren === []) {
        return '';
    }

    if ($anlassKey === 'sponsorenvorstellung') {
        // EINEN Sponsor vorstellen: der ranghoechste als Vorschlag (Fakten-Feld kann ihn ersetzen).
        $s = $sponsoren[0];
        $zeile = 'Stelle diesen Sponsor vor: ' . $s['firma'];
        if ($s['kernkompetenz'] !== '') {
            $zeile .= ' — Kernkompetenz: ' . $s['kernkompetenz'] . ' (stelle den Bezug zum Marktlauf selbst her)';
        }
        if ($s['website'] !== null && $s['website'] !== '') {
            $zeile .= ' — Website: ' . $s['website'];
        }
        return $zeile;
    }

    // sponsoren_dank: Haupt + Gold namentlich, Rest gesammelt.
    $namentlich = [];
    $weitere    = 0;
    foreach ($sponsoren as $s) {
        if (in_array($s['paket'], ['hauptsponsor', 'gold'], true)) {
            $eintrag = $s['firma'];
            if ($s['kernkompetenz'] !== '') { $eintrag .= ' (' . $s['kernkompetenz'] . ')'; }
            $namentlich[] = $eintrag;
        } else {
            $weitere++;
        }
    }
    $teile = [];
    if ($namentlich !== []) {
        $teile[] = 'Namentlich danken: ' . implode(', ', $namentlich) . '.';
    }
    if ($weitere > 0) {
        $teile[] = 'Weitere ' . $weitere . ' Partner gesammelt-warm einschliessen (keine Aufzaehlung, keine Stufen-Titel).';
    }
    return $teile === [] ? '' : 'Bestaetigte Sponsoren/Partner — ' . implode(' ', $teile);
}
