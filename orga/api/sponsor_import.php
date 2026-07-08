<?php
/**
 * Sponsor CSV-Import (POST + CSRF)
 * Grundlage: intern/sponsor-crm-ausbau.md §3
 *
 * Spalten-Mapping (CSV → DB):
 *   COMPANY        → sponsors.firma
 *   TIER_VORSCHLAG → sponsors.paket (gold/silber/bronze/hauptsponsor)
 *   EMAIL          → sponsor_ansprechpartner.email
 *   ANREDE         → sponsor_ansprechpartner.anrede
 *   LASTNAME       → sponsor_ansprechpartner.nachname
 *   PRIORITAET     → sponsors.prioritaet (Hoch=1/Mittel=2/Niedrig=3 oder Zahl)
 *   ORT            → sponsors.ort
 *   GESENDET=Ja    → status=angefragt + gesendet_am gesetzt
 *
 * Dubletten-Check in PHP (kein Unique-Index): firma+email normalisiert.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsoren.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsoren.php');
    exit;
}

if (!isset($_FILES['csv_datei']) || $_FILES['csv_datei']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Keine gültige CSV-Datei hochgeladen.';
    header('Location: ../sponsoren.php');
    exit;
}

$tmpPath = $_FILES['csv_datei']['tmp_name'];

if ($_FILES['csv_datei']['size'] > 2 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'CSV-Datei zu groß (max. 2 MB).';
    header('Location: ../sponsoren.php');
    exit;
}

// ---- Hilfsfunktionen -------------------------------------------------------

$normalize = static function (string $v): string {
    return mb_strtolower(trim($v));
};

$mapPaket = static function (string $v): ?string {
    $v = mb_strtolower(trim($v));
    return match ($v) {
        'gold'                    => 'gold',
        'silver', 'silber'        => 'silber',
        'bronze'                  => 'bronze',
        'hauptsponsor', 'main'    => 'hauptsponsor',
        default                   => null,
    };
};

$mapPrioritaet = static function (string $v): ?int {
    $v = trim($v);
    if ($v === '') {
        return null;
    }
    if (is_numeric($v)) {
        $n = (int) $v;
        return ($n >= 1 && $n <= 3) ? $n : null;
    }
    return match (mb_strtolower($v)) {
        'hoch', 'high'      => 1,
        'mittel', 'medium'  => 2,
        'niedrig', 'low'    => 3,
        default             => null,
    };
};

$mapAnrede = static function (string $v): string {
    return match (mb_strtolower(trim($v))) {
        'herr'   => 'Herr',
        'frau'   => 'Frau',
        'divers' => 'Divers',
        default  => '',
    };
};

$isGesendet = static function (string $v): bool {
    return in_array(mb_strtolower(trim($v)), ['ja', 'yes', '1', 'x', 'true'], true);
};

// ---- CSV öffnen + Delimiter erkennen --------------------------------------

$handle = @fopen($tmpPath, 'r');
if ($handle === false) {
    $_SESSION['flash_error'] = 'CSV-Datei konnte nicht gelesen werden.';
    header('Location: ../sponsoren.php');
    exit;
}

// BOM überspringen
$firstBytes = fread($handle, 3);
if ($firstBytes !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    $_SESSION['flash_error'] = 'CSV-Datei ist leer.';
    header('Location: ../sponsoren.php');
    exit;
}
$delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

// Zurück an Datenanfang (nach evtl. BOM)
if ($firstBytes === "\xEF\xBB\xBF") {
    fseek($handle, 3);
} else {
    rewind($handle);
}

$header = fgetcsv($handle, 0, $delimiter);
if ($header === false) {
    fclose($handle);
    $_SESSION['flash_error'] = 'CSV-Header konnte nicht gelesen werden.';
    header('Location: ../sponsoren.php');
    exit;
}

// Spalten-Index nach Name (uppercase, getrimmt)
$col = [];
foreach ($header as $i => $name) {
    $col[strtoupper(trim((string) $name))] = $i;
}

if (!isset($col['COMPANY'])) {
    fclose($handle);
    $_SESSION['flash_error'] = 'Pflichtspalte COMPANY fehlt im CSV-Header.';
    header('Location: ../sponsoren.php');
    exit;
}

$get = static function (array $row, array $col, string $name): string {
    return isset($col[$name]) ? trim((string) ($row[$col[$name]] ?? '')) : '';
};

// ---- Import ----------------------------------------------------------------

$neu = 0;
$uebersprungen = 0;
$fehler = 0;
$fehlerZeilen = [];

try {
    $pdo = getDbConnection();

    // Bestehende Dubletten-Keys laden (firma|email, beide normalisiert)
    $seen = [];
    $existing = $pdo->query('
        SELECT s.firma, ap.email
        FROM sponsors s
        LEFT JOIN sponsor_ansprechpartner ap ON ap.sponsor_id = s.id
    ');
    while ($e = $existing->fetch()) {
        $seen[$normalize((string) $e['firma']) . '|' . $normalize((string) ($e['email'] ?? ''))] = true;
    }

    $insertSponsor = $pdo->prepare('
        INSERT INTO sponsors (firma, paket, prioritaet, ort, status, gesendet_am)
        VALUES (:firma, :paket, :prioritaet, :ort, :status, :gesendet_am)
    ');
    $insertAp = $pdo->prepare('
        INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, nachname, email)
        VALUES (:sponsor_id, :anrede, :nachname, :email)
    ');

    $zeile = 1; // Header war Zeile 1
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $zeile++;

        // Vollständig leere Zeilen überspringen
        if (count(array_filter($row, static fn ($c) => trim((string) $c) !== '')) === 0) {
            continue;
        }

        $firma = $get($row, $col, 'COMPANY');
        if ($firma === '') {
            $fehler++;
            if (count($fehlerZeilen) < 20) {
                $fehlerZeilen[] = "Zeile {$zeile}: COMPANY leer";
            }
            continue;
        }

        $email = $get($row, $col, 'EMAIL');
        $key = $normalize($firma) . '|' . $normalize($email);

        if (isset($seen[$key])) {
            $uebersprungen++;
            continue;
        }

        $gesendet = $isGesendet($get($row, $col, 'GESENDET'));

        try {
            $insertSponsor->execute([
                'firma'       => $firma,
                'paket'       => $mapPaket($get($row, $col, 'TIER_VORSCHLAG')),
                'prioritaet'  => $mapPrioritaet($get($row, $col, 'PRIORITAET')),
                'ort'         => $get($row, $col, 'ORT') ?: null,
                'status'      => $gesendet ? 'angefragt' : 'neu',
                'gesendet_am' => $gesendet ? date('Y-m-d H:i:s') : null,
            ]);
            $sponsorId = (int) $pdo->lastInsertId();

            $nachname = $get($row, $col, 'LASTNAME');
            if ($email !== '' || $nachname !== '') {
                $insertAp->execute([
                    'sponsor_id' => $sponsorId,
                    'anrede'     => $mapAnrede($get($row, $col, 'ANREDE')),
                    'nachname'   => $nachname,
                    'email'      => $email,
                ]);
            }

            $seen[$key] = true;
            $neu++;
        } catch (PDOException $e) {
            $fehler++;
            logError("Sponsor-Import Zeile {$zeile}: " . $e->getMessage());
            if (count($fehlerZeilen) < 20) {
                $fehlerZeilen[] = "Zeile {$zeile}: Datenbankfehler";
            }
        }
    }

    fclose($handle);

    $_SESSION['flash_success'] = "Import abgeschlossen: {$neu} neu, {$uebersprungen} Dubletten übersprungen, {$fehler} Fehler.";
    if (!empty($fehlerZeilen)) {
        $_SESSION['import_report'] = $fehlerZeilen;
    }
} catch (PDOException $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    logError('Sponsor-Import DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Import.';
}

header('Location: ../sponsoren.php');
exit;
