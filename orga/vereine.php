<?php
/**
 * Vereine & regionale Laufevents — Übersicht (Admin + Orga).
 * Kontaktliste zum Anschreiben (Einladung / Veranstalter-Vernetzung).
 * Analog zur Sponsoren-Übersicht, aber ohne Paket/Summe.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/verein_status.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$importReport = $_SESSION['import_report'] ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['import_report']);

$filterKategorie = $_GET['kategorie'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$pdo = getDbConnection();

$sql = 'SELECT * FROM vereine';
$where = [];
$params = [];

if (in_array($filterKategorie, ['verein', 'laufevent'], true)) {
    $where[] = 'kategorie = :kategorie';
    $params['kategorie'] = $filterKategorie;
}
if ($filterStatus !== '' && vereinStatusValid($filterStatus)) {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
}
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY kategorie ASC, name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eintraege = $stmt->fetchAll();

$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM vereine')->fetchColumn();
$vereinCount = (int) $pdo->query("SELECT COUNT(*) FROM vereine WHERE kategorie = 'verein'")->fetchColumn();
$eventCount = (int) $pdo->query("SELECT COUNT(*) FROM vereine WHERE kategorie = 'laufevent'")->fetchColumn();

$katLabel = ['verein' => 'Verein', 'laufevent' => 'Laufevent'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Vereine & Laufevents | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .page-header { display: flex; align-items: center; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1.5rem; }
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .filter-bar .form-group { margin-bottom: 0; }
        .filter-bar label { font-size: 0.75rem; margin-bottom: 0.25rem; }
        .filter-bar select { padding: 0.5rem; min-width: 150px; }
        .stats { display: flex; gap: 2rem; font-size: 0.875rem; color: var(--text-light); margin-bottom: 1rem; flex-wrap: wrap; }
        .stat-value { font-weight: 600; color: var(--primary); }
        .action-bar {
            display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; align-items: center;
            margin-bottom: 1.25rem; padding: 0.6rem 0.875rem; background: var(--white);
            border: 1px solid var(--border); border-radius: 8px; font-size: 0.8rem;
        }
        .action-bar form { display: flex; gap: 0.4rem; align-items: center; }
        .action-bar input[type="file"] { font-size: 0.78rem; }
        .action-bar label { font-size: 0.8rem; font-weight: 600; }
        .action-bar-sep { width: 1px; align-self: stretch; min-height: 1.5rem; background: var(--border); }
        .versand-count { font-size: 0.78rem; color: var(--text-light); }
        .versand-hint { flex-basis: 100%; margin: 0.15rem 0 0; font-size: 0.8rem; color: var(--text-light); }
        .import-report {
            font-size: 0.8rem; background: #fff8f8; border: 1px solid #f5c6cb; border-radius: 6px;
            padding: 0.5rem 0.75rem; margin-bottom: 1rem; max-height: 180px; overflow-y: auto;
        }
        .import-report ul { margin: 0.25rem 0 0 1rem; }
        .table-wrap { overflow-x: auto; border-radius: 8px; box-shadow: var(--shadow-card); }
        .data-table { width: 100%; border-collapse: collapse; background: var(--white); border-radius: 8px; overflow: hidden; }
        .data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th {
            background: var(--bg); font-weight: 600; font-size: 0.75rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-light);
        }
        .data-table td { font-size: 0.875rem; vertical-align: top; }
        .data-table tr:hover { background: #fafafa; }
        .col-check { width: 32px; text-align: center; }
        .kat-badge {
            display: inline-block; padding: 0.125rem 0.45rem; border-radius: 3px;
            font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 600;
        }
        .kat-verein { background: #e3f0ff; color: #1a5fb4; }
        .kat-laufevent { background: #e7f6ec; color: #1c7a41; }
        .name-sub { font-size: 0.75rem; color: var(--text-light); margin-top: 0.15rem; }
        .ap-name { font-size: 0.875rem; }
        .ap-email, .ap-tel { font-size: 0.75rem; color: var(--text-light); }
        .relevanz-cell { font-size: 0.8rem; max-width: 240px; }
        .inline-select {
            padding: 0.25rem 0.4rem; font-size: 0.75rem; border: 1px solid var(--border);
            border-radius: 4px; background: var(--white); cursor: pointer; max-width: 150px;
        }
        .inline-select.saving { opacity: 0.5; }
        .inline-select.saved { border-color: var(--primary); }
        .status-select { border-left-width: 4px; }
        .status-select.ampel-grau  { border-left-color: #9aa0a6; }
        .status-select.ampel-blau  { border-left-color: #2b7de9; }
        .status-select.ampel-gelb  { border-left-color: #f4b400; }
        .status-select.ampel-gruen { border-left-color: var(--primary); }
        .status-select.ampel-rot   { border-left-color: var(--error); }
        .status-partner-row { background: rgba(76, 175, 80, 0.12); }
        .status-kein_interesse-row { background: rgba(211, 47, 47, 0.10); }
        .status-in_kontakt-row { background: rgba(244, 180, 0, 0.14); }
        .status-angeschrieben-row { background: rgba(43, 125, 233, 0.10); }
        .btn-icon {
            padding: 0.25rem 0.5rem; font-size: 0.75rem; background: var(--border); color: var(--text);
            border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn-icon:hover { background: #ccc; }
        .notiz-form { display: flex; flex-direction: column; gap: 0.25rem; }
        .notiz-form textarea {
            width: 170px; font-size: 0.75rem; padding: 0.35rem; border: 1px solid var(--border);
            border-radius: 4px; resize: vertical;
        }
        .notiz-save { display: none; align-self: flex-start; }
        .notiz-form.dirty .notiz-save { display: inline-block; }
    </style>
</head>
<body>
<?php $activeNav = 'vereine'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>Vereine &amp; regionale Laufevents</h1>
                <a href="verein_form.php" class="btn btn-primary btn-small">+ Neu anlegen</a>
                <a href="vereine_briefe.php" class="btn btn-secondary btn-small">Anschreiben bearbeiten</a>
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

            <?php $exportQuery = http_build_query(array_filter(['kategorie' => $filterKategorie, 'status' => $filterStatus])); ?>
            <div class="action-bar">
                <form method="post" action="api/verein_import.php" enctype="multipart/form-data"
                      onsubmit="return confirm('CSV jetzt importieren? Dubletten (Name + Ort) werden übersprungen.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <label for="csv_datei">CSV-Import</label>
                    <input type="file" id="csv_datei" name="csv_datei" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-small btn-secondary">Importieren</button>
                </form>
                <div class="action-bar-sep"></div>
                <a href="api/verein_export.php<?= $exportQuery ? '?' . $exportQuery : '' ?>" class="btn btn-small btn-secondary">
                    CSV-Export<?= ($filterKategorie || $filterStatus) ? ' (gefiltert)' : '' ?>
                </a>
                <div class="action-bar-sep"></div>
                <form id="versand-form" method="post" action="api/verein_versand.php"
                      onsubmit="return confirmVersand();" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="btn btn-small btn-primary">Ausgewählte anschreiben</button>
                    <span class="versand-count" id="versand-count">0 ausgewählt</span>
                    <p class="versand-hint">
                        Anschreiben richtet sich automatisch nach der Kategorie (Verein bzw. Laufevent).
                        Versand über <strong>info@atsv-kirchseeon-marktlauf.de</strong>.
                    </p>
                </form>
            </div>

            <form method="get" class="filter-bar">
                <div class="form-group">
                    <label>Kategorie</label>
                    <select name="kategorie" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <option value="verein" <?= $filterKategorie === 'verein' ? 'selected' : '' ?>>Vereine</option>
                        <option value="laufevent" <?= $filterKategorie === 'laufevent' ? 'selected' : '' ?>>Laufevents</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <?php foreach (VEREIN_STATUS as $key => $meta): ?>
                            <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($filterKategorie || $filterStatus): ?>
                    <a href="vereine.php" class="btn btn-small btn-secondary">Filter zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="stats">
                <span><?= count($eintraege) ?> von <?= $totalCount ?> Einträgen</span>
                <span>Vereine: <span class="stat-value"><?= $vereinCount ?></span></span>
                <span>Laufevents: <span class="stat-value"><?= $eventCount ?></span></span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-check"><input type="checkbox" id="check-all" title="Alle auswählen"></th>
                            <th>Name</th>
                            <th>Ansprechpartner</th>
                            <th>Laufsport-Relevanz / Distanzen</th>
                            <th>Website</th>
                            <th>Status</th>
                            <th>Notiz</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eintraege)): ?>
                            <tr><td colspan="8">Noch keine Einträge. Neu anlegen oder CSV importieren.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eintraege as $e): ?>
                                <?php
                                $rowClass = '';
                                if ($e['status'] === 'partner') $rowClass = 'status-partner-row';
                                elseif ($e['status'] === 'kein_interesse') $rowClass = 'status-kein_interesse-row';
                                elseif ($e['status'] === 'in_kontakt') $rowClass = 'status-in_kontakt-row';
                                elseif ($e['status'] === 'angeschrieben') $rowClass = 'status-angeschrieben-row';
                                $apName = trim(($e['vorname'] ?? '') . ' ' . ($e['nachname'] ?? ''));
                                $hasEmail = trim((string) ($e['email'] ?? '')) !== '';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="col-check">
                                        <?php if ($hasEmail): ?>
                                            <input type="checkbox" class="row-check" name="verein_ids[]" value="<?= (int) $e['id'] ?>" form="versand-form">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="verein_form.php?id=<?= (int) $e['id'] ?>"><strong><?= htmlspecialchars($e['name']) ?></strong></a>
                                        <span class="kat-badge kat-<?= $e['kategorie'] ?>"><?= htmlspecialchars($katLabel[$e['kategorie']] ?? $e['kategorie']) ?></span>
                                        <div class="name-sub">
                                            <?php
                                            $sub = [];
                                            if (!empty($e['ort'])) $sub[] = htmlspecialchars($e['ort']);
                                            if (!empty($e['entfernung'])) $sub[] = htmlspecialchars($e['entfernung']);
                                            if ($e['kategorie'] === 'laufevent' && !empty($e['termin'])) $sub[] = 'Termin: ' . htmlspecialchars($e['termin']);
                                            if ($e['kategorie'] === 'laufevent' && !empty($e['veranstalter'])) $sub[] = 'Veranst.: ' . htmlspecialchars($e['veranstalter']);
                                            echo implode(' · ', $sub);
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($apName !== '' || $hasEmail || !empty($e['telefon'])): ?>
                                            <?php if ($apName !== ''): ?>
                                                <div class="ap-name"><?= htmlspecialchars($apName) ?><?php if (!empty($e['funktion'])): ?> <span class="ap-tel">(<?= htmlspecialchars($e['funktion']) ?>)</span><?php endif; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($e['telefon'])): ?>
                                                <div class="ap-tel"><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $e['telefon'])) ?>"><?= htmlspecialchars($e['telefon']) ?></a></div>
                                            <?php endif; ?>
                                            <?php if ($hasEmail): ?>
                                                <div class="ap-email"><a href="mailto:<?= htmlspecialchars($e['email']) ?>"><?= htmlspecialchars($e['email']) ?></a></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="ap-tel">– keine E-Mail –</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="relevanz-cell"><?= nl2br(htmlspecialchars((string) ($e['relevanz'] ?? ''))) ?: '–' ?></td>
                                    <td>
                                        <?php if (!empty($e['website'])):
                                            $url = preg_match('#^https?://#i', $e['website']) ? $e['website'] : 'https://' . $e['website']; ?>
                                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="ap-email"><?= htmlspecialchars($e['website']) ?></a>
                                        <?php else: ?>–<?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="inline-select status-select ampel-<?= vereinStatusAmpel($e['status']) ?>"
                                                data-id="<?= (int) $e['id'] ?>" data-field="status" title="Status ändern">
                                            <?php foreach (VEREIN_STATUS as $key => $meta): ?>
                                                <option value="<?= $key ?>" <?= $e['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <form method="post" action="api/verein_crud.php" class="notiz-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="notiz">
                                            <input type="hidden" name="verein_id" value="<?= (int) $e['id'] ?>">
                                            <textarea name="notizen" rows="2" placeholder="Notiz…"
                                                      oninput="this.closest('.notiz-form').classList.add('dirty')"><?= htmlspecialchars($e['notizen'] ?? '') ?></textarea>
                                            <button type="submit" class="btn-icon notiz-save">Speichern</button>
                                        </form>
                                    </td>
                                    <td><a href="verein_form.php?id=<?= (int) $e['id'] ?>" class="btn-icon">Bearbeiten</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
    // Auswahl + Versand
    (function() {
        const checkAll = document.getElementById('check-all');
        const countLabel = document.getElementById('versand-count');
        function rowChecks() { return Array.prototype.slice.call(document.querySelectorAll('.row-check')); }
        function selectedCount() { return rowChecks().filter(function(c) { return c.checked; }).length; }
        function updateCount() { if (countLabel) countLabel.textContent = selectedCount() + ' ausgewählt'; }
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                rowChecks().forEach(function(c) { c.checked = checkAll.checked; });
                updateCount();
            });
        }
        rowChecks().forEach(function(c) { c.addEventListener('change', updateCount); });
        updateCount();
        window.confirmVersand = function() {
            const n = selectedCount();
            if (n === 0) { alert('Bitte zuerst mindestens einen Eintrag mit E-Mail auswählen.'); return false; }
            if (n === 1) return confirm('1 Empfänger ausgewählt.\n\nAnschreiben jetzt sofort senden?');
            return confirm(n + ' Empfänger ausgewählt.\n\nIn die Sende-Queue stellen? Der Versand läuft anschließend über das CLI-Script (15 Sek. Abstand pro Mail).');
        };
    })();

    // Inline-Status direkt speichern
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const ampelClasses = ['ampel-grau', 'ampel-blau', 'ampel-gelb', 'ampel-gruen', 'ampel-rot'];
        document.querySelectorAll('.inline-select').forEach(function(sel) {
            sel.addEventListener('change', function() {
                const body = new URLSearchParams();
                body.set('action', 'inline_update');
                body.set('csrf_token', csrf);
                body.set('verein_id', sel.dataset.id);
                body.set('field', sel.dataset.field);
                body.set('value', sel.value);
                sel.classList.add('saving'); sel.classList.remove('saved');
                fetch('api/verein_crud.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        sel.classList.remove('saving');
                        if (d && d.ok) {
                            ampelClasses.forEach(function(c) { sel.classList.remove(c); });
                            if (d.ampel) sel.classList.add('ampel-' + d.ampel);
                            const row = sel.closest('tr');
                            if (row) {
                                row.classList.remove('status-partner-row', 'status-kein_interesse-row', 'status-in_kontakt-row', 'status-angeschrieben-row');
                                const map = { partner: 'status-partner-row', kein_interesse: 'status-kein_interesse-row', in_kontakt: 'status-in_kontakt-row', angeschrieben: 'status-angeschrieben-row' };
                                if (map[sel.value]) row.classList.add(map[sel.value]);
                            }
                            sel.classList.add('saved');
                            setTimeout(function() { sel.classList.remove('saved'); }, 1200);
                        } else {
                            alert((d && d.message) || 'Fehler beim Speichern.');
                        }
                    })
                    .catch(function() { sel.classList.remove('saving'); alert('Fehler beim Speichern.'); });
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
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) { link.addEventListener('click', closeSidebar); });
    })();
    </script>
</body>
</html>
