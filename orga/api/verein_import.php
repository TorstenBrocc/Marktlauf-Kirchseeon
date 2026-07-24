<?php
/**
 * CSV-Import für Vereine & Laufevents (POST + CSRF).
 *
 * Spalten (uppercase, getrimmt; deutsche Alias-Namen aus dem Excel-Muster werden
 * ebenfalls erkannt):
 *   NAME            (Alias: VEREIN, EVENT)                 -> vereine.name  [Pflicht]
 *   KATEGORIE       (verein|laufevent; Default verein)     -> vereine.kategorie
 *   VERANSTALTER                                           -> veranstalter
 *   ORT                                                    -> ort
 *   ENTFERNUNG      (Alias: ENTF. (CA.), ENTF.)            -> entfernung
 *   RELEVANZ        (Alias: LAUFSPORT-RELEVANZ, DISTANZEN) -> relevanz
 *   TERMIN          (Alias: TERMIN 2026, TERMIN 2026 (FALLS BEKANNT)) -> termin
 *   ANREDE / VORNAME / NACHNAME  (Alias ANSPRECHPARTNER -> nachname, wenn kein Vor-/Nachname)
 *   FUNKTION, EMAIL (Alias E-MAIL), TELEFON, ANSCHRIFT
 *   WEBSITE, SOCIAL (Alias SOCIAL MEDIA)
 *   QUELLE          (Alias: IMPRESSUM / QUELLE, IMPRESSUM/QUELLE)
 *   HINWEIS
 *   STATUS          (neu|angeschrieben|in_kontakt|partner|kein_interesse; Default neu)
 *
 * Dubletten-Check in PHP: name+ort normalisiert -> übersprungen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/verein_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine.php');
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../vereine.php');
    exit;
}
if (!isset($_FILES['csv_datei']) || $_FILES['csv_datei']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Keine gültige CSV-Datei hochgeladen.';
    header('Location: ../vereine.php');
    exit;
}
if ($_FILES['csv_datei']['size'] > 2 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'CSV-Datei zu groß (max. 2 MB).';
    header('Location: ../vereine.php');
    exit;
}

$normalize = static fn (string $v): string => mb_strtolower(trim($v));

$mapKategorie = static function (string $v): string {
    return match (mb_strtolower(trim($v))) {
        'laufevent', 'event', 'lauf', 'veranstaltung' => 'laufevent',
        default => 'verein',
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
$mapStatus = static function (string $v): string {
    $v = mb_strtolower(trim($v));
    return vereinStatusValid($v) ? $v : 'neu';
};

$handle = @fopen($_FILES['csv_datei']['tmp_name'], 'r');
if ($handle === false) {
    $_SESSION['flash_error'] = 'CSV-Datei konnte nicht gelesen werden.';
    header('Location: ../vereine.php');
    exit;
}

$firstBytes = fread($handle, 3);
if ($firstBytes !== "\xEF\xBB\xBF") { rewind($handle); }

$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    $_SESSION['flash_error'] = 'CSV-Datei ist leer.';
    header('Location: ../vereine.php');
    exit;
}
$delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
if ($firstBytes === "\xEF\xBB\xBF") { fseek($handle, 3); } else { rewind($handle); }

$header = fgetcsv($handle, 0, $delimiter);
if ($header === false) {
    fclose($handle);
    $_SESSION['flash_error'] = 'CSV-Header konnte nicht gelesen werden.';
    header('Location: ../vereine.php');
    exit;
}

$col = [];
foreach ($header as $i => $name) {
    $col[strtoupper(trim((string) $name))] = $i;
}

$get = static function (array $row, array $col, string $name): string {
    return isset($col[$name]) ? trim((string) ($row[$col[$name]] ?? '')) : '';
};
$getAny = static function (array $row, array $col, array $names) use ($get): string {
    foreach ($names as $n) {
        $v = $get($row, $col, $n);
        if ($v !== '') { return $v; }
    }
    return '';
};

if (!isset($col['NAME']) && !isset($col['VEREIN']) && !isset($col['EVENT'])) {
    fclose($handle);
    $_SESSION['flash_error'] = 'Pflichtspalte NAME (oder VEREIN / EVENT) fehlt im CSV-Header.';
    header('Location: ../vereine.php');
    exit;
}

$neu = 0; $uebersprungen = 0; $fehler = 0; $fehlerZeilen = [];

try {
    $pdo = getDbConnection();

    // Bestehende name|ort merken (Dubletten)
    $seen = [];
    $existing = $pdo->query('SELECT name, ort FROM vereine');
    while ($e = $existing->fetch()) {
        $seen[$normalize((string) $e['name']) . '|' . $normalize((string) ($e['ort'] ?? ''))] = true;
    }

    $insert = $pdo->prepare('
        INSERT INTO vereine
            (kategorie, name, veranstalter, ort, entfernung, relevanz, termin, anrede, vorname, nachname,
             funktion, email, telefon, anschrift, website, social, quelle, hinweis, status)
        VALUES
            (:kategorie, :name, :veranstalter, :ort, :entfernung, :relevanz, :termin, :anrede, :vorname, :nachname,
             :funktion, :email, :telefon, :anschrift, :website, :social, :quelle, :hinweis, :status)
    ');

    $zeile = 1;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $zeile++;
        if (count(array_filter($row, static fn ($c) => trim((string) $c) !== '')) === 0) { continue; }

        $name = $getAny($row, $col, ['NAME', 'VEREIN', 'EVENT']);
        if ($name === '') {
            $fehler++;
            if (count($fehlerZeilen) < 20) { $fehlerZeilen[] = "Zeile {$zeile}: NAME leer"; }
            continue;
        }
        $ort = $get($row, $col, 'ORT');
        $key = $normalize($name) . '|' . $normalize($ort);
        if (isset($seen[$key])) { $uebersprungen++; continue; }

        // Ansprechpartner: getrennte Felder bevorzugen, sonst Volltext -> Nachname
        $vorname = $get($row, $col, 'VORNAME');
        $nachname = $get($row, $col, 'NACHNAME');
        if ($vorname === '' && $nachname === '') {
            $nachname = $get($row, $col, 'ANSPRECHPARTNER');
        }

        try {
            $insert->execute([
                'kategorie'    => $mapKategorie($get($row, $col, 'KATEGORIE')),
                'name'         => mb_substr($name, 0, 255),
                'veranstalter' => $get($row, $col, 'VERANSTALTER') ?: null,
                'ort'          => $ort ?: null,
                'entfernung'   => $getAny($row, $col, ['ENTFERNUNG', 'ENTF. (CA.)', 'ENTF.']) ?: null,
                'relevanz'     => $getAny($row, $col, ['RELEVANZ', 'LAUFSPORT-RELEVANZ', 'DISTANZEN']) ?: null,
                'termin'       => $getAny($row, $col, ['TERMIN', 'TERMIN 2026', 'TERMIN 2026 (FALLS BEKANNT)']) ?: null,
                'anrede'       => $mapAnrede($get($row, $col, 'ANREDE')),
                'vorname'      => $vorname ?: null,
                'nachname'     => $nachname ?: null,
                'funktion'     => $get($row, $col, 'FUNKTION') ?: null,
                'email'        => $getAny($row, $col, ['EMAIL', 'E-MAIL']) ?: null,
                'telefon'      => $getAny($row, $col, ['TELEFON', 'TELEFONNUMMER']) ?: null,
                'anschrift'    => $get($row, $col, 'ANSCHRIFT') ?: null,
                'website'      => $get($row, $col, 'WEBSITE') ?: null,
                'social'       => $getAny($row, $col, ['SOCIAL', 'SOCIAL MEDIA']) ?: null,
                'quelle'       => $getAny($row, $col, ['QUELLE', 'IMPRESSUM / QUELLE', 'IMPRESSUM/QUELLE', 'IMPRESSUM/QUELLE (URL)', 'IMPRESSUM / QUELLE (URL)']) ?: null,
                'hinweis'      => $get($row, $col, 'HINWEIS') ?: null,
                'status'       => $mapStatus($get($row, $col, 'STATUS')),
            ]);
            $seen[$key] = true;
            $neu++;
        } catch (PDOException $e) {
            $fehler++;
            logError("Verein-Import Zeile {$zeile}: " . $e->getMessage());
            if (count($fehlerZeilen) < 20) { $fehlerZeilen[] = "Zeile {$zeile}: Datenbankfehler"; }
        }
    }
    fclose($handle);

    $_SESSION['flash_success'] = "Import abgeschlossen: {$neu} neu, {$uebersprungen} Dubletten übersprungen, {$fehler} Fehler.";
    if (!empty($fehlerZeilen)) { $_SESSION['import_report'] = $fehlerZeilen; }
} catch (PDOException $e) {
    if (is_resource($handle)) { fclose($handle); }
    logError('Verein-Import DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Import.';
}

header('Location: ../vereine.php');
exit;
