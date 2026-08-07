<?php
/**
 * Einmaliger Seed: die vier Bestands-Sponsoren der Startseite in die datengetriebene
 * Website-Rotation überführen (Migration 042). Idempotent:
 *   - matcht per firma LIKE; genau 1 Treffer → aktualisieren, 0 → anlegen, >1 → überspringen.
 *   - übernimmt das bestehende Repo-Logo als materialisiertes Web-Asset.
 *   - setzt Website (exakt die aktuellen Link-Ziele aus index.html) + in_rotation = 1.
 *   - schreibt am Ende data/sponsoren.json.
 *
 * Aufruf auf dem Server:
 *   ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_rotation.php"
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_rotation.php';

$assets = __DIR__ . '/../assets/images/sponsoren';

$seed = [
    ['match' => '%Apotheke%', 'firma' => 'Apotheke St. Josef',
     'website' => 'https://apotheke-kirchseeon.de',
     'logo' => $assets . '/Apotheke St. Josef.png'],
    ['match' => '%parkasse%', 'firma' => 'Kreissparkasse München Starnberg Ebersberg',
     'website' => 'https://www.kskmse.de',
     'logo' => $assets . '/KSKMSE_Instituts-Logo-Regionen_cmyk_rot.jpg'],
    ['match' => '%Pietas%', 'firma' => 'Bestattungsdienst Pietas',
     'website' => 'https://www.bestattungsdienst-pietas.de/bestattungen-kirchseeon-bestattungsunternehmen-beerdigung-begraebnis.php',
     'logo' => $assets . '/Bestattungsdienst-Pietas.svg'],
    ['match' => '%rmann%', 'firma' => 'Hörmann Gruppe',
     'website' => 'https://www.hoermann-gruppe.com',
     'logo' => $assets . '/Hoermann-Logo-pur_rgb.png'],
];

$pdo = getDbConnection();

foreach ($seed as $s) {
    $stmt = $pdo->prepare('SELECT id, firma FROM sponsors WHERE firma LIKE :m');
    $stmt->execute(['m' => $s['match']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        echo "SKIP {$s['firma']}: mehrdeutig (" . count($rows) . " Treffer) — bitte manuell\n";
        continue;
    }
    if (count($rows) === 1) {
        $id = (int) $rows[0]['id'];
        $firma = (string) $rows[0]['firma'];
        echo "UPDATE #{$id} {$firma}\n";
    } else {
        $ins = $pdo->prepare("INSERT INTO sponsors (firma, status) VALUES (:f, 'zugesagt')");
        $ins->execute(['f' => $s['firma']]);
        $id = (int) $pdo->lastInsertId();
        $firma = $s['firma'];
        echo "CREATE #{$id} {$firma}\n";
    }

    $asset = null;
    try {
        $asset = importSponsorLogoFromPath($id, $firma, $s['logo']);
    } catch (Throwable $e) {
        echo "  LOGO-FEHLER: " . $e->getMessage() . "\n";
    }

    $pdo->prepare('UPDATE sponsors SET website = :w, in_rotation = 1, logo_web_asset = COALESCE(:l, logo_web_asset) WHERE id = :id')
        ->execute(['w' => $s['website'], 'l' => $asset, 'id' => $id]);

    echo "  -> website={$s['website']} logo=" . ($asset ?? 'NULL') . "\n";
}

writeSponsorenFeed($pdo);
echo "Feed data/sponsoren.json geschrieben.\n";
