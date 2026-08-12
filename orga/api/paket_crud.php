<?php
/**
 * Paket-Pflege — Preise und Katalog-Positionen speichern (POST + CSRF, AJAX/JSON).
 *
 * Zwei Datensätze hinter einer Seite (`orga/pakete.php`):
 *   feld=investition  → Paketpreis in der Einstellung `sponsoring_pakete`
 *   sonst             → eine Spalte der Katalog-Zeile in `leistungs_katalog` (Migration 059)
 *
 * Muster: orga/api/leistung_crud.php (Inline-Speichern je Zelle).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_leistungen.php';
require_once __DIR__ . '/../../src/rechnung.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$feld = trim((string) ($_POST['feld'] ?? ''));
$wert = (string) ($_POST['wert'] ?? '');

try {
    $pdo = getDbConnection();

    // --- Paketpreis -----------------------------------------------------------------
    if ($feld === 'investition') {
        $paketKey = trim((string) ($_POST['paket'] ?? ''));
        if (!in_array($paketKey, ['hauptsponsor', 'gold', 'silber', 'bronze'], true)) {
            echo json_encode(['ok' => false, 'error' => 'input']);
            exit;
        }
        // Gespeichert wird die komplette Liste — das ist das Format, das `sponsoring_pakete`
        // seit jeher hat. Gelesen wird über sponsoringPakete(), damit die aktuellen Werte
        // (inkl. erzeugter Highlights) drinstehen und nichts verloren geht.
        $liste = [];
        foreach (sponsoringPakete($pdo) as $key => $p) {
            $liste[] = [
                'key'         => (string) $key,
                'name'        => (string) ($p['name'] ?? ''),
                'investition' => (string) $key === $paketKey ? mb_substr(trim($wert), 0, 60) : (string) ($p['investition'] ?? ''),
                'highlights'  => (string) ($p['highlights'] ?? ''),
            ];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2'
        );
        $json = json_encode($liste, JSON_UNESCAPED_UNICODE);
        $stmt->execute(['k' => 'sponsoring_pakete', 'v' => $json, 'v2' => $json]);

        echo json_encode(['ok' => true, 'betrag' => paketBetrag(mb_substr(trim($wert), 0, 60))]);
        exit;
    }

    // --- Katalog-Zeile --------------------------------------------------------------
    $key = trim((string) ($_POST['key'] ?? ''));
    // Whitelist inkl. inaktiver Positionen: eine abgeschaltete Zeile muss wieder
    // einschaltbar sein, sonst wäre `aktiv=0` eine Einbahnstraße.
    $gueltigeKeys = array_map(
        static fn (array $p): string => $p['key'],
        sponsorLeistungenKatalog(true)
    );
    if ($key === '' || !in_array($key, $gueltigeKeys, true)) {
        echo json_encode(['ok' => false, 'error' => 'input']);
        exit;
    }

    // Spalte + Wertprüfung je Feld. Nur diese Felder sind über die Seite änderbar;
    // `key`, `typ` und `gruppe` sind Struktur und bleiben Code-/Migrationssache.
    switch ($feld) {
        case 'min_stufe':
            if (!in_array($wert, ['bronze', 'silber', 'gold', 'hauptsponsor'], true)) {
                echo json_encode(['ok' => false, 'error' => 'input']);
                exit;
            }
            $spalte = 'min_stufe';
            $param  = $wert;
            break;

        case 'aktiv':
            $spalte = 'aktiv';
            $param  = $wert === '1' ? 1 : 0;
            break;

        case 'menge_bronze':
        case 'menge_silber':
        case 'menge_gold':
            $spalte = $feld;
            // Leer = keine Zahl = "individuell". 0 ist ein zulässiger Wert (Position ohne Kontingent).
            $param  = trim($wert) === '' ? null : max(0, (int) $wert);
            break;

        case 'label':
            $wert = trim($wert);
            if ($wert === '') {
                echo json_encode(['ok' => false, 'error' => 'input']);
                exit;
            }
            $spalte = 'label';
            $param  = mb_substr($wert, 0, 120);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'feld']);
            exit;
    }

    $stmt = $pdo->prepare("UPDATE leistungs_katalog SET `$spalte` = :wert WHERE `key` = :key");
    $stmt->execute(['wert' => $param, 'key' => $key]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    logError('paket_crud: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'db']);
}
