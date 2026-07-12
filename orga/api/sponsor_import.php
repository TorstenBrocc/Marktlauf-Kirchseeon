<?php
/**
 * Sponsor CSV-Import (POST + CSRF)
 * Grundlage: intern/sponsor-crm-ausbau.md §3
 *
 * Spalten-Mapping (CSV → DB):
 *   FIRMENNAME     → sponsors.firma (Alias: COMPANY)
 *   TIER_VORSCHLAG → sponsors.paket (gold/silber/bronze/hauptsponsor)
 *   EMAIL          → sponsor_ansprechpartner.email
 *   TELEFONNUMMER  → sponsor_ansprechpartner.telefon (Alias: TELEFON, optional)
 *   ANREDE         → sponsor_ansprechpartner.anrede
 *   LASTNAME       → sponsor_ansprechpartner.nachname
 *   PRIORITAET     → sponsors.prioritaet (Hoch=1/Mittel=2/Niedrig=3 oder Zahl)
 *   ORT            → sponsors.ort
 *   GESENDET=Ja    → status=angefragt + gesendet_am gesetzt
 *
 * Dubletten-Check in PHP (kein Unique-Index): firma+email normalisiert.
 * Bei einer erkannten Dublette wird nicht neu angelegt, aber eine fehlende
 * Telefonnummer aus der CSV nachgetragen (vorhandene Nummern bleiben unangetastet).
 * Hat ein bekannter Sponsor (gleiche Firma) noch gar keinen Ansprechpartner,
 * wird die Kontaktzeile aus der CSV am vorhandenen Sponsor ergänzt statt neu angelegt.
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

if (!isset($col['FIRMENNAME']) && !isset($col['COMPANY'])) {
    fclose($handle);
    $_SESSION['flash_error'] = 'Pflichtspalte FIRMENNAME fehlt im CSV-Header.';
    header('Location: ../sponsoren.php');
    exit;
}

$get = static function (array $row, array $col, string $name): string {
    return isset($col[$name]) ? trim((string) ($row[$col[$name]] ?? '')) : '';
};

// Wert über mehrere mögliche Spaltennamen holen (deutscher Name zuerst, engl. Alias als Fallback)
$getAny = static function (array $row, array $col, array $names) use ($get): string {
    foreach ($names as $name) {
        $v = $get($row, $col, $name);
        if ($v !== '') {
            return $v;
        }
    }
    return '';
};

// ---- Import ----------------------------------------------------------------

$neu = 0;
$uebersprungen = 0;
$ergaenzt = 0;
$fehler = 0;
$fehlerZeilen = [];

try {
    $pdo = getDbConnection();

    // Bestehende Datensätze laden (firma|email, beide normalisiert).
    // Zusätzlich zur reinen Dubletten-Erkennung merken wir uns die Ansprechpartner-ID
    // und die vorhandene Telefonnummer, um bei einem erneuten Import fehlende Nummern
    // nachzutragen (ohne bereits vorhandene je zu überschreiben).
    $seen = [];
    $existingAp = [];
    $sponsorIdByFirma = []; // normFirma => sponsor_id (erster Treffer)
    $sponsorHasAp = [];     // sponsor_id => bool (existiert mind. ein Ansprechpartner?)
    $existing = $pdo->query('
        SELECT s.id AS sponsor_id, s.firma, ap.id AS ap_id, ap.email, ap.telefon
        FROM sponsors s
        LEFT JOIN sponsor_ansprechpartner ap ON ap.sponsor_id = s.id
    ');
    while ($e = $existing->fetch()) {
        $sid = (int) $e['sponsor_id'];
        $normFirma = $normalize((string) $e['firma']);
        $key = $normFirma . '|' . $normalize((string) ($e['email'] ?? ''));
        $seen[$key] = true;

        if (!isset($sponsorIdByFirma[$normFirma])) {
            $sponsorIdByFirma[$normFirma] = $sid;
        }
        if (!isset($sponsorHasAp[$sid])) {
            $sponsorHasAp[$sid] = false;
        }

        // Nur den ersten passenden AP-Datensatz je Schlüssel als Ziel für die Anreicherung merken
        if ($e['ap_id'] !== null) {
            $sponsorHasAp[$sid] = true;
            if (!isset($existingAp[$key])) {
                $existingAp[$key] = [
                    'ap_id'   => (int) $e['ap_id'],
                    'telefon' => trim((string) ($e['telefon'] ?? '')),
                ];
            }
        }
    }

    $insertSponsor = $pdo->prepare('
        INSERT INTO sponsors (firma, paket, prioritaet, ort, status, gesendet_am)
        VALUES (:firma, :paket, :prioritaet, :ort, :status, :gesendet_am)
    ');
    $insertAp = $pdo->prepare('
        INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, nachname, telefon, email)
        VALUES (:sponsor_id, :anrede, :nachname, :telefon, :email)
    ');
    // Telefonnummer nur setzen, wenn bislang keine hinterlegt ist (kein Überschreiben)
    $updateTelefon = $pdo->prepare("
        UPDATE sponsor_ansprechpartner
        SET telefon = :telefon
        WHERE id = :id AND (telefon IS NULL OR telefon = '')
    ");

    $zeile = 1; // Header war Zeile 1
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $zeile++;

        // Vollständig leere Zeilen überspringen
        if (count(array_filter($row, static fn ($c) => trim((string) $c) !== '')) === 0) {
            continue;
        }

        $firma = $getAny($row, $col, ['FIRMENNAME', 'COMPANY']);
        if ($firma === '') {
            $fehler++;
            if (count($fehlerZeilen) < 20) {
                $fehlerZeilen[] = "Zeile {$zeile}: FIRMENNAME leer";
            }
            continue;
        }

        $email = $get($row, $col, 'EMAIL');
        $normFirma = $normalize($firma);
        $key = $normFirma . '|' . $normalize($email);
        $telefonCsv = $getAny($row, $col, ['TELEFONNUMMER', 'TELEFON']);
        $nachname = $get($row, $col, 'LASTNAME');
        $anrede = $mapAnrede($get($row, $col, 'ANREDE'));

        // 1) Exakte Dublette (firma+email) mit vorhandenem Ansprechpartner:
        //    keine Neuanlage, aber fehlende Telefonnummer nachtragen.
        if (isset($seen[$key]) && isset($existingAp[$key])) {
            if ($telefonCsv !== '' && $existingAp[$key]['telefon'] === '') {
                try {
                    $updateTelefon->execute(['telefon' => $telefonCsv, 'id' => $existingAp[$key]['ap_id']]);
                    if ($updateTelefon->rowCount() > 0) {
                        $existingAp[$key]['telefon'] = $telefonCsv; // im Lauf gesetzt, kein Doppel-Update
                        $ergaenzt++;
                        continue;
                    }
                } catch (PDOException $e) {
                    logError("Sponsor-Import Zeile {$zeile} (Telefon-Ergänzung): " . $e->getMessage());
                }
            }
            $uebersprungen++;
            continue;
        }

        // 2) Bekannter Sponsor (gleiche Firma), der noch gar keinen Ansprechpartner hat:
        //    Kontaktzeile am vorhandenen Sponsor anlegen statt neu anzulegen.
        if (isset($sponsorIdByFirma[$normFirma]) && empty($sponsorHasAp[$sponsorIdByFirma[$normFirma]])) {
            $sid = $sponsorIdByFirma[$normFirma];
            if ($email !== '' || $nachname !== '' || $telefonCsv !== '') {
                try {
                    $insertAp->execute([
                        'sponsor_id' => $sid,
                        'anrede'     => $anrede,
                        'nachname'   => $nachname,
                        'telefon'    => $telefonCsv,
                        'email'      => $email,
                    ]);
                    $sponsorHasAp[$sid] = true;
                    $seen[$key] = true;
                    $existingAp[$key] = ['ap_id' => (int) $pdo->lastInsertId(), 'telefon' => $telefonCsv];
                    $ergaenzt++;
                } catch (PDOException $e) {
                    logError("Sponsor-Import Zeile {$zeile} (Ansprechpartner-Ergänzung): " . $e->getMessage());
                    $uebersprungen++;
                }
            } else {
                $uebersprungen++;
            }
            continue;
        }

        // 3) Sonstige Dublette (firma+email bereits bekannt, Sponsor hat aber schon AP mit anderer Mail):
        //    unverändert überspringen.
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

            $hatAp = ($email !== '' || $nachname !== '' || $telefonCsv !== '');
            if ($hatAp) {
                $insertAp->execute([
                    'sponsor_id' => $sponsorId,
                    'anrede'     => $anrede,
                    'nachname'   => $nachname,
                    'telefon'    => $telefonCsv,
                    'email'      => $email,
                ]);
            }

            // Neuen Sponsor registrieren, damit weitere CSV-Zeilen derselben Firma andocken
            $seen[$key] = true;
            if (!isset($sponsorIdByFirma[$normFirma])) {
                $sponsorIdByFirma[$normFirma] = $sponsorId;
            }
            $sponsorHasAp[$sponsorId] = $hatAp;
            if ($hatAp) {
                $existingAp[$key] = ['ap_id' => (int) $pdo->lastInsertId(), 'telefon' => $telefonCsv];
            }
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

    $_SESSION['flash_success'] = "Import abgeschlossen: {$neu} neu, {$ergaenzt} ergänzt (Telefon/Kontakt), {$uebersprungen} Dubletten übersprungen, {$fehler} Fehler.";
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
