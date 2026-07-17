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
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['import_report']);

$filterStatus = $_GET['status'] ?? '';
$filterPaket = $_GET['paket'] ?? '';

$pdo = getDbConnection();

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

if ($filterPaket !== '' && in_array($filterPaket, ['hauptsponsor', 'gold', 'silber', 'bronze'], true)) {
    $where[] = 'paket = :paket';
    $params['paket'] = $filterPaket;
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY kein_kontakt ASC, firma ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sponsoren = $stmt->fetchAll();

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
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
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
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        .table-wrap {
            overflow-x: auto;
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
                        <option value="frei">Freier Brief</option>
                    </select>
                    <button type="submit" class="btn btn-small btn-primary">Ausgewählte anschreiben</button>
                    <span class="versand-count" id="versand-count">0 ausgewählt</span>
                    <p class="versand-hint">Versand erfolgt über <strong>info@atsv-kirchseeon-marktlauf.de</strong></p>
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
                        </select>
                    </div>
                    <?php if ($filterStatus || $filterPaket): ?>
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
                            <th>Notiz</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sponsoren)): ?>
                            <tr>
                                <td colspan="10">Keine Sponsoren gefunden.</td>
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
                                ?>
                                <tr class="<?= $s['kein_kontakt'] ? 'kein-kontakt-row' : ($s['status'] === 'zugesagt' ? 'status-zugesagt-row' : ($s['status'] === 'abgelehnt' ? 'status-abgelehnt-row' : '')) ?>">
                                    <td class="col-check">
                                        <?php if (!$s['kein_kontakt']): ?>
                                            <input type="checkbox" class="row-check" name="sponsor_ids[]" value="<?= $s['id'] ?>" form="versand-form">
                                        <?php endif; ?>
                                    </td>
                                    <td class="firma-cell">
                                        <a href="sponsor_form.php?id=<?= $s['id'] ?>">
                                            <strong><?= htmlspecialchars($s['firma']) ?></strong>
                                        </a>
                                        <?php if (isset($prioMeta[$prio])): ?>
                                            <span class="prio-badge <?= $prioMeta[$prio][1] ?>" title="Priorität"><?= $prioMeta[$prio][0] ?></span>
                                        <?php endif; ?>
                                        <?php if ($s['kein_kontakt']): ?>
                                            <span class="kein-kontakt-badge">Kein Kontakt</span>
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
                                        <select class="inline-select branche-select"
                                                data-id="<?= $s['id'] ?>" data-field="branche" title="Branche ändern">
                                            <option value="" <?= empty($s['branche']) ? 'selected' : '' ?>>–</option>
                                            <?php foreach ($branchen as $b): ?>
                                                <option value="<?= htmlspecialchars($b) ?>" <?= ($s['branche'] ?? '') === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="inline-select paket-select paket-<?= $s['paket'] ?: 'none' ?>"
                                                data-id="<?= $s['id'] ?>" data-field="paket" title="Paket ändern">
                                            <option value="" <?= !$s['paket'] ? 'selected' : '' ?>>–</option>
                                            <?php foreach (['hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber', 'bronze' => 'Bronze'] as $pk => $pl): ?>
                                                <option value="<?= $pk ?>" <?= $s['paket'] === $pk ? 'selected' : '' ?>><?= $pl ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= $s['summe'] ? number_format((float)$s['summe'], 2, ',', '.') . ' €' : '–' ?></td>
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

        window.confirmVersand = function() {
            const n = selectedCount();
            if (n === 0) {
                alert('Bitte zuerst mindestens einen Sponsor auswählen.');
                return false;
            }
            const typ = document.getElementById('anschreiben_typ');
            const typLabels = { folgejahr: 'Folgejahr-Anschreiben', frei: 'Freier Brief', erstanschreiben: 'Erstanschreiben' };
            const typLabel = (typ && typLabels[typ.value]) || 'Erstanschreiben';
            if (n === 1) {
                return confirm('1 Empfänger auswählt.\n\n' + typLabel + ' jetzt sofort senden?');
            }
            return confirm(n + ' Empfänger ausgewählt.\n\n' + typLabel + ' in die Sende-Queue stellen? '
                + 'Der Versand läuft anschließend über das CLI-Script (15 Sek. Abstand pro Mail).');
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
        const paketClasses = ['paket-hauptsponsor', 'paket-gold', 'paket-silber', 'paket-bronze', 'paket-none'];

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
                                    row.classList.toggle('status-zugesagt-row', sel.value === 'zugesagt');
                                }
                            } else if (sel.dataset.field === 'paket') {
                                applyClass(sel, paketClasses, 'paket-' + (d.paket || 'none'));
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
    </script>
</body>
</html>
