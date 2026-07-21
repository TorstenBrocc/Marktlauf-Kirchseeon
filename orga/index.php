<?php
/**
 * Orga Dashboard Startseite
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$config = getConfig();

// Dashboard-Kacheln aus der Modul-Registry (Single Source mit der Sidebar,
// siehe orga/_nav.php). Je Modul optional eine KPI-Closure; jede läuft in
// try/catch, damit eine (noch) fehlende Tabelle nur die Kennzahl weglässt
// statt das ganze Dashboard mit einem Fehler abzuschießen.
$navItems = require __DIR__ . '/_nav.php';
// Kacheln nach Sidebar-Abschnitt gruppieren (gleiche Reihenfolge wie in _nav.php),
// damit Dashboard und Sidebar dieselbe Struktur zeigen.
$dashboardGroups = [];
foreach ($navItems as $item) {
    if (($item['tile'] ?? true) === false || !empty($item['admin'])) {
        continue;
    }
    $kpi = null;
    if (isset($item['kpi']) && is_callable($item['kpi'])) {
        try {
            $kpi = ($item['kpi'])($pdo);
        } catch (PDOException $e) {
            logError('Dashboard-KPI (' . ($item['key'] ?? '?') . '): ' . $e->getMessage());
        }
    }
    $section = $item['section'] ?? '';
    $dashboardGroups[$section][] = ['item' => $item, 'kpi' => $kpi];
}

/**
 * Eine einzelne Dashboard-Kachel rendern (deaktiviertes Modul oder Absprung/KPI-Link).
 */
$renderTile = static function (array $tile): void {
    $item = $tile['item'];
    $kpi  = $tile['kpi'];
    if (empty($item['href'])) { // deaktiviertes Modul (z. B. Live-Ticker)
        echo '<div class="card card-tile card-tile-disabled">'
            . '<h3>' . htmlspecialchars($item['label'])
            . (isset($item['badge']) ? ' <span class="badge">' . htmlspecialchars($item['badge']) . '</span>' : '')
            . '</h3><p class="card-label">Noch nicht verfügbar</p></div>';
        return;
    }
    echo '<a class="card card-tile signal-' . htmlspecialchars($kpi['signal'] ?? 'neutral') . '" href="' . htmlspecialchars($item['href']) . '">'
        . '<h3>' . htmlspecialchars($item['label']) . '</h3>';
    if ($kpi !== null) {
        echo '<p class="card-stat">' . htmlspecialchars($kpi['value']) . '</p>'
            . '<p class="card-label">' . htmlspecialchars($kpi['label']) . '</p>';
    } else {
        echo '<p class="card-tile-open">Öffnen →</p>';
    }
    echo '</a>';
};

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

$onedriveUrl = '';
try {
    $onedriveStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $onedriveStmt->execute(['key' => 'onedrive_url']);
    $onedriveUrl = $onedriveStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table may not exist yet
}

$stravaUrl = '';
try {
    $stravaStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $stravaStmt->execute(['key' => 'strava_url']);
    $stravaUrl = $stravaStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table may not exist yet
}

