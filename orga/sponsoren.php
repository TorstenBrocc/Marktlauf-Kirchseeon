<?php
/**
 * Sponsoren-Übersicht (Admin + Orga)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_status.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$importReport = $_SESSION['import_report'] ?? [];
// Das Reset-Signal der Anhang-Abwahl werten die Anschreiben-Seiten aus (dort steht die
// Anhang-Kachel); hier wird es nur noch mit aufgeräumt, falls es liegen geblieben ist.
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['import_report'], $_SESSION['anhang_abwahl_reset']);

$filterStatus = $_GET['status'] ?? '';
$filterPaket = $_GET['paket'] ?? '';
$filterZustaendig = $_GET['zustaendig'] ?? '';

// Fördergruppen-Reiter im Kopf (TT 2026-08-14): Standard = klassisches Sponsoring.
// 'alle' = alle Fördergruppen zusammen (kein foerdergruppe-Filter).
$filterFg = (string) ($_GET['fg'] ?? 'sponsoring');
if ($filterFg !== 'alle' && !in_array($filterFg, sponsorFoerdergruppeKeys(), true)) {
    $filterFg = 'sponsoring';
}
$filterBranchen = array_values(array_filter((array) ($_GET['branchen'] ?? [])));

$pdo = getDbConnection();

// Zuordenbare Personen: aktive Orga-/Admin-Mitglieder
$users = [];
$userNameById = [];
try {
    $uStmt = $pdo->query("SELECT id, name FROM users WHERE active = 1 ORDER BY name");
    $users = $uStmt->fetchAll();
    foreach ($users as $u) {
        $userNameById[(int) $u['id']] = $u['name'];
    }
} catch (PDOException $e) {
    // users-Tabelle immer vorhanden; defensiv
}

// Ist Migration 023 (zustaendig_user_id) bereits angewendet? Solange nicht,
// bleibt die Spalte/Filter ausgeblendet (graceful, kein Fehler).
$hasZustaendig = false;
try {
    $hasZustaendig = (bool) $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'zustaendig_user_id'")->fetchColumn();
} catch (PDOException $e) {
    // ignore
}

// Bedingungen-Bestätigung (Migration 062) evtl. noch nicht angewendet — graceful.
$hasBedingungen = false;
try {
    $hasBedingungen = (bool) $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'bedingungen_bestaetigt_am'")->fetchColumn();
} catch (PDOException $e) {
    // ignore
}

$hasGruppe = false;
$gruppeNameById = [];
try {
    $hasGruppe = (bool) $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'gruppe_id'")->fetchColumn();
    if ($hasGruppe) {
        $gStmt = $pdo->query('SELECT id, name FROM sponsor_gruppen');
        foreach ($gStmt->fetchAll() as $g) {
            $gruppeNameById[(int) $g['id']] = $g['name'];
        }
    }
} catch (PDOException $e) {
    // ignore
}

$hasFoerdergruppe = false;
try {
    $hasFoerdergruppe = (bool) $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'foerdergruppe'")->fetchColumn();
} catch (PDOException $e) {
    // ignore
}

$colCount = 9;
if ($hasZustaendig) {
    $colCount++;
}
if ($hasBedingungen) {
    $colCount++;
}

$sql = 'SELECT * FROM sponsors';
$where = [];
$params = [];

if ($filterStatus !== '' && sponsorStatusValid($filterStatus)) {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
} else {
    // Standard-Ansicht: abgelehnte Sponsoren ausblenden. Sie erscheinen nur,
    // wenn im Statusfilter explizit "Abgelehnt" gewählt wird (Zweig oben).
    $where[] = "status != 'abgelehnt'";
}

if ($filterPaket !== '' && in_array($filterPaket, ['hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'], true)) {
    $where[] = 'paket = :paket';
    $params['paket'] = $filterPaket;
}

// Fördergruppen-Reiter: jede Ansicht zeigt genau eine Gruppe ('alle' = ohne Filter).
if ($hasFoerdergruppe && $filterFg !== 'alle') {
    $where[] = 'foerdergruppe = :fg';
    $params['fg'] = $filterFg;
}

// Zuständigkeit: "mine" = eigene Einträge, sonst konkrete User-ID
if ($hasZustaendig) {
    if ($filterZustaendig === 'mine') {
        $where[] = 'zustaendig_user_id = :zust';
        $params['zust'] = (int) $user['id'];
    } elseif ($filterZustaendig !== '' && ctype_digit((string) $filterZustaendig) && isset($userNameById[(int) $filterZustaendig])) {
        $where[] = 'zustaendig_user_id = :zust';
        $params['zust'] = (int) $filterZustaendig;
    }
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY kein_kontakt ASC, firma ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sponsoren = $stmt->fetchAll();

if (!empty($filterBranchen)) {
    $sponsoren = array_values(array_filter($sponsoren, static function (array $s) use ($filterBranchen): bool {
        if (empty($s['branche'])) {
            return false;
        }
        $dec = json_decode((string) $s['branche'], true);
        $arr = is_array($dec) ? $dec : [$s['branche']];
        foreach ($filterBranchen as $fb) {
            if (in_array($fb, $arr, true)) {
                return true;
            }
        }
        return false;
    }));
}

$ansprechpartnerBySponsor = [];
try {
    $apStmt = $pdo->query('SELECT sponsor_id, anrede, vorname, nachname, email, telefon FROM sponsor_ansprechpartner ORDER BY sponsor_id, id');
    while ($row = $apStmt->fetch()) {
        $ansprechpartnerBySponsor[$row['sponsor_id']][] = $row;
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

$countStmt = $pdo->query('SELECT COUNT(*) FROM sponsors');
$totalCount = (int) $countStmt->fetchColumn();

// Zugesagtes Geld = alles ab der Zusage: bestätigt und abgerechnet gehören dazu, sonst fiele ein
// Sponsor zwischen Rechnungsstellung und Zahlungseingang aus der Summe heraus.
$summeStmt = $pdo->query('SELECT SUM(summe) FROM sponsors WHERE status IN ("zugesagt", "bestaetigt", "abgerechnet", "bezahlt")');
$gesamtSumme = (float) $summeStmt->fetchColumn();


$branchen = [];
try {
    $bStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $bStmt->execute(['key' => 'sponsor_branchen']);
    $bRaw = $bStmt->fetchColumn();
    if ($bRaw) {
        $branchen = json_decode((string) $bRaw, true) ?? [];
    }
} catch (PDOException $e) {
    // Branchen noch nicht angelegt
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sponsoren | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .page-header {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 1.25rem;
        }
        /* Top-Polsterung des Scroll-Containers auf 0 (Abstand wandert in .page-header oben),
           damit die fixierte Kopf-Zone bündig am oberen Rand andockt statt an der Polsterkante —
           sonst schöbe Inhalt durch den 1,5rem-Streifen ÜBER dem Titel. */
        .main-content { padding-top: 0; }
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar .form-group {
            margin-bottom: 0;
        }
        .filter-bar label {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }
        .filter-bar select {
            padding: 0.5rem;
            min-width: 150px;
        }
        .branche-dd-wrap {
            position: relative;
        }
        .branche-dd-btn {
            padding: 0.5rem;
            min-width: 150px;
            width: 100%;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            text-align: left;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text);
            font-family: inherit;
        }
        .branche-dd-btn:hover {
            border-color: var(--primary);
        }
        .branche-dd-panel {
            display: none;
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            z-index: 50;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            box-shadow: var(--shadow-card);
            padding: 0.4rem 0;
            min-width: 230px;
            max-height: 320px;
            overflow-y: auto;
        }
        .branche-dd-panel.open {
            display: block;
        }
        .branche-dd-item {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.75rem;
            cursor: pointer;
            font-weight: 400;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .branche-dd-item:hover {
            background: var(--bg);
        }
        .branche-dd-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table th {
            background: var(--bg);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
        }
        .data-table tr:hover {
            background: #fafafa;
        }
        .data-table td {
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-angefragt { background: #fff3cd; color: #856404; }
        .status-zugesagt { background: #d4edda; color: #155724; }
        .status-abgelehnt { background: var(--error-bg); color: var(--error); }
        .status-bezahlt { background: var(--success-bg); color: var(--success); }
        /* Ampel-Status (Lebenszyklus) */
        .ampel {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .ampel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex: 0 0 auto;
        }
        .ampel-grau  .ampel-dot { background: #9aa0a6; }
        .ampel-blau  .ampel-dot { background: #2b7de9; }
        .ampel-gelb  .ampel-dot { background: #f4b400; }
        .ampel-gruen .ampel-dot { background: var(--primary); }
        .ampel-rot   .ampel-dot { background: var(--error); }
        /* Kompakte Aktionsleiste (Import/Export + Absprünge in die Anschreiben) */
        /* CSV-Import/Export sitzt am Seitenende unter der Tabelle (TT 2026-08-14). */
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.25rem;
            align-items: center;
            margin-top: 1.5rem;
            padding: 0.6rem 0.875rem;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .action-bar form {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }
        .action-bar input[type="file"] {
            font-size: 0.78rem;
        }
        .action-bar-sep {
            width: 1px;
            align-self: stretch;
            min-height: 1.5rem;
            background: var(--border);
        }
        .action-bar label {
            font-size: 0.8rem;
            font-weight: 600;
        }
        .action-bar select {
            padding: 0.3rem 0.4rem;
            font-size: 0.8rem;
        }
        .import-report {
            font-size: 0.8rem;
            background: #fff8f8;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            margin-bottom: 1rem;
            max-height: 180px;
            overflow-y: auto;
        }
        .import-report ul { margin: 0.25rem 0 0 1rem; }
        .notiz-form {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .notiz-form textarea {
            width: 180px;
            font-size: 0.75rem;
            padding: 0.35rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            resize: vertical;
        }
        .notiz-save {
            display: none;
            align-self: flex-start;
        }
        .notiz-form.dirty .notiz-save {
            display: inline-block;
        }
        .prio-badge {
            display: inline-block;
            font-size: 0.6rem;
            padding: 0.05rem 0.3rem;
            border-radius: 3px;
            margin-left: 0.4rem;
            vertical-align: middle;
            color: #fff;
        }
        .prio-1 { background: var(--error); }
        .prio-2 { background: #f4b400; color: #333; }
        .prio-3 { background: #9aa0a6; }
        .paket-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .paket-hauptsponsor { background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; }
        .paket-gold { background: #ffd700; color: #333; }
        .paket-silber { background: #c0c0c0; color: #333; }
        .paket-bronze { background: #cd7f32; color: white; }
        .paket-sachsponsor { background: #6b7280; color: white; }
        /* Inline-Dropdowns (Paket/Status direkt in der Tabelle ändern) */
        .inline-select {
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--white);
            cursor: pointer;
            max-width: 130px;
        }
        .inline-select.saving { opacity: 0.5; }
        .inline-select.saved { border-color: var(--primary); }
        /* Paket-Dropdown übernimmt die Badge-Farbe */
        .paket-select.paket-hauptsponsor { background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; border: none; }
        .paket-select.paket-gold { background: #ffd700; color: #333; border-color: #e6c200; }
        .paket-select.paket-silber { background: #c0c0c0; color: #333; border-color: #a8a8a8; }
        .paket-select.paket-bronze { background: #cd7f32; color: white; border: none; }
        .paket-select.paket-sachsponsor { background: #6b7280; color: white; border: none; }
        .paket-select.paket-none { color: var(--text-light); }
        /* Status-Dropdown: farbiger Rand nach Ampel */
        .status-select { border-left-width: 4px; }
        .status-select.ampel-grau  { border-left-color: #9aa0a6; }
        .status-select.ampel-blau  { border-left-color: #2b7de9; }
        .status-select.ampel-gelb  { border-left-color: #f4b400; }
        .status-select.ampel-gruen { border-left-color: var(--primary); }
        .status-select.ampel-rot   { border-left-color: var(--error); }
        /* Zugesagte Sponsoren: ganze Zeile hell transparent grün */
        .status-zugesagt-row { background: rgba(76, 175, 80, 0.12); }
        /* Abgelehnte Sponsoren: ganze Zeile hell transparent rot (analog zu zugesagt) */
        .status-abgelehnt-row { background: rgba(211, 47, 47, 0.12); }
        /* In Klärung: ganze Zeile hell transparent gelb (analog zu zugesagt/abgelehnt) */
        .status-in_klaerung-row { background: rgba(244, 180, 0, 0.16); }
        /* Angeschrieben: ganze Zeile hell transparent blau (Ampel-Blau) */
        .status-angefragt-row { background: rgba(43, 125, 233, 0.12); }
        /* Branche-Karten (nur gruppiert): weiße Karten auf grauem Grund */
        @media (min-width: 769px) {
            /* App-Shell (nur Desktop): main-content füllt die Fensterhöhe und ist eine
               vertikale Flex-Spalte. Kopf-Zone oben und Aktionsleiste unten bleiben fix, die
               gruppierte Tabelle (siehe eigener Media-Block weiter unten) füllt den Rest und
               scrollt IN SICH. So bleibt der Spaltenkopf beim Scrollen sichtbar und es scrollt
               nie die ganze Seite horizontal weg (das erzeugte früher den kaputten rechten
               Rand). Auf dem Handy (< 769px) greift nichts davon -> normaler Seiten-Scroll. */
            .dashboard-layout { height: 100vh; overflow: hidden; }
            .main-content { height: 100vh; display: flex; flex-direction: column; min-height: 0; }
            .sidebar { overflow-y: auto; }
            .kopf-fixzone {
                position: sticky;
                top: 0;
                flex: 0 0 auto;
                z-index: 20;
                background: var(--bg);
            }
            .action-bar { flex: 0 0 auto; }
            .data-table thead th {
                position: sticky;
                top: var(--fixzone-h, 0px);
                z-index: 5;
            }
        }
        .table-wrap.grouped {
            /* Basis = Mobil (< 769px): die breite Tabelle scrollt horizontal innerhalb ihrer
               Karte, statt den Überlauf auf die Seite zu schieben (kaputter rechter Rand).
               Auf Desktop wird diese Regel vom Scroll-Box-Media-Block weiter unten überschrieben
               (overflow:auto + Flex-Höhe). */
            overflow-x: auto;
            box-shadow: none;
            border-radius: 0;
            background: #eceef1;
            padding: 0 14px 14px;
        }
        .data-table.grouped {
            border-collapse: separate;
            border-spacing: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            overflow: visible;
        }
        /* Reiter-Band (Fördergruppen): grauer Grund wie die Kartenzone, verbindet optisch mit
           dem Spaltenkopf. Zentriert; passt der Reiter nicht auf schmale Schirme, scrollt er
           horizontal (safe center: bei Überlauf linksbündig, damit nichts abgeschnitten wird),
           statt umzubrechen. */
        .kopf-sticky {
            display: flex;
            justify-content: safe center;
            overflow-x: auto;
            background: #eceef1;
            padding: 0.75rem 14px;
        }
        .kopf-sticky .ansicht-toggle { flex: 0 0 auto; }
        /* Kern-Hinweis unter den Fördergruppen-Reitern: was macht die Gruppe im Kern aus
           (Nachvollziehbarkeit der Einordnung). Setzt das graue Reiter-Band nach unten fort. */
        .fg-kern-hinweis {
            background: #eceef1;
            padding: 0 14px 0.7rem;
            margin: 0;
            font-size: 0.82rem;
            line-height: 1.45;
            color: var(--text);
            text-align: center;
        }
        .fg-kern-hinweis strong { color: var(--primary); }
        .data-table.grouped thead th {
            /* Position wird pro Breite gesetzt: Desktop = sticky am Box-Rand (Media-Block
               weiter unten), Mobil = static (normaler Fluss). Hier nur die Optik. */
            background: #eceef1;
            border-bottom: none;
            box-shadow: 0 6px 8px -7px rgba(0, 0, 0, 0.25);
        }
        /* Scroll-Box (nur Desktop): die gruppierte Tabelle füllt als Flex-Kind den Raum
           zwischen fixer Kopf-Zone und fixer Aktionsleiste und scrollt IN SICH — vertikal
           (Spaltenkopf bleibt oben) und horizontal (breite Tabelle scrollt in der Box, nicht
           die ganze Seite → kein ungedeckter rechter Rand). min-height:0 ist Pflicht, sonst
           kann das Flex-Kind nicht unter seine Inhaltshöhe schrumpfen und der interne Scroll
           entsteht nicht. Dieser Block steht bewusst NACH .table-wrap.grouped{overflow-x:auto}
           (die Basis-Regel gilt nur noch für Mobil < 769px): Media-Queries erhöhen die
           Spezifität nicht, nur die Quellreihenfolge entscheidet auf Desktop. */
        @media (min-width: 769px) {
            .table-wrap.grouped {
                flex: 1 1 auto;
                min-height: 0;
                overflow: auto;
            }
            .data-table.grouped thead th {
                position: sticky;
                top: 0;
                z-index: 5;
            }
        }
        /* Kachel-Kopf: klickbar (einklappen), Doppelklick benennt um */
        .branche-heading-row td {
            background: #fff;
            font-weight: 600;
            font-size: 1.05rem;
            padding: 0.7rem 0.95rem;
            border: 1px solid var(--border);
            border-bottom-color: #eee;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            box-shadow: -6px 0 9px -6px rgba(0, 0, 0, 0.16), 6px 0 9px -6px rgba(0, 0, 0, 0.16), 0 -2px 6px -4px rgba(0, 0, 0, 0.06);
            cursor: pointer;
            user-select: none;
        }
        .branche-heading-row:hover td { background: #fafbfc; }
        .branche-heading-count { color: #8a8a8a; font-weight: 400; font-size: 0.85rem; }
        .branche-caret { display: inline-block; width: 0.9em; color: #9a9a9a; font-size: 0.8rem; transition: transform 0.12s; }
        .branche-heading-row.collapsed .branche-caret { transform: rotate(-90deg); }
        /* Kachel-Körper: weiße Zeilen, Seitenrahmen + Schatten, unten abgerundet */
        .data-table.grouped tr.branche-row { background: #fff; }
        .branche-row td:first-child { border-left: 1px solid var(--border); box-shadow: -6px 0 9px -6px rgba(0, 0, 0, 0.16); }
        .branche-row td:last-child  { border-right: 1px solid var(--border); box-shadow: 6px 0 9px -6px rgba(0, 0, 0, 0.16); }
        .branche-row-last td { border-bottom: 1px solid var(--border); box-shadow: 0 8px 10px -6px rgba(0, 0, 0, 0.18); }
        .branche-row-last td:first-child { border-bottom-left-radius: 12px; box-shadow: -6px 7px 10px -6px rgba(0, 0, 0, 0.17); }
        .branche-row-last td:last-child  { border-bottom-right-radius: 12px; box-shadow: 6px 7px 10px -6px rgba(0, 0, 0, 0.17); }
        /* Status-/Kein-Kontakt-Tönung behält Vorrang vor dem Kartenweiß */
        .data-table.grouped tr.status-zugesagt-row    { background: rgba(76, 175, 80, 0.12); }
        .data-table.grouped tr.status-abgelehnt-row   { background: rgba(211, 47, 47, 0.12); }
        .data-table.grouped tr.status-in_klaerung-row { background: rgba(244, 180, 0, 0.16); }
        .data-table.grouped tr.status-angefragt-row   { background: rgba(43, 125, 233, 0.12); }
        .data-table.grouped tr.kein-kontakt-row       { background: #f9f9f9; }
        /* Grauer Grund zeigt sich zwischen den Karten */
        .branche-gap td { padding: 0; height: 14px; background: transparent; border: none; }
        .branche-gap:hover td { background: transparent; }
        .kein-kontakt-row {
            background: #f9f9f9;
        }
        .kein-kontakt-row td {
            color: #999;
        }
        .kein-kontakt-row .firma-cell {
            text-decoration: line-through;
        }
        .kein-kontakt-badge {
            display: inline-block;
            padding: 0.125rem 0.375rem;
            background: #6c757d;
            color: white;
            border-radius: 3px;
            font-size: 0.625rem;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        /* Sachsponsoring: Sachspende statt Geld. Farbe wie Zusage (status-zugesagt). */
        .sach-badge {
            display: inline-block;
            padding: 0.125rem 0.375rem;
            background: #d4edda;
            color: #155724;
            border-radius: 3px;
            font-size: 0.625rem;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        .gruppe-badge {
            display: inline-block;
            padding: 0.05rem 0.375rem;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 3px;
            font-size: 0.625rem;
            margin-left: 0.4rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .col-bedingungen {
            text-align: center;
        }
        .bed-ja {
            color: var(--primary);
            font-weight: 600;
        }
        .bed-nein {
            color: #c0392b;
            font-weight: 700;
        }
        .bed-neutral {
            color: var(--text-light);
        }
        .table-wrap {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow-card);
        }
        .stats {
            display: flex;
            gap: 2rem;
            font-size: 0.875rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }
        .inline-form {
            display: inline;
        }
        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: var(--border);
            color: var(--text);
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-icon:hover {
            background: #ccc;
        }
        .ap-count {
            font-size: 0.7rem;
            color: var(--text-light);
            margin-left: 0.25rem;
        }
        .ap-name {
            font-size: 0.875rem;
        }
        .ap-email,
        .ap-tel {
            font-size: 0.75rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
<?php $activeNav = 'sponsoren'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <div class="kopf-fixzone">
                <div class="page-header">
                    <h1>Sponsoren-Übersicht</h1>
                    <a href="sponsor_form.php" class="btn btn-primary btn-small">+ Neu anlegen</a>
                </div>

                <form method="get" class="filter-bar">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Alle (ohne Abgelehnt)</option>
                            <?php foreach (SPONSOR_STATUS as $key => $meta): ?>
                                <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Paket</label>
                        <select name="paket" onchange="this.form.submit()">
                            <option value="">Alle</option>
                            <option value="hauptsponsor" <?= $filterPaket === 'hauptsponsor' ? 'selected' : '' ?>>Hauptsponsor</option>
                            <option value="gold" <?= $filterPaket === 'gold' ? 'selected' : '' ?>>Gold</option>
                            <option value="silber" <?= $filterPaket === 'silber' ? 'selected' : '' ?>>Silber</option>
                            <option value="bronze" <?= $filterPaket === 'bronze' ? 'selected' : '' ?>>Bronze</option>
                            <option value="sachsponsor" <?= $filterPaket === 'sachsponsor' ? 'selected' : '' ?>>Sachsponsor</option>
                        </select>
                    </div>
                    <?php if ($hasZustaendig): ?>
                    <div class="form-group">
                        <label>Zuständig</label>
                        <select name="zustaendig" onchange="this.form.submit()">
                            <option value="">Alle</option>
                            <option value="mine" <?= $filterZustaendig === 'mine' ? 'selected' : '' ?>>Nur meine</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= (string) $filterZustaendig === (string) $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($branchen)): ?>
                    <div class="form-group">
                        <label>Branche</label>
                        <div class="branche-dd-wrap" id="branche-dd-wrap">
                            <button type="button" class="branche-dd-btn" onclick="toggleBrancheDD()">
                                <?php $bCnt = count($filterBranchen); ?>
                                <?= $bCnt ? 'Branche (' . $bCnt . ')' : 'Alle Branchen' ?> ▾
                            </button>
                            <div class="branche-dd-panel" id="branche-dd-panel">
                                <?php foreach ($branchen as $b): ?>
                                    <label class="branche-dd-item">
                                        <input type="checkbox" name="branchen[]"
                                               value="<?= htmlspecialchars($b) ?>"
                                               <?= in_array($b, $filterBranchen, true) ? 'checked' : '' ?>
                                               onchange="this.form.submit()">
                                        <?= htmlspecialchars($b) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($filterStatus || $filterPaket || $filterZustaendig || !empty($filterBranchen)): ?>
                        <a href="sponsoren.php" class="btn btn-small btn-secondary">Filter zurücksetzen</a>
                    <?php endif; ?>
                </form>

                <div class="stats">
                    <span><?= count($sponsoren) ?> von <?= $totalCount ?> Sponsoren</span>
                    <span>Zusagen gesamt: <span class="stat-value"><?= number_format($gesamtSumme, 2, ',', '.') ?> €</span></span>
                </div>

                <?php
            // Fördergruppen-Reiter im Kopf (TT 2026-08-14). Die frühere Liste/Branche-Umschaltung
            // ist entfernt — die Branche-Gruppierung ist die einzige Darstellung, deshalb entfällt
            // die Branche-Spalte immer.
            $colCount--;
            $qsFg = $_GET;
            ?>
                <div class="kopf-sticky">
                    <div class="ansicht-toggle" style="display:inline-flex;border:1px solid var(--border);border-radius:6px;overflow:hidden;font-size:0.85rem">
                        <?php $fgFirst = true; foreach (SPONSOR_FOERDERGRUPPE as $fgKey => $fgLabel): $qsFg['fg'] = $fgKey; ?>
                            <a href="?<?= htmlspecialchars(http_build_query($qsFg)) ?>"
                               style="padding:0.4rem 0.85rem;text-decoration:none;<?= $fgFirst ? '' : 'border-left:1px solid var(--border);' ?><?= $filterFg === $fgKey ? 'background:var(--primary);color:#fff' : 'color:var(--text)' ?>"><?= htmlspecialchars($fgLabel) ?></a>
                        <?php $fgFirst = false; endforeach; ?>
                        <?php $qsFg['fg'] = 'alle'; ?>
                        <a href="?<?= htmlspecialchars(http_build_query($qsFg)) ?>"
                           style="padding:0.4rem 0.85rem;text-decoration:none;border-left:1px solid var(--border);<?= $filterFg === 'alle' ? 'background:var(--primary);color:#fff' : 'color:var(--text)' ?>">Alle</a>
                    </div>
                </div>
                <?php $fgKern = ($filterFg !== 'alle') ? sponsorFoerdergruppeHinweis((string) $filterFg) : ''; ?>
                <?php if ($fgKern !== ''): ?>
                <div class="fg-kern-hinweis"><strong><?= htmlspecialchars(sponsorFoerdergruppeLabel((string) $filterFg)) ?>:</strong> <?= htmlspecialchars($fgKern) ?></div>
                <?php endif; ?>
            </div><!-- /kopf-fixzone -->

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if (!empty($importReport)): ?>
                <div class="import-report">
                    <strong>Import-Hinweise:</strong>
                    <ul>
                        <?php foreach ($importReport as $line): ?>
                            <li><?= htmlspecialchars($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="table-wrap grouped">
                <table class="data-table grouped">
                    <thead>
                        <tr>
                            <th>Firma</th>
                            <th>Ansprechpartner</th>
                            <th>Paket</th>
                            <th>Summe</th>
                            <th>Status</th>
                            <th>Wiedervorlage</th>
                            <?php if ($hasBedingungen): ?><th class="col-bedingungen" title="Sponsoring-Bedingungen vom Sponsor bestätigt?">Bedingungen</th><?php endif; ?>
                            <?php if ($hasZustaendig): ?><th>Zuständig</th><?php endif; ?>
                            <th>Notiz</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sponsoren)): ?>
                            <tr>
                                <td colspan="<?= $colCount ?>">Keine Sponsoren gefunden.</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $prioMeta = [1 => ['Hoch', 'prio-1'], 2 => ['Mittel', 'prio-2'], 3 => ['Niedrig', 'prio-3']];
                            $brancheArr = function ($s) {
                                if (empty($s['branche'])) { return []; }
                                $d = json_decode($s['branche'], true);
                                return is_array($d) ? $d : [$s['branche']];
                            };
                            // Anzeige-Reihenfolge: nach Branche gruppiert. Ein Sponsor erscheint
                            // unter jeder seiner Branchen; „Ohne Branche" kommt ans Ende.
                            $sequence = [];
                            {
                                // Gruppen-Namen = Einstellungs-Liste + alle real vergebenen Branchen,
                                // damit auch Sponsoren mit „unbekannter" Branche eine Überschrift bekommen.
                                $groupNames = $branchen;
                                foreach ($sponsoren as $s) {
                                    foreach ($brancheArr($s) as $bv) {
                                        if ($bv !== '' && !in_array($bv, $groupNames, true)) { $groupNames[] = $bv; }
                                    }
                                }
                                $gi = 0;
                                foreach ($groupNames as $bName) {
                                    $grp = [];
                                    foreach ($sponsoren as $s) {
                                        if (in_array($bName, $brancheArr($s), true)) { $grp[] = $s; }
                                    }
                                    if ($grp) {
                                        $gi++;
                                        $sequence[] = ['heading' => $bName, 'count' => count($grp), 'gi' => $gi];
                                        $n = count($grp);
                                        foreach (array_values($grp) as $k => $g) {
                                            $sequence[] = ['sponsor' => $g, 'gi' => $gi, 'last' => ($k === $n - 1)];
                                        }
                                    }
                                }
                                $ohne = [];
                                foreach ($sponsoren as $s) {
                                    if (!$brancheArr($s)) { $ohne[] = $s; }
                                }
                                if ($ohne) {
                                    $gi++;
                                    $sequence[] = ['heading' => 'Ohne Branche', 'count' => count($ohne), 'gi' => $gi, 'noedit' => true];
                                    $n = count($ohne);
                                    foreach (array_values($ohne) as $k => $g) {
                                        $sequence[] = ['sponsor' => $g, 'gi' => $gi, 'last' => ($k === $n - 1)];
                                    }
                                }
                            }
                            ?>
                            <?php foreach ($sequence as $item): ?>
                                <?php if (isset($item['heading'])): ?>
                                    <?php if (!empty($headingSeen)): ?><tr class="branche-gap"><td colspan="<?= $colCount ?>"></td></tr><?php endif; $headingSeen = true; ?>
                                    <tr class="branche-heading-row" data-group="<?= (int) $item['gi'] ?>" data-branche="<?= htmlspecialchars($item['heading']) ?>"<?= empty($item['noedit']) ? '' : ' data-noedit="1"' ?>>
                                        <td colspan="<?= $colCount ?>"><span class="branche-caret" aria-hidden="true">▾</span> <span class="branche-title"><?= htmlspecialchars($item['heading']) ?></span> <span class="branche-heading-count">(<?= (int) $item['count'] ?>)</span></td>
                                    </tr>
                                <?php else: ?>
                                <?php
                                $s = $item['sponsor'];
                                $apList = $ansprechpartnerBySponsor[$s['id']] ?? [];
                                $apCount = count($apList);
                                $firstAp = $apList[0] ?? null;
                                $prio = (int) ($s['prioritaet'] ?? 0);
                                $rowClass = '';
                                if ($s['kein_kontakt']) {
                                    $rowClass = 'kein-kontakt-row';
                                } elseif ($s['status'] === 'zugesagt') {
                                    $rowClass = 'status-zugesagt-row';
                                } elseif ($s['status'] === 'abgelehnt') {
                                    $rowClass = 'status-abgelehnt-row';
                                } elseif ($s['status'] === 'in_klaerung') {
                                    $rowClass = 'status-in_klaerung-row';
                                } elseif ($s['status'] === 'angefragt') {
                                    $rowClass = 'status-angefragt-row';
                                }
                                ?>
                                <tr class="<?= $rowClass ?><?= isset($item['gi']) ? ' branche-row' . (!empty($item['last']) ? ' branche-row-last' : '') : '' ?>"<?= isset($item['gi']) ? ' data-group="' . (int) $item['gi'] . '"' : '' ?>>
                                    <td class="firma-cell">
                                        <a href="sponsor_form.php?id=<?= $s['id'] ?>">
                                            <strong><?= htmlspecialchars($s['firma']) ?></strong>
                                        </a>
                                        <?php if ($hasGruppe && !empty($s['gruppe_id']) && isset($gruppeNameById[(int) $s['gruppe_id']])): ?>
                                            <span class="gruppe-badge" title="Konzern/Gruppe"><?= htmlspecialchars($gruppeNameById[(int) $s['gruppe_id']]) ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($prioMeta[$prio])): ?>
                                            <span class="prio-badge <?= $prioMeta[$prio][1] ?>" title="Priorität"><?= $prioMeta[$prio][0] ?></span>
                                        <?php endif; ?>
                                        <?php if ($s['kein_kontakt']): ?>
                                            <span class="kein-kontakt-badge">Kein Kontakt</span>
                                        <?php endif; ?>
                                        <?php if (($s['paket'] ?? '') === 'sachsponsor'): ?>
                                            <span class="sach-badge" title="Sachspende statt Geld">Sach</span>
                                        <?php endif; ?>
                                        <?php if (!empty($s['ort'])): ?>
                                            <div class="ap-email"><?= htmlspecialchars($s['ort']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($firstAp): ?>
                                            <div class="ap-name">
                                                <?= htmlspecialchars(trim($firstAp['vorname'] . ' ' . $firstAp['nachname'])) ?: '–' ?>
                                                <?php if ($apCount > 1): ?>
                                                    <span class="ap-count">+<?= $apCount - 1 ?> weitere</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($firstAp['telefon'])): ?>
                                                <div class="ap-tel">
                                                    <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $firstAp['telefon'])) ?>"><?= htmlspecialchars($firstAp['telefon']) ?></a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($firstAp['email']): ?>
                                                <div class="ap-email">
                                                    <a href="mailto:<?= htmlspecialchars($firstAp['email']) ?>"><?= htmlspecialchars($firstAp['email']) ?></a>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            –
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="inline-select paket-select paket-<?= $s['paket'] ?: 'none' ?>"
                                                data-id="<?= $s['id'] ?>" data-field="paket" title="Paket ändern">
                                            <option value="" <?= !$s['paket'] ? 'selected' : '' ?>>–</option>
                                            <?php foreach (['hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber', 'bronze' => 'Bronze', 'sachsponsor' => 'Sachsponsor'] as $pk => $pl): ?>
                                                <option value="<?= $pk ?>" <?= $s['paket'] === $pk ? 'selected' : '' ?>><?= $pl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="summe-cell"><?= $s['summe'] ? number_format((float)$s['summe'], 2, ',', '.') . ' €' : '–' ?></td>
                                    <td>
                                        <select class="inline-select status-select ampel-<?= sponsorStatusAmpel($s['status']) ?>"
                                                data-id="<?= $s['id'] ?>" data-field="status" title="Status ändern">
                                            <?php foreach (SPONSOR_STATUS as $key => $meta): ?>
                                                <option value="<?= $key ?>" <?= $s['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($s['wiedervorlage']): ?>
                                            <?= date('d.m.Y', strtotime($s['wiedervorlage'])) ?>
                                        <?php else: ?>
                                            –
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($hasBedingungen): ?>
                                        <?php
                                        $bedStatus     = (string) ($s['status'] ?? '');
                                        $bedBestaetigt = !empty($s['bedingungen_bestaetigt_am']);
                                        if (!sponsorBedingungenBenoetigt($bedStatus)) {
                                            $bedCls = 'bed-neutral'; $bedSym = '–';
                                            $bedTip = 'Bestätigung noch nicht verschickt';
                                        } elseif ($bedBestaetigt) {
                                            $bedCls   = 'bed-ja';
                                            $bedWeg   = sponsorBedingungenWegLabel((string) ($s['bedingungen_weg'] ?? ''));
                                            $bedDatum = date('d.m.Y', strtotime((string) $s['bedingungen_bestaetigt_am']));
                                            $bedBeleg = !empty($s['bedingungen_beleg']);
                                            $bedSym   = '✓' . ($bedBeleg ? ' 📎' : '');
                                            $bedTip   = 'Bestätigt am ' . $bedDatum
                                                . ($bedWeg !== '' ? ' (' . $bedWeg . ')' : '')
                                                . ($bedBeleg ? ' · Rückmeldung im Ordner' : ' · Beleg fehlt');
                                        } else {
                                            $bedCls = 'bed-nein'; $bedSym = '✗';
                                            $bedTip = 'Bedingungen noch nicht bestätigt';
                                        }
                                        ?>
                                        <td class="col-bedingungen" title="<?= htmlspecialchars($bedTip) ?>">
                                            <span class="<?= $bedCls ?>"><?= $bedSym ?></span>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($hasZustaendig): ?>
                                    <td>
                                        <?php $zustId = (int) ($s['zustaendig_user_id'] ?? 0); ?>
                                        <select class="inline-select zustaendig-select"
                                                data-id="<?= $s['id'] ?>" data-field="zustaendig" title="Zuständige Person zuordnen">
                                            <option value="" <?= $zustId === 0 ? 'selected' : '' ?>>–</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?= (int) $u['id'] ?>" <?= $zustId === (int) $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <form method="post" action="api/sponsor_notiz.php" class="notiz-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                                            <textarea name="notizen" rows="2" placeholder="Notiz…"
                                                      oninput="this.closest('.notiz-form').classList.add('dirty')"><?= htmlspecialchars($s['notizen'] ?? '') ?></textarea>
                                            <button type="submit" class="btn-icon notiz-save">Speichern</button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="sponsor_form.php?id=<?= $s['id'] ?>" class="btn-icon" title="Bearbeiten">Bearbeiten</a>
                                        <?php if (!$s['kein_kontakt']): ?>
                                            <form method="post" action="api/sponsor_crud.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="kein_kontakt_set">
                                                <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn-icon" title="Kein Kontakt markieren">KK</button>
                                            </form>
                                        <?php elseif ($isAdmin): ?>
                                            <form method="post" action="api/sponsor_crud.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="kein_kontakt_remove">
                                                <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn-icon" title="Kein-Kontakt aufheben">KK↩</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $exportQuery = http_build_query(array_filter(['status' => $filterStatus, 'paket' => $filterPaket])); ?>
            <div class="action-bar">
                <form method="post" action="api/sponsor_import.php" enctype="multipart/form-data"
                      onsubmit="return confirm('CSV jetzt importieren? Dubletten (Firma + E-Mail) werden übersprungen.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <label for="csv_datei">CSV-Import</label>
                    <input type="file" id="csv_datei" name="csv_datei" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-small btn-secondary">Importieren</button>
                </form>
                <div class="action-bar-sep"></div>
                <a href="api/sponsor_export.php<?= $exportQuery ? '?' . $exportQuery : '' ?>" class="btn btn-small btn-secondary">
                    CSV-Export<?= ($filterStatus || $filterPaket) ? ' (gefiltert)' : '' ?>
                </a>
                <a href="api/sponsor_export_full.php<?= $exportQuery ? '?' . $exportQuery : '' ?>" class="btn btn-small btn-secondary"
                   title="Alle Stammdatenfelder (website, branche, förderung …) inkl. erstem Ansprechpartner">
                    Stammdaten-CSV<?= ($filterStatus || $filterPaket) ? ' (gefiltert)' : '' ?>
                </a>
                <a href="api/sponsor_vcard_export.php<?= $exportQuery ? '?' . $exportQuery : '' ?>" class="btn btn-small btn-secondary"
                   title="Ansprechpartner als vCard (.vcf) für die Handy-Kontakte">
                    vCard-Export<?= ($filterStatus || $filterPaket) ? ' (gefiltert)' : '' ?>
                </a>
            </div>
        </main>
    </div>
    <script>
    // Inline-Dropdowns: Paket/Status direkt aus der Übersicht speichern
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const ampelClasses = ['ampel-grau', 'ampel-blau', 'ampel-gelb', 'ampel-gruen', 'ampel-rot'];
        const paketClasses = ['paket-hauptsponsor', 'paket-gold', 'paket-silber', 'paket-bronze', 'paket-sachsponsor', 'paket-none'];

        function applyClass(sel, keep, cls) {
            keep.forEach(function(c) { sel.classList.remove(c); });
            if (cls) sel.classList.add(cls);
        }

        document.querySelectorAll('.inline-select').forEach(function(sel) {
            sel.addEventListener('change', function() {
                const body = new URLSearchParams();
                body.set('action', 'inline_update');
                body.set('csrf_token', csrf);
                body.set('sponsor_id', sel.dataset.id);
                body.set('field', sel.dataset.field);
                body.set('value', sel.value);

                sel.classList.add('saving');
                sel.classList.remove('saved');

                fetch('api/sponsor_crud.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'fetch' },
                    body: body
                })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        sel.classList.remove('saving');
                        if (d && d.ok) {
                            if (sel.dataset.field === 'status') {
                                applyClass(sel, ampelClasses, 'ampel-' + d.ampel);
                                const row = sel.closest('tr');
                                if (row && !row.classList.contains('kein-kontakt-row')) {
                                    row.classList.remove('status-zugesagt-row', 'status-abgelehnt-row', 'status-in_klaerung-row', 'status-angefragt-row');
                                    if (sel.value === 'zugesagt') {
                                        row.classList.add('status-zugesagt-row');
                                    } else if (sel.value === 'abgelehnt') {
                                        row.classList.add('status-abgelehnt-row');
                                    } else if (sel.value === 'in_klaerung') {
                                        row.classList.add('status-in_klaerung-row');
                                    } else if (sel.value === 'angefragt') {
                                        row.classList.add('status-angefragt-row');
                                    }
                                }
                            } else if (sel.dataset.field === 'paket') {
                                applyClass(sel, paketClasses, 'paket-' + (d.paket || 'none'));
                                // Betrag folgt dem Typ: Summe-Zelle live nachziehen (null = unverändert lassen).
                                if (d.summe !== null && d.summe !== undefined) {
                                    const row = sel.closest('tr');
                                    const cell = row ? row.querySelector('.summe-cell') : null;
                                    if (cell) {
                                        const n = parseFloat(d.summe);
                                        cell.textContent = (n > 0)
                                            ? n.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
                                            : '–';
                                    }
                                }
                            }
                            sel.classList.add('saved');
                            setTimeout(function() { sel.classList.remove('saved'); }, 1200);
                        } else {
                            alert((d && d.message) || 'Fehler beim Speichern.');
                        }
                    })
                    .catch(function() {
                        sel.classList.remove('saving');
                        alert('Fehler beim Speichern.');
                    });
            });
        });
    })();

    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    })();

    function toggleBrancheDD() {
        document.getElementById('branche-dd-panel').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('branche-dd-wrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('branche-dd-panel').classList.remove('open');
        }
    });

    // ── Branche-Kacheln: Einfachklick klappt ein/aus, Doppelklick benennt um ──
    (function () {
        var CSRF = <?= json_encode($csrfToken) ?>;
        var IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        var headings = document.querySelectorAll('.branche-heading-row');
        if (!headings.length) return;

        function rowsOf(gi) { return document.querySelectorAll('tr[data-group="' + gi + '"]:not(.branche-heading-row)'); }

        function toggle(head) {
            var gi = head.dataset.group;
            var collapse = !head.classList.contains('collapsed');
            head.classList.toggle('collapsed', collapse);
            rowsOf(gi).forEach(function (r) { r.style.display = collapse ? 'none' : ''; });
        }

        function rename(head) {
            if (!IS_ADMIN) { return; }
            if (head.dataset.noedit) { return; } // „Ohne Branche" nicht umbenennbar
            var alt = head.dataset.branche;
            var neu = window.prompt('Branche umbenennen — betrifft alle Sponsoren dieser Gruppe:', alt);
            if (neu === null) { return; }
            neu = neu.trim();
            if (neu === '' || neu === alt) { return; }
            var body = new URLSearchParams();
            body.set('csrf_token', CSRF);
            body.set('old', alt);
            body.set('new', neu);
            fetch('api/branche_rename.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) { location.reload(); }
                    else { alert((d && d.message) || 'Umbenennen fehlgeschlagen.'); }
                })
                .catch(function () { alert('Netzwerkfehler beim Umbenennen.'); });
        }

        headings.forEach(function (head) {
            var clickTimer = null;
            head.addEventListener('click', function () {
                if (clickTimer) { return; }
                clickTimer = setTimeout(function () { clickTimer = null; toggle(head); }, 220);
            });
            head.addEventListener('dblclick', function () {
                if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
                rename(head);
            });
        });
    })();

    // Fixierte Kopf-Zone (Desktop): Blockhöhe messen und als --fixzone-h veröffentlichen,
    // damit der Spaltenkopf lückenlos darunter andockt (robust bei Umbruch/Resize).
    (function () {
        var fixzone = document.querySelector('.kopf-fixzone');
        if (!fixzone) { return; }
        var root = document.documentElement;
        function sync() {
            root.style.setProperty('--fixzone-h', fixzone.offsetHeight + 'px');
        }
        sync();
        window.addEventListener('resize', sync);
    })();
    </script>
</body>
</html>
