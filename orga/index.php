<?php
/**
 * Orga Dashboard Startseite
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$config = getConfig();

$helferCount = (int) $pdo->query('SELECT COUNT(*) FROM helfer')->fetchColumn();
$helferNeuCount = (int) $pdo->query("SELECT COUNT(*) FROM helfer WHERE status = 'neu'")->fetchColumn();

$sponsorCount = 0;
$sponsorSumme = 0;
try {
    $sponsorCount = (int) $pdo->query('SELECT COUNT(*) FROM sponsors')->fetchColumn();
    $sponsorSumme = (float) $pdo->query("SELECT COALESCE(SUM(summe), 0) FROM sponsors WHERE status IN ('zugesagt', 'bezahlt')")->fetchColumn();
} catch (PDOException $e) {
    // Table may not exist yet
}

$meineAufgaben = [];
$orgaAufgaben = [];
$orgaUsers = [];
try {
    $meineStmt = $pdo->prepare("
        SELECT 'orga' AS quelle, titel, faellig_am, status
        FROM aufgaben
        WHERE status != 'erledigt' AND verantwortlich_user_id = :user_id
        ORDER BY faellig_am ASC, created_at DESC
    ");
    $meineStmt->execute(['user_id' => $user['id']]);
    $meineAufgaben = $meineStmt->fetchAll();

    $orgaStmt = $pdo->query("
        SELECT a.*, u.name AS verantwortlich_name
        FROM aufgaben a
        LEFT JOIN users u ON a.verantwortlich_user_id = u.id
        ORDER BY
            CASE a.status WHEN 'offen' THEN 1 WHEN 'in_arbeit' THEN 2 ELSE 3 END,
            a.faellig_am ASC,
            a.created_at DESC
    ");
    $orgaAufgaben = $orgaStmt->fetchAll();

    $orgaUsers = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin', 'orga') AND active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet
}

$trelloBoardUrl = '';
try {
    $trelloStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $trelloStmt->execute(['key' => 'trello_board_url']);
    $trelloBoardUrl = $trelloStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table may not exist yet
}
if ($trelloBoardUrl === '') {
    $trelloBoardUrl = $config['trello_board_url'] ?? '';
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .meine-aufgaben {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .meine-aufgaben h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1rem;
            color: #856404;
        }
        .meine-aufgaben ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .meine-aufgaben li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .meine-aufgaben li:last-child { border-bottom: none; }
        .meine-aufgaben .aufgabe-titel { flex: 1; }
        .meine-aufgaben .aufgabe-faellig {
            font-size: 0.75rem;
            color: #856404;
            white-space: nowrap;
        }
        .meine-aufgaben .aufgabe-faellig.ueberfaellig { color: #dc3545; font-weight: 600; }
        .aufgaben-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .aufgaben-section h2 {
            font-size: 1.1rem;
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .aufgaben-table {
            width: 100%;
            border-collapse: collapse;
        }
        .aufgaben-table th,
        .aufgaben-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }
        .aufgaben-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-light);
            font-weight: 600;
        }
        .aufgaben-table tr:hover { background: #fafafa; }
        .status-select {
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.75rem;
            background: var(--white);
        }
        .status-offen { background: #fff3cd; }
        .status-in_arbeit { background: #cce5ff; }
        .status-erledigt { background: #d4edda; }
        .aufgabe-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto auto;
            gap: 0.75rem;
            align-items: end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        .aufgabe-form input,
        .aufgabe-form select {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .aufgabe-form label {
            font-size: 0.75rem;
            color: var(--text-light);
            display: block;
            margin-bottom: 0.25rem;
        }
        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: var(--border);
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-icon:hover { background: #ccc; }
        .btn-icon.btn-danger { background: var(--error-bg); color: var(--error); }
        .btn-icon.btn-danger:hover { background: var(--error); color: white; }
        .trello-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #0079bf;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.875rem;
        }
        .trello-link:hover { background: #026aa7; }
        @media (max-width: 900px) {
            .aufgabe-form {
                grid-template-columns: 1fr;
            }
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
                <li class="nav-item active">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
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
                    <a href="benutzer.php">Benutzerverwaltung</a>
                    
                </li>
                <li class="nav-item">
                    <a href="einstellungen.php">Einstellungen</a>
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
                <h1>Dashboard</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if (!empty($meineAufgaben)): ?>
            <div class="meine-aufgaben">
                <h3>📋 Meine offenen Aufgaben (<?= count($meineAufgaben) ?>)</h3>
                <ul>
                    <?php foreach ($meineAufgaben as $ma): ?>
                    <li>
                        <span class="aufgabe-titel"><?= htmlspecialchars($ma['titel']) ?></span>
                        <?php if ($ma['faellig_am']): ?>
                            <?php
                            $faelligDate = strtotime($ma['faellig_am']);
                            $heute = strtotime('today');
                            $ueberfaellig = $faelligDate < $heute;
                            ?>
                            <span class="aufgabe-faellig <?= $ueberfaellig ? 'ueberfaellig' : '' ?>">
                                <?= $ueberfaellig ? '⚠️ ' : '' ?>Fällig: <?= date('d.m.Y', $faelligDate) ?>
                            </span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <section class="dashboard-grid">
                <article class="card">
                    <h3>Helfer-Anmeldungen</h3>
                    <p class="card-stat"><?= $helferCount ?></p>
                    <p class="card-label">Anmeldungen eingegangen<?= $helferNeuCount > 0 ? " ({$helferNeuCount} neu)" : '' ?></p>
                    <a href="helfer.php" class="btn btn-small btn-primary" style="margin-top:0.5rem">Zur Übersicht</a>
                </article>

                <article class="card">
                    <h3>Sponsoren</h3>
                    <p class="card-stat"><?= $sponsorCount ?></p>
                    <p class="card-label">Sponsoren erfasst<?= $sponsorSumme > 0 ? ' (' . number_format($sponsorSumme, 0, ',', '.') . ' € zugesagt)' : '' ?></p>
                    <a href="sponsoren.php" class="btn btn-small btn-primary" style="margin-top:0.5rem">Zur Übersicht</a>
                </article>

                <article class="card">
                    <h3>Schnellzugriff</h3>
                    <ul class="quick-links">
                        <li><a href="../helfer-anmeldung.php" target="_blank">Helfer-Formular (öffentlich)</a></li>
                        <li><a href="https://www.raceresult.com/de-de/account/index" target="_blank" rel="noopener">Race Result Account</a></li>
                    </ul>
                </article>

                <?php if ($isAdmin): ?>
                <article class="card card-admin">
                    <h3>Admin-Bereich</h3>
                    <ul class="quick-links">
                        <li><a href="benutzer.php">Benutzer verwalten</a></li>
                        <li><a href="einstellungen.php">Systemeinstellungen</a></li>
                    </ul>
                </article>
                <?php endif; ?>
            </section>

            <div class="aufgaben-section">
                <h2>
                    Orga-Aufgaben
                    <?php if ($trelloBoardUrl): ?>
                    <a href="<?= htmlspecialchars($trelloBoardUrl) ?>" target="_blank" rel="noopener" class="trello-link">
                        📋 Zum Trello-Board
                    </a>
                    <?php endif; ?>
                </h2>

                <?php if (!empty($orgaAufgaben)): ?>
                <div style="overflow-x:auto">
                    <table class="aufgaben-table">
                        <thead>
                            <tr>
                                <th>Titel</th>
                                <th>Verantwortlich</th>
                                <th>Fällig</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orgaAufgaben as $aufgabe): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($aufgabe['titel']) ?>
                                    <?php if ($aufgabe['notiz']): ?>
                                        <br><small style="color:var(--text-light)"><?= htmlspecialchars(mb_substr($aufgabe['notiz'], 0, 60)) ?><?= mb_strlen($aufgabe['notiz']) > 60 ? '…' : '' ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $aufgabe['verantwortlich_name'] ? htmlspecialchars($aufgabe['verantwortlich_name']) : '–' ?></td>
                                <td>
                                    <?php if ($aufgabe['faellig_am']): ?>
                                        <?php
                                        $faelligDate = strtotime($aufgabe['faellig_am']);
                                        $heute = strtotime('today');
                                        $ueberfaellig = $faelligDate < $heute && $aufgabe['status'] !== 'erledigt';
                                        ?>
                                        <span style="<?= $ueberfaellig ? 'color:var(--error);font-weight:600' : '' ?>">
                                            <?= date('d.m.Y', $faelligDate) ?>
                                        </span>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="api/aufgabe_orga_crud.php" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="aufgabe_id" value="<?= $aufgabe['id'] ?>">
                                        <select name="status" class="status-select status-<?= $aufgabe['status'] ?>" onchange="this.form.submit()">
                                            <option value="offen" <?= $aufgabe['status'] === 'offen' ? 'selected' : '' ?>>Offen</option>
                                            <option value="in_arbeit" <?= $aufgabe['status'] === 'in_arbeit' ? 'selected' : '' ?>>In Arbeit</option>
                                            <option value="erledigt" <?= $aufgabe['status'] === 'erledigt' ? 'selected' : '' ?>>Erledigt</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" action="api/aufgabe_orga_crud.php" style="display:inline" onsubmit="return confirm('Aufgabe löschen?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="aufgabe_id" value="<?= $aufgabe['id'] ?>">
                                        <button type="submit" class="btn-icon btn-danger" title="Löschen">✕</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color:var(--text-light);font-size:0.875rem">Keine Aufgaben vorhanden.</p>
                <?php endif; ?>

                <form method="post" action="api/aufgabe_orga_crud.php" class="aufgabe-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label for="new_titel">Neue Aufgabe</label>
                        <input type="text" id="new_titel" name="titel" required placeholder="Titel">
                    </div>
                    <div>
                        <label for="new_verantwortlich">Verantwortlich</label>
                        <select id="new_verantwortlich" name="verantwortlich_user_id">
                            <option value="">– Niemand –</option>
                            <?php foreach ($orgaUsers as $ou): ?>
                            <option value="<?= $ou['id'] ?>"><?= htmlspecialchars($ou['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="new_faellig">Fällig am</label>
                        <input type="date" id="new_faellig" name="faellig_am">
                    </div>
                    <div style="align-self:end">
                        <button type="submit" class="btn btn-primary">Hinzufügen</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
