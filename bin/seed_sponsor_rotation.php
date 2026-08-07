<?php
/**
 * Einmaliger Seed: die vier Startseiten-Bestandssponsoren in die datengetriebene
 * Website-Rotation überführen (Migration 042). Idempotent — auf EXAKTE Sponsor-IDs
 * gekoppelt (per Lister ermittelt), da Namens-Matches im CRM mehrdeutig sind.
 *   - übernimmt das bestehende Repo-Logo als materialisiertes Web-Asset,
 *   - setzt Website (exakt die aktuellen Link-Ziele aus index.html) + in_rotation = 1,
 *   - schreibt am Ende data/sponsoren.json.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_rotation.php"
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_rotation.php';

$assets = __DIR__ . '/../assets/images/sponsoren';

// Sponsor-ID => [Anzeigename (Logo-Slug), Website, Quell-Logo]
$seed = [
    17 => ['firma' => 'Apotheke St. Josef',
           'website' => 'https://apotheke-kirchseeon.de',
           'logo' => $assets . '/Apotheke St. Josef.png'],
    8  => ['firma' => 'Kreissparkasse München Starnberg Ebersberg',
           'website' => 'https://www.kskmse.de',
           'logo' => $assets . '/KSKMSE_Instituts-Logo-Regionen_cmyk_rot.jpg'],
    24 => ['firma' => 'Hörmann Gruppe',
           'website' => 'https://www.hoermann-gruppe.com',
           'logo' => $assets . '/Hoermann-Logo-pur_rgb.png'],
    67 => ['firma' => 'Bestattungsdienst Pietas',
           'website' => 'https://www.bestattungsdienst-pietas.de/bestattungen-kirchseeon-bestattungsunternehmen-beerdigung-begraebnis.php',
           'logo' => $assets . '/Bestattungsdienst-Pietas.svg'],
];

$pdo = getDbConnection();

foreach ($seed as $id => $s) {
    $chk = $pdo->prepare('SELECT firma FROM sponsors WHERE id = :id');
    $chk->execute(['id' => $id]);
    if ($chk->fetchColumn() === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        continue;
    }

    $asset = null;
    try {
        $asset = importSponsorLogoFromPath($id, $s['firma'], $s['logo']);
    } catch (Throwable $e) {
        echo "  LOGO-FEHLER #{$id}: " . $e->getMessage() . "\n";
    }

    $pdo->prepare('UPDATE sponsors SET website = :w, in_rotation = 1, logo_web_asset = COALESCE(:l, logo_web_asset) WHERE id = :id')
        ->execute(['w' => $s['website'], 'l' => $asset, 'id' => $id]);

    echo "OK #{$id} {$s['firma']} logo=" . ($asset ?? 'NULL') . "\n";
}

writeSponsorenFeed($pdo);
echo "Feed data/sponsoren.json geschrieben.\n";