// Zugangsdaten-Hinweise je Button — NUR für Admins, Klartext bleibt DB-intern.
$linkHinweise = [];
if ($isAdmin) {
    try {
        $hinweisStmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('raceresult_hinweis','trello_hinweis','onedrive_hinweis','strava_hinweis')");
        foreach ($hinweisStmt as $row) {
            $linkHinweise[$row['key']] = $row['value'];
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

/**
 * Render-Helfer: ⓘ-Button + aufklappbare, kopierbare Notiz für einen Schnellzugriff-Link.
 * Gibt leeren String zurück, wenn kein Admin oder kein Hinweis hinterlegt ist.
 */
$renderHinweis = function (string $key) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id = 'hint-' . $key;
    $rows = min(6, max(2, substr_count($text, "\n") + 1));
    return '<button type="button" class="qc-info" aria-expanded="false" aria-controls="' . $id . '" onclick="toggleHint(this)" title="' . htmlspecialchars($text) . '">&#9432;</button>'
        . '<div class="qc-note" id="' . $id . '" hidden>'
        . '<textarea class="qc-note-text" readonly rows="' . $rows . '" onclick="this.select()">' . htmlspecialchars($text) . '</textarea>'
        . '<div class="qc-note-actions">'
        . '<button type="button" class="qc-copy" onclick="copyHint(this)">Kopieren</button>'
        . '<a class="qc-edit" href="einstellungen.php#link-' . htmlspecialchars($key) . '">Bearbeiten &rarr;</a>'
        . '</div></div>';
};

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
    <title>Cockpit | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
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
        @media (max-width: 900px) {
            .aufgabe-form {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-group {
            margin-bottom: 1.75rem;
        }
        .dashboard-group-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-light);
            font-weight: 700;
            margin: 0 0 0.75rem 0;
        }
    </style>
</head>
<body>
<?php $activeNav = 'dashboard'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Cockpit</h1>
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

            <?php foreach ($dashboardGroups as $section => $tiles): ?>
                <?php if ($section === 'ADMIN' || $section === '') { continue; } // ADMIN nicht aufs Dashboard; '' unten mit Schnellzugriff ?>
                <section class="dashboard-group">
                    <h2 class="dashboard-group-title"><?= htmlspecialchars($section) ?></h2>
                    <div class="dashboard-grid">
                        <?php foreach ($tiles as $tile) { $renderTile($tile); } ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="dashboard-group">
                <div class="dashboard-grid">
                    <?php
                    // Kacheln ohne eigenen Abschnitt sowie die ADMIN-Kachel (nur CI &
                    // Design — für alle sichtbar, aber ohne ADMIN-Überschrift hier) plus
                    // die Schnellzugriff-Karte als „Absprung"-Bereich am Ende.
                    foreach (($dashboardGroups[''] ?? []) as $tile) { $renderTile($tile); }
                    foreach (($dashboardGroups['ADMIN'] ?? []) as $tile) { $renderTile($tile); }
                    ?>

                <article class="card">
                    <h3>Schnellzugriff</h3>
                    <ul class="quick-links">
                        <li><a href="../helfer-anmeldung.php" target="_blank">Helfer-Formular (öffentlich)</a></li>
                        <li><a href="https://www.raceresult.com/de-de/account/index" target="_blank" rel="noopener" class="btn-brand btn-brand-raceresult">Race Result</a><?= $renderHinweis('raceresult_hinweis') ?></li>
                        <li><a href="https://github.com/TorstenBrocc/Marktlauf-Kirchseeon" target="_blank" rel="noopener" class="btn-brand btn-brand-github">GitHub-Repo (Website)</a></li>
                        <?php if ($trelloBoardUrl): ?>
                        <li><a href="<?= htmlspecialchars($trelloBoardUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-trello">Trello-Board</a><?= $renderHinweis('trello_hinweis') ?></li>
                        <?php endif; ?>
                        <?php if ($onedriveUrl): ?>
                        <li><a href="<?= htmlspecialchars($onedriveUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-onedrive">OneDrive</a><?= $renderHinweis('onedrive_hinweis') ?></li>
                        <?php endif; ?>
                        <?php if ($stravaUrl): ?>
                        <li><a href="<?= htmlspecialchars($stravaUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-strava">Strava</a><?= $renderHinweis('strava_hinweis') ?></li>
                        <?php endif; ?>
                    </ul>
                </article>
                </div>
            </section>

            <div class="aufgaben-section">
                <h2>Orga-Aufgaben</h2>

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
    <script>
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.nav-item a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    })();

    // Zugangsdaten-Hinweis je Schnellzugriff-Button: aufklappen + kopieren
    function toggleHint(btn) {
        var note = document.getElementById(btn.getAttribute('aria-controls'));
        if (!note) return;
        var willOpen = note.hasAttribute('hidden');
        if (willOpen) { note.removeAttribute('hidden'); } else { note.setAttribute('hidden', ''); }
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }
    function copyHint(btn) {
        var ta = btn.closest('.qc-note').querySelector('.qc-note-text');
        if (!ta) return;
        ta.select();
        var done = function () {
            var label = btn.textContent;
            btn.textContent = 'Kopiert ✓';
            setTimeout(function () { btn.textContent = label; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(ta.value).then(done).catch(function () { document.execCommand('copy'); done(); });
        } else {
            document.execCommand('copy');
            done();
        }
    }
    </script>
</body>
</html>
