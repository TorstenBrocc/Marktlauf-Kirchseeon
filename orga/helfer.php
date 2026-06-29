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
$filterSlot = $_GET['slot'] ?? '';
$filterBeitrag = $_GET['beitrag'] ?? '';

$pdo = getDbConnection();

$sql = '
    SELECT h.*,
           GROUP_CONCAT(DISTINCT CONCAT(hs.tag, " ", hs.zeitfenster) ORDER BY hs.tag SEPARATOR ", ") AS slots,
           GROUP_CONCAT(DISTINCT CONCAT(hb.typ, COALESCE(CONCAT(": ", hb.freitext), "")) SEPARATOR ", ") AS beitraege
    FROM helfer h
    LEFT JOIN helfer_slots hs ON h.id = hs.helfer_id
    LEFT JOIN helfer_beitrag hb ON h.id = hb.helfer_id
';

$where = [];
$params = [];

if ($filterStatus !== '' && in_array($filterStatus, ['neu', 'bestaetigt', 'abgelehnt'], true)) {
    $where[] = 'h.status = :status';
    $params['status'] = $filterStatus;
}

if ($filterSlot !== '') {
    $where[] = 'hs.tag = :slot';
    $params['slot'] = $filterSlot;
}

if ($filterBeitrag !== '' && in_array($filterBeitrag, ['kuchen', 'equipment', 'sonstiges'], true)) {
    $where[] = 'hb.typ = :beitrag';
    $params['beitrag'] = $filterBeitrag;
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

$availableSlots = [];
$slotStmt = $pdo->query('SELECT DISTINCT tag FROM helfer_slots ORDER BY tag');
while ($row = $slotStmt->fetch()) {
    $availableSlots[] = $row['tag'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Helfer | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
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
        .btn-danger {
            background-color: #dc3545;
            color: var(--white);
        }
        .btn-danger:hover {
            background: #b71c1c;
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
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Marktlauf Orga</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item active">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item">
                    <a href="sponsoren.php">Sponsoren</a>
                </li>
                <li class="nav-item">
                    <a href="dateien.php">Dateien</a>
                </li>
                <li class="nav-item">
                    <a href="ticker.php">Live-Ticker</a>
                    <span class="badge">Phase 3</span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item">
                    <a href="users.php">Benutzerverwaltung</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="settings.php">Einstellungen</a>
                    <span class="badge">Phase 2</span>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1>Helfer-Übersicht</h1>
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
                <div class="form-group">
                    <label>Slot</label>
                    <select name="slot" onchange="this.form.submit()">
                        <option value="">Alle Tage</option>
                        <?php foreach ($availableSlots as $slot): ?>
                            <option value="<?= htmlspecialchars($slot) ?>" <?= $filterSlot === $slot ? 'selected' : '' ?>>
                                <?= htmlspecialchars($slot) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Beitrag</label>
                    <select name="beitrag" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <option value="kuchen" <?= $filterBeitrag === 'kuchen' ? 'selected' : '' ?>>Kuchen</option>
                        <option value="equipment" <?= $filterBeitrag === 'equipment' ? 'selected' : '' ?>>Equipment</option>
                        <option value="sonstiges" <?= $filterBeitrag === 'sonstiges' ? 'selected' : '' ?>>Sonstiges</option>
                    </select>
                </div>
                <?php if ($filterStatus || $filterSlot || $filterBeitrag): ?>
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
                            <th>Slots</th>
                            <th>Beitrag</th>
                            <th>Anmeldung</th>
                            <th>Notiz</th>
                            <?php if ($isAdmin): ?><th>Aktion</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($helfer)): ?>
                            <tr>
                                <td colspan="<?= $isAdmin ? 9 : 8 ?>">Keine Helfer gefunden.</td>
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
                                    <td class="cell-small"><?= htmlspecialchars($h['slots'] ?? '-') ?></td>
                                    <td class="cell-small"><?= htmlspecialchars($h['beitraege'] ?? '-') ?></td>
                                    <td class="cell-small"><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                                    <td class="notiz-cell">
                                        <form method="post" action="api/helfer_notiz.php" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <textarea name="notiz" placeholder="Notiz..."><?= htmlspecialchars($h['notiz'] ?? '') ?></textarea>
                                            <button type="submit" title="Notiz speichern">Speichern</button>
                                        </form>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                    <td>
                                        <form method="post" action="api/helfer_delete.php" class="inline-form" onsubmit="return confirm('Helfer wirklich löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="helfer_id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn-danger" title="Helfer löschen">Löschen</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
