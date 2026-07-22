<?php
/**
 * Helfer-Übersicht (Admin + Orga)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$filterStatus = $_GET['status'] ?? '';

$pdo = getDbConnection();

// Helferübersicht = Kontakt & Status. Verfügbarkeit/Slots leben im Einsatzplan,
// Kuchen/Sonstiges in "Kuchen & Sonstiges". Hier bleibt als Einsatz-Kontext nur
// die verbindliche Schicht-Zuteilung (nicht die Selbstmeldung).
$sql = '
    SELECT h.*,
           GROUP_CONCAT(DISTINCT sc.titel ORDER BY sc.titel SEPARATOR ", ") AS schichten
    FROM helfer h
    LEFT JOIN schicht_zuteilung sz ON h.id = sz.helfer_id
    LEFT JOIN schichten sc ON sc.id = sz.schicht_id
';

$where = [];
$params = [];

if ($filterStatus !== '' && in_array($filterStatus, ['neu', 'bestaetigt', 'abgelehnt'], true)) {
    $where[] = 'h.status = :status';
    $params['status'] = $filterStatus;
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY h.id ORDER BY h.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$helfer = $stmt->fetchAll();

$countStmt = $pdo->query('SELECT COUNT(*) FROM helfer');
$totalCount = (int) $countStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Helfer | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
            vertical-align: top;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-neu { background: #fff3cd; color: #856404; }
        .status-bestaetigt { background: var(--success-bg); color: var(--success); }
        .status-abgelehnt { background: var(--error-bg); color: var(--error); }
        .foto-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .foto-ja { background: var(--success-bg); color: var(--success); }
        .foto-nein { background: var(--error-bg); color: var(--error); }
        .foto-minor { margin-top: 0.25rem; color: var(--text-light); font-size: 0.7rem; }
        .inline-form {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }
        .inline-form select,
        .inline-form input[type="text"] {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        .inline-form button {
            padding: 4px 10px;
            font-size: 0.8rem;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .inline-form button:hover {
            background: var(--primary-dark);
        }
        .btn-confirm {
            padding: 4px 10px;
            font-size: 0.75rem;
            background: #28a745;
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-confirm:hover {
            background: #218838;
        }
        .notiz-cell {
            max-width: 200px;
        }
        .notiz-cell textarea {
            width: 100%;
            min-height: 40px;
            padding: 0.25rem;
            font-size: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            resize: vertical;
        }
        .table-wrap {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow-card);
        }
        .stats {
            font-size: 0.875rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        .cell-small {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        .content-header--with-action {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .aktion-cell {
            white-space: nowrap;
        }
        .aktion-cell > a,
        .aktion-cell > form {
            display: block;
            margin-bottom: 0.25rem;
        }
        .aktion-cell > a {
            text-align: center;
        }
    </style>
</head>
<body>
<?php $activeNav = 'helfer'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header content-header--with-action">
                <h1>Helfer-Übersicht</h1>
                <a href="https://atsv-kirchseeon-marktlauf.de/helfer-anmeldung.php?token=2650B7543C102D8E528F96021885F5EE3BE8361FBA6ADE4611023A9D02875FEB"
                   class="btn btn-primary btn-small"
                   target="_blank" rel="noopener noreferrer"
                   title="Öffentliche Maske der Helfereinladung/-anmeldung öffnen">
                    Maske Helfereinladung
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:middle;margin-left:4px;">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
                <a href="https://atsv-kirchseeon-marktlauf.de/helfer/zugang.php"
                   class="btn btn-secondary btn-small"
                   target="_blank" rel="noopener noreferrer"
                   title="Briefing-Übersicht (persönliche Helfer-Maske) öffnen">
                    Briefing-Übersicht
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:middle;margin-left:4px;">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <form method="get" class="filter-bar">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <option value="neu" <?= $filterStatus === 'neu' ? 'selected' : '' ?>>Neu</option>
                        <option value="bestaetigt" <?= $filterStatus === 'bestaetigt' ? 'selected' : '' ?>>Bestätigt</option>
                        <option value="abgelehnt" <?= $filterStatus === 'abgelehnt' ? 'selected' : '' ?>>Abgelehnt</option>
                    </select>
                </div>
                <?php if ($filterStatus): ?>
                    <a href="helfer.php" class="btn btn-small btn-secondary">Filter zurücksetzen</a>
                <?php endif; ?>
            </form>

            <p class="stats"><?= count($helfer) ?> von <?= $totalCount ?> Helfern angezeigt</p>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Telefon</th>
                            <th>Status</th>
                            <th>Fotofreigabe</th>
                            <th>Einsatz</th>
                            <th>Anmeldung</th>
                            <th>Notiz</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($helfer)): ?>
                            <tr>
                                <td colspan="9">Keine Helfer gefunden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($helfer as $h): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($h['vorname'] . ' ' . $h['nachname']) ?></strong>
                                    </td>
                                    <td><a href="mailto:<?= htmlspecialchars($h['email']) ?>"><?= htmlspecialchars($h['email']) ?></a></td>
                                    <td><?= htmlspecialchars($h['phone']) ?></td>
                                    <td>
                                        <form method="post" action="api/helfer_status.php" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="neu" <?= $h['status'] === 'neu' ? 'selected' : '' ?>>Neu</option>
                                                <option value="bestaetigt" <?= $h['status'] === 'bestaetigt' ? 'selected' : '' ?>>Bestätigt</option>
                                                <option value="abgelehnt" <?= $h['status'] === 'abgelehnt' ? 'selected' : '' ?>>Abgelehnt</option>
                                            </select>
                                        </form>
                                        <?php if ($h['status'] === 'neu'): ?>
                                        <form method="post" action="api/helfer_bestaetigen.php" class="inline-form" style="margin-top: 0.25rem;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn-confirm" title="Bestätigen und Zugangslink senden">Bestätigen + Mail</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-small">
                                        <?php
                                        $ja = ($h['consent_photo'] ?? 'no') === 'yes';
                                        $minor = (int) ($h['is_minor'] ?? 0) === 1;
                                        $tsTitle = !empty($h['consent_ts'])
                                            ? 'Einwilligung erteilt am ' . date('d.m.Y H:i', strtotime((string) $h['consent_ts']))
                                            : 'Kein Einwilligungszeitpunkt hinterlegt';
                                        ?>
                                        <span class="foto-badge <?= $ja ? 'foto-ja' : 'foto-nein' ?>" title="<?= htmlspecialchars($tsTitle) ?>">
                                            <?= $ja ? 'Ja' : 'Nein' ?>
                                        </span>
                                        <?php if ($minor): ?>
                                            <div class="foto-minor" title="Erziehungsberechtigte Person: <?= htmlspecialchars((string) ($h['guardian_name'] ?? '–') ?: '–') ?>">
                                                minderjährig · erz.-ber.: <?= htmlspecialchars((string) ($h['guardian_name'] ?? '–') ?: '–') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cell-small"><?= htmlspecialchars($h['schichten'] ?? '') ?: '-' ?></td>
                                    <td class="cell-small"><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                                    <td class="notiz-cell">
                                        <form method="post" action="api/helfer_notiz.php" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <textarea name="notiz" placeholder="Notiz..."><?= htmlspecialchars($h['notiz'] ?? '') ?></textarea>
                                            <button type="submit" title="Notiz speichern">Speichern</button>
                                        </form>
                                    </td>
                                    <td class="aktion-cell">
                                        <a href="helfer_form.php?id=<?= $h['id'] ?>"
                                           class="btn-action" title="Helfer-Stammdaten bearbeiten">Bearbeiten</a>
                                        <a href="https://atsv-kirchseeon-marktlauf.de/helfer/zugang.php?uuid=<?= htmlspecialchars(urlencode($h['uuid'])) ?>"
                                           class="btn-action" target="_blank" rel="noopener noreferrer"
                                           title="Briefing-Ansicht dieses Helfers öffnen (zum Nachvollziehen der Darstellung)">Briefing</a>
                                        <?php if ($isAdmin): ?>
                                        <form method="post" action="api/helfer_delete.php" class="inline-form" onsubmit="return confirm('Helfer wirklich löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn-action btn-danger" title="Helfer löschen">Löschen</button>
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
