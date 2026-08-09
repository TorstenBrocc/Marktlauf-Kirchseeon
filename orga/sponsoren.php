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
// Reset-Signal: nach erfolgreichem Bestätigungs-Versand setzt der Browser die Anhang-Abwahl zurück.
$bestaetigungVersandDone = !empty($_SESSION['bestaetigung_versand_done']);
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['import_report'], $_SESSION['bestaetigung_versand_done']);

$filterStatus = $_GET['status'] ?? '';
$filterPaket = $_GET['paket'] ?? '';
$filterZustaendig = $_GET['zustaendig'] ?? '';
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

// Migration 030 (Rechnungsanschrift/Gruppen) evtl. noch nicht angewendet — graceful.
$hasRechnung = false;
try {
    $hasRechnung = (bool) $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'rechnung_firma'")->fetchColumn();
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

$colCount = 10;
if ($hasZustaendig) {
    $colCount++;
}
if ($hasRechnung) {
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

$summeStmt = $pdo->query('SELECT SUM(summe) FROM sponsors WHERE status IN ("zugesagt", "bezahlt")');
$gesamtSumme = (float) $summeStmt->fetchColumn();

$merkfeld = '';
try {
    $merkStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $merkStmt->execute(['key' => 'sponsor_merkfeld']);
    $merkfeld = (string) ($merkStmt->fetchColumn() ?: '');
} catch (PDOException $e) {
    // Table may not exist yet
}

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
            margin-bottom: 1.5rem;
        }
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 0;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        /* Filter+Stats links, Merkfeld rechts (gleich hohe Spalten) */
        .filter-merk-row {
            display: flex;
            gap: 1.5rem;
            align-items: stretch;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .filter-col {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            flex: 0 0 auto;
        }
        .filter-col .stats {
            margin-bottom: 0;
        }
        .merkfeld-card {
            display: flex;
            flex: 1 1 320px;
            min-width: 280px;
            max-width: 480px;
            margin-left: auto;
        }
        .merkfeld-card textarea {
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 0.8rem;
            line-height: 1.45;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            resize: none;
            overflow: hidden;
        }
        .merkfeld-card.locked textarea {
            background: #f6f6f4;
            color: var(--text);
            cursor: default;
        }
        @media (max-width: 640px) {
            .merkfeld-card {
                flex-basis: 100%;
                min-width: 0;
                max-width: none;
                margin-left: 0;
            }
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
        /* Kompakte Aktionsleiste (Import/Export + Versand in einer Zeile) */
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.25rem;
            align-items: center;
            margin-bottom: 1.25rem;
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
        /* Hinweis unter den Versand-Controls: eigene volle Zeile (flex-basis 100%) */
        .versand-hint {
            flex-basis: 100%;
            margin: 0.15rem 0 0;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        .action-bar select {
            padding: 0.3rem 0.4rem;
            font-size: 0.8rem;
        }
        .versand-count {
            font-size: 0.78rem;
            color: var(--text-light);
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
        .col-check { width: 32px; text-align: center; }
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
        .col-rechnung {
            text-align: center;
        }
        .rechnung-ja {
            color: var(--primary);
            font-weight: 600;
        }
        .rechnung-nein {
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
            <div class="page-header">
                <h1>Sponsoren-Übersicht</h1>
                <a href="sponsor_form.php" class="btn btn-primary btn-small">+ Neu anlegen</a>
            </div>

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
                <a href="api/sponsor_vcard_export.php<?= $exportQuery ? '?' . $exportQuery : '' ?>" class="btn btn-small btn-secondary"
                   title="Ansprechpartner als vCard (.vcf) für die Handy-Kontakte">
                    vCard-Export<?= ($filterStatus || $filterPaket) ? ' (gefiltert)' : '' ?>
                </a>
                <div class="action-bar-sep"></div>
                <form id="versand-form" method="post" action="api/sponsor_versand.php"
                      onsubmit="return confirmVersand();" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <label for="anschreiben_typ">Anschreiben:</label>
                    <select id="anschreiben_typ" name="anschreiben_typ">
                        <option value="erstanschreiben">Erstanschreiben</option>
                        <option value="folgejahr">Folgejahr / Bestandssponsor</option>
                        <option value="bestaetigung">Bestätigung Sponsoring</option>
                        <option value="frei">Freier Brief</option>
                    </select>
                    <button type="submit" class="btn btn-small btn-primary">Ausgewählte anschreiben</button>
                    <span class="versand-count" id="versand-count">0 ausgewählt</span>
                    <p class="versand-hint">Versand erfolgt über <strong>info@atsv-kirchseeon-marktlauf.de</strong></p>
                    <div id="bestaetigung-assets" hidden
                         style="width:100%;flex-basis:100%;margin-top:.5rem;padding:.6rem .8rem;border:1px solid #d9d9d9;border-radius:8px;background:rgba(0,150,64,.04);font-size:.9rem;">
                        <div style="font-weight:600;margin-bottom:.35rem;">📎 Anhänge der Bestätigung <span id="ba-status" style="font-weight:400;color:#666;"></span></div>
                        <div id="ba-list" style="display:flex;flex-direction:column;gap:.25rem;"></div>
                    </div>
                </form>
            </div>

            <div class="filter-merk-row">
                <div class="filter-col">
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
                </div>

                <div class="merkfeld-card" id="merkfeld-wrap">
                    <textarea id="merkfeld-text" rows="6" data-csrf="<?= htmlspecialchars($csrfToken) ?>"
                              placeholder="📌 Merkfeld — Bankverbindung, Vereins-/Steuernummer …&#10;Doppelklick sperrt &amp; speichert, erneuter Doppelklick entsperrt."><?= htmlspecialchars($merkfeld) ?></textarea>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-check"><input type="checkbox" id="check-all" title="Alle auswählen"></th>
                            <th>Firma</th>
                            <th>Ansprechpartner</th>
                            <th>Branche</th>
                            <th>Paket</th>
                            <th>Summe</th>
                            <th>Status</th>
                            <th>Wiedervorlage</th>
                            <?php if ($hasRechnung): ?><th class="col-rechnung" title="Rechnungsanschrift hinterlegt?">Rechnung</th><?php endif; ?>
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
                            ?>
                            <?php foreach ($sponsoren as $s): ?>
                                <?php
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
                                <tr class="<?= $rowClass ?>">
                                    <td class="col-check">
                                        <?php if (!$s['kein_kontakt']): ?>
                                            <input type="checkbox" class="row-check" name="sponsor_ids[]" value="<?= $s['id'] ?>" form="versand-form">
                                        <?php endif; ?>
                                    </td>
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
                                        <?php
                                        $bArr = [];
                                        if (!empty($s['branche'])) {
                                            $dec = json_decode($s['branche'], true);
                                            $bArr = is_array($dec) ? $dec : [$s['branche']];
                                        }
                                        $bFirst = $bArr[0] ?? '';
                                        ?>
                                        <select class="inline-select branche-select"
                                                data-id="<?= $s['id'] ?>" data-field="branche" title="Branche ändern">
                                            <option value="" <?= $bFirst === '' ? 'selected' : '' ?>>–</option>
                                            <?php foreach ($branchen as $b): ?>
                                                <option value="<?= htmlspecialchars($b) ?>" <?= $bFirst === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (count($bArr) > 1): ?>
                                            <div style="font-size:0.7rem;color:var(--text-light);margin-top:0.2rem">+<?= count($bArr) - 1 ?> weitere</div>
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
                                    <?php if ($hasRechnung): ?>
                                        <?php
                                        $hatRechnung = !empty($s['rechnung_firma']) || !empty($s['rechnung_strasse'])
                                            || !empty($s['rechnung_plz']) || !empty($s['rechnung_ort']) || !empty($s['rechnung_email']);
                                        ?>
                                        <td class="col-rechnung" title="<?= $hatRechnung ? 'Rechnungsanschrift hinterlegt' : 'Keine Rechnungsanschrift hinterlegt' ?>">
                                            <span class="<?= $hatRechnung ? 'rechnung-ja' : 'rechnung-nein' ?>"><?= $hatRechnung ? '✓' : '✗' ?></span>
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
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
    // Sponsor-Auswahl + Versand
    (function() {
        const checkAll = document.getElementById('check-all');
        const countLabel = document.getElementById('versand-count');

        function rowChecks() {
            return Array.prototype.slice.call(document.querySelectorAll('.row-check'));
        }
        function selectedCount() {
            return rowChecks().filter(function(c) { return c.checked; }).length;
        }
        function updateCount() {
            if (countLabel) {
                countLabel.textContent = selectedCount() + ' ausgewählt';
            }
        }

        if (checkAll) {
            checkAll.addEventListener('change', function() {
                rowChecks().forEach(function(c) { c.checked = checkAll.checked; });
                updateCount();
            });
        }
        rowChecks().forEach(function(c) {
            c.addEventListener('change', updateCount);
        });
        updateCount();

        // Opt-out-Liste der Bestätigungs-Anhänge (Plakate + Assets): lazy laden, sobald
        // „Bestätigung" gewählt ist. Die Abwahl lebt browser-seitig (localStorage, derselbe
        // Schlüssel wie im Brief-Editor) und gilt bis zum nächsten Versand.
        const typSel   = document.getElementById('anschreiben_typ');
        const baBox     = document.getElementById('bestaetigung-assets');
        const baList    = document.getElementById('ba-list');
        const baStatus  = document.getElementById('ba-status');
        const versandForm = document.getElementById('versand-form');
        let baLoaded = false;

        const ABWAHL_KEY = 'mkl_anhang_abwahl';
        function abwahlLoad() {
            try { var s = JSON.parse(localStorage.getItem(ABWAHL_KEY) || '{}'); return { plakat: s.plakat || [], asset: s.asset || [] }; }
            catch(e) { return { plakat: [], asset: [] }; }
        }
        function abwahlSave(s) { try { localStorage.setItem(ABWAHL_KEY, JSON.stringify(s)); } catch(e) {} }

        // Reset nach erfolgreichem Bestätigungs-Versand: Abwahl leeren → wieder alle Anhänge dran.
        <?php if ($bestaetigungVersandDone): ?>
        try { localStorage.removeItem(ABWAHL_KEY); } catch(e) {}
        <?php endif; ?>

        function renderGruppe(gruppe, titel, items) {
            if (!items || items.length === 0) return 0;
            const state = abwahlLoad();
            const h = document.createElement('div');
            h.style.cssText = 'font-weight:600;font-size:.82rem;color:#555;margin:.35rem 0 .1rem;';
            h.textContent = titel;
            baList.appendChild(h);
            items.forEach(function(f) {
                const lbl = document.createElement('label');
                lbl.style.cssText = 'display:flex;align-items:center;gap:.4rem;cursor:pointer;';
                const cb = document.createElement('input');
                cb.type = 'checkbox'; cb.className = 'anhang-abwahl-send'; cb.value = f.id;
                cb.dataset.group = gruppe;
                cb.checked = (state[gruppe] || []).indexOf(f.id) === -1;
                cb.addEventListener('change', function() {
                    const s = abwahlLoad();
                    const arr = s[gruppe] || [];
                    const i = arr.indexOf(f.id);
                    if (cb.checked) { if (i !== -1) arr.splice(i, 1); }
                    else if (i === -1) { arr.push(f.id); }
                    s[gruppe] = arr;
                    abwahlSave(s);
                });
                const span = document.createElement('span');
                span.textContent = f.name;
                lbl.appendChild(cb); lbl.appendChild(span);
                baList.appendChild(lbl);
            });
            return items.length;
        }

        function loadBestaetigungAssets() {
            if (baLoaded || !baList) return;
            baLoaded = true;
            baStatus.textContent = '… lädt';
            fetch('api/bestaetigung_assets.php', { headers: { 'X-Requested-With': 'fetch' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    baList.innerHTML = '';
                    if (!d || !d.ok) { baStatus.textContent = '⚠️ Ordner nicht lesbar'; return; }
                    let n = renderGruppe('plakat', 'Plakate', d.plakat);
                    n += renderGruppe('asset', 'Bestätigungs-Anhänge', d.asset);
                    if (n === 0) {
                        baStatus.textContent = d.configured === false
                            ? '— keine Anhänge (Ordner nicht festgelegt/leer)'
                            : '— keine Anhänge vorhanden';
                        return;
                    }
                    baStatus.textContent = '(alle vorausgewählt — zum Weglassen abwählen; gilt bis zum nächsten Versand)';
                })
                .catch(function() { baStatus.textContent = '⚠️ Ordner nicht lesbar'; baLoaded = false; });
        }

        function syncBaVisibility() {
            if (!baBox) return;
            const on = typSel && typSel.value === 'bestaetigung';
            baBox.hidden = !on;
            if (on) loadBestaetigungAssets();
        }
        if (typSel) { typSel.addEventListener('change', syncBaVisibility); syncBaVisibility(); }

        window.confirmVersand = function() {
            const n = selectedCount();
            if (n === 0) {
                alert('Bitte zuerst mindestens einen Sponsor auswählen.');
                return false;
            }
            const typ = document.getElementById('anschreiben_typ');
            const typLabels = { folgejahr: 'Folgejahr-Anschreiben', frei: 'Freier Brief', erstanschreiben: 'Erstanschreiben', bestaetigung: 'Bestätigung Sponsoring' };
            const typLabel = (typ && typLabels[typ.value]) || 'Erstanschreiben';
            let ok;
            if (n === 1) {
                ok = confirm('1 Sponsor ausgewählt.\n\n' + typLabel + ' jetzt senden?\n'
                    + '(Hat der Sponsor mehrere Kontakte im Anschreiben markiert, gehen alle einzeln personalisiert raus.)');
            } else {
                ok = confirm(n + ' Sponsoren ausgewählt.\n\n' + typLabel + ' in die Sende-Queue stellen? '
                    + 'Der Versand läuft anschließend über das CLI-Script (15 Sek. Abstand pro Mail).');
            }
            if (!ok) return false;
            // Abgewählte Anhänge je Gruppe als exclude_*_fids[] mitschicken (nur Bestätigung).
            if (versandForm) {
                versandForm.querySelectorAll('input[name="exclude_asset_fids[]"], input[name="exclude_plakat_fids[]"]').forEach(function(el) { el.remove(); });
                if (typ && typ.value === 'bestaetigung') {
                    document.querySelectorAll('.anhang-abwahl-send').forEach(function(cb) {
                        if (cb.checked) return;
                        const hid = document.createElement('input');
                        hid.type = 'hidden';
                        hid.name = cb.dataset.group === 'plakat' ? 'exclude_plakat_fids[]' : 'exclude_asset_fids[]';
                        hid.value = cb.value;
                        versandForm.appendChild(hid);
                    });
                }
            }
            return true;
        };
    })();

    // Merkfeld: Doppelklick sperrt & speichert, erneuter Doppelklick entsperrt
    (function() {
        const wrap = document.getElementById('merkfeld-wrap');
        if (!wrap) return;
        const ta = document.getElementById('merkfeld-text');
        const leftCol = document.querySelector('.filter-col');
        const csrf = ta.dataset.csrf;
        let locked = false;

        // Höhe: mind. so hoch wie das Umfeld (Filter+Stats), wächst mit dem Inhalt
        function autosize() {
            ta.style.height = 'auto';
            let h = ta.scrollHeight;
            if (leftCol && window.matchMedia('(min-width: 641px)').matches) {
                h = Math.max(h, leftCol.offsetHeight);
            }
            ta.style.height = h + 'px';
        }

        function setLocked(v) {
            locked = v;
            ta.readOnly = v;
            wrap.classList.toggle('locked', v);
            ta.title = v
                ? '🔒 gesperrt — Doppelklick zum Bearbeiten'
                : '✏️ Doppelklick sperrt & speichert';
            autosize();
        }

        function save() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('merkfeld', ta.value);
            ta.title = '… speichern';
            fetch('api/sponsor_merkfeld.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.ok) {
                        setLocked(true);
                        ta.title = '🔒 gespeichert';
                    } else {
                        ta.title = '⚠️ ' + ((d && d.message) || 'Fehler beim Speichern');
                    }
                })
                .catch(function() { ta.title = '⚠️ Fehler beim Speichern'; });
        }

        ta.addEventListener('dblclick', function() {
            if (locked) {
                setLocked(false);
                ta.focus();
            } else {
                save();
            }
        });

        ta.addEventListener('input', autosize);
        window.addEventListener('resize', autosize);

        // Startzustand: mit Inhalt = gesperrt, leer = direkt beschreibbar
        setLocked(ta.value.trim() !== '');
        autosize();
    })();

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
    </script>
</body>
</html>
