<?php
/**
 * Dateien — Ordner-Browser über das geteilte Google-Laufwerk (Paket 7).
 * Spiegelt die echte Drive-Ordnerstruktur (navigieren, Breadcrumb, Ordner anlegen,
 * Upload in den offenen Ordner, Download/Löschen). Zwei Wurzeln über die Tabs
 * (Orga/Helfer). Keine festen Kategorien, kein Jahr, kein DB-Index.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/google_drive.php';

$user      = getCurrentUserFromGuard();
$isAdmin   = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$tab = ($_GET['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$pdo = getDbConnection();

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function getFileIcon(string $mime): string
{
    return match (true) {
        str_contains($mime, 'pdf')   => '📄',
        str_contains($mime, 'word')  => '📝',
        str_contains($mime, 'sheet') => '📊',
        str_contains($mime, 'image') => '🖼️',
        default                      => '📄',
    };
}

// --- Drive nicht konfiguriert: schlichter Hinweis -----------------------------
if (!driveConfigured()) {
    $notice = true;
} else {
    $notice = false;
    $rootId = driveRootFolderId($pdo, $tab);

    // PRG: aktuellen Ordner als Plakate-/Bilder-Ordner festlegen.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $target = trim((string) ($_POST['folder'] ?? ''));
        $action = (string) ($_POST['action'] ?? '');
        if ($target !== '' && in_array($action, ['set_plakat', 'set_bilder'], true)) {
            $set = $pdo->prepare('INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2');
            if ($action === 'set_plakat') {
                $jahr = driveRennJahr($pdo);
                $set->execute(['k' => 'plakat_folder_' . $jahr, 'v' => $target, 'v2' => $target]);
                $_SESSION['flash_success'] = 'Als Plakate-Ordner für ' . $jahr . ' festgelegt.';
            } else {
                $set->execute(['k' => 'bilder_folder_id', 'v' => $target, 'v2' => $target]);
                $_SESSION['flash_success'] = 'Als Bilder-Ordner festgelegt.';
            }
        }
        header('Location: dateien.php?tab=' . $tab . '&folder=' . urlencode($target));
        exit;
    }

    // Aktuellen Ordner bestimmen + gegen das geteilte Laufwerk absichern.
    $folder = trim((string) ($_GET['folder'] ?? ''));
    if ($folder === '') {
        $folder = $rootId;
    }
    $curMeta = driveFileMeta($folder);
    if ($curMeta === null
        || (string) ($curMeta['driveId'] ?? '') !== driveSharedDriveId()
        || (string) ($curMeta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
        $folder  = $rootId;
        $curMeta = driveFileMeta($folder);
    }

    $breadcrumb = driveBreadcrumb($folder, $rootId);
    $ordner     = [];
    $dateien    = [];
    try {
        foreach (driveListChildren($folder) as $c) {
            if ($c['isFolder']) {
                $ordner[] = $c;
            } else {
                $dateien[] = $c;
            }
        }
    } catch (Throwable $e) {
        logError('dateien.php list: ' . $e->getMessage());
        $flashError = $flashError ?: 'Ordnerinhalt konnte nicht geladen werden.';
    }

    $rennJahr     = driveRennJahr($pdo);
    $plakatFolder = drivePlakatFolderId($pdo, $rennJahr);
    $bilderFolder = driveBilderFolderId($pdo);

    // Ordnerbaum-Wurzel + erstes Level (tiefere Ebenen lädt der Baum bei Bedarf nach).
    $treeRootName = $breadcrumb[0]['name'] ?? driveBereichName($tab);
    $treeTop = [];
    try {
        foreach (driveListChildren($rootId) as $c) {
            if ($c['isFolder']) {
                $treeTop[] = $c;
            }
        }
    } catch (Throwable $e) {
        $treeTop = [];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dateien | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .tabs { display:flex; gap:0; margin-bottom:1.5rem; border-bottom:2px solid var(--border); }
        .tab { padding:0.75rem 1.5rem; color:var(--text-light); border-bottom:2px solid transparent; margin-bottom:-2px; text-decoration:none; }
        .tab:hover { color:var(--text); }
        .tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:500; }
        .fb-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .breadcrumb { font-size:0.95rem; }
        .breadcrumb a { color:var(--primary); text-decoration:none; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb .sep { color:var(--text-light); margin:0 0.35rem; }
        .fb-actions { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; margin-left:auto; }
        .fb-actions form { display:flex; gap:0.35rem; align-items:center; }
        .fb-actions input[type="text"], .fb-actions input[type="file"] { padding:0.35rem 0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.85rem; }
        .data-table { width:100%; border-collapse:collapse; background:var(--white); border-radius:8px; overflow:hidden; box-shadow:var(--shadow-card); }
        .data-table th, .data-table td { padding:0.65rem 0.75rem; text-align:left; border-bottom:1px solid var(--border); font-size:0.9rem; }
        .data-table th { background:var(--bg); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-light); }
        .data-table tr:hover { background:#fafafa; }
        .row-icon { font-size:1.2rem; margin-right:0.5rem; }
        .row-folder a { font-weight:600; color:var(--text); text-decoration:none; }
        .row-folder a:hover { color:var(--primary); }
        .btn-download { display:inline-block; padding:0.25rem 0.7rem; background:var(--primary); color:#fff; border-radius:4px; text-decoration:none; font-size:0.75rem; }
        .btn-download:hover { background:var(--primary-dark); }
        .inline-form { display:inline; }
        .designated { display:inline-block; padding:0.1rem 0.5rem; border-radius:999px; font-size:0.7rem; font-weight:600; background:rgba(0,150,64,0.1); color:var(--primary-dark); }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light); }
        .table-wrap { overflow-x:auto; border-radius:8px; box-shadow:var(--shadow-card); margin-top:0.5rem; }
        .fb-hint { font-size:0.75rem; color:var(--text-light); margin-top:0.75rem; }
        .btn-action { padding:0.2rem 0.55rem; border:1px solid var(--border); border-radius:4px; background:var(--white); font-size:0.72rem; cursor:pointer; }
        tr[draggable] { cursor:grab; }
        .drag-over { outline:2px solid var(--primary); outline-offset:-2px; background:rgba(0,150,64,0.08) !important; }
        a.drop-target.drag-over { background:rgba(0,150,64,0.18); border-radius:3px; outline:none; }
        .fb-layout { display:flex; gap:1.25rem; align-items:flex-start; }
        .fb-tree { flex:0 0 250px; background:var(--white); border:1px solid var(--border); border-radius:8px; padding:0.6rem; max-height:72vh; overflow:auto; font-size:0.85rem; box-shadow:var(--shadow-card); }
        .fb-tree ul { list-style:none; margin:0; padding-left:0.9rem; }
        .fb-tree > ul { padding-left:0; }
        .fb-main { flex:1; min-width:0; }
        .tree-node { display:flex; align-items:center; gap:0.25rem; padding:0.15rem 0.3rem; border-radius:4px; white-space:nowrap; }
        .tree-node a { color:var(--text); text-decoration:none; overflow:hidden; text-overflow:ellipsis; }
        .tree-node a:hover { color:var(--primary); }
        .tree-node.current { background:rgba(0,150,64,0.1); font-weight:600; }
        .tree-toggle { cursor:pointer; width:1rem; text-align:center; color:var(--text-light); user-select:none; }
        .tree-toggle.empty { visibility:hidden; }
        @media (max-width:820px) { .fb-layout { flex-wrap:wrap; } .fb-tree { flex-basis:100%; max-height:none; } }
    </style>
</head>
<body>
<?php $activeNav = 'dateien'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Dateien</h1>
            </header>

            <?php if ($flashSuccess): ?><div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
            <?php if ($flashError): ?><div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

            <?php if ($notice): ?>
                <div class="empty-state">
                    <div style="font-size:3rem">📁</div>
                    <p>Google Drive ist noch nicht konfiguriert. Sobald die Zugangsdaten in der
                    Server-Konfiguration hinterlegt sind, erscheint hier der Ordner-Browser.</p>
                </div>
            <?php else: ?>

            <div class="tabs">
                <a href="?tab=orga" class="tab <?= $tab === 'orga' ? 'active' : '' ?>">Orga</a>
                <a href="?tab=helfer" class="tab <?= $tab === 'helfer' ? 'active' : '' ?>">Helfer</a>
            </div>

            <div class="fb-layout">
                <aside class="fb-tree" id="fb-tree" aria-label="Ordnerbaum">
                    <ul>
                        <li>
                            <div class="tree-node drop-target<?= $folder === $rootId ? ' current' : '' ?>" data-folderid="<?= htmlspecialchars($rootId) ?>">
                                <span class="tree-toggle" data-loaded="1">▾</span>
                                <a href="?tab=<?= $tab ?>&folder=<?= urlencode($rootId) ?>"><?= htmlspecialchars($treeRootName) ?></a>
                            </div>
                            <ul class="tree-children">
                                <?php foreach ($treeTop as $t): ?>
                                <li>
                                    <div class="tree-node drop-target<?= $folder === $t['id'] ? ' current' : '' ?>" data-folderid="<?= htmlspecialchars($t['id']) ?>">
                                        <span class="tree-toggle" data-loaded="0" data-fid="<?= htmlspecialchars($t['id']) ?>">▸</span>
                                        <a href="?tab=<?= $tab ?>&folder=<?= urlencode($t['id']) ?>"><?= htmlspecialchars($t['name']) ?></a>
                                    </div>
                                    <ul class="tree-children" hidden></ul>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    </ul>
                </aside>
                <div class="fb-main">

            <div class="fb-bar">
                <nav class="breadcrumb" aria-label="Pfad">
                    <?php foreach ($breadcrumb as $i => $bc): ?>
                        <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
                        <?php if ($bc['id'] === $folder): ?>
                            <strong><?= htmlspecialchars($bc['name']) ?></strong>
                        <?php else: ?>
                            <a class="drop-target" data-folderid="<?= htmlspecialchars($bc['id']) ?>" href="?tab=<?= $tab ?>&folder=<?= urlencode($bc['id']) ?>"><?= htmlspecialchars($bc['name']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <div class="fb-actions">
                    <form method="post" action="api/folder_create.php" title="Neuen Unterordner anlegen">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="tab" value="<?= $tab ?>">
                        <input type="hidden" name="parent" value="<?= htmlspecialchars($folder) ?>">
                        <input type="text" name="name" placeholder="Neuer Ordner" maxlength="120" required>
                        <button type="submit" class="btn btn-secondary btn-small">＋ Ordner</button>
                    </form>
                    <form method="post" action="api/file_upload.php" enctype="multipart/form-data" title="In diesen Ordner hochladen">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="tab" value="<?= $tab ?>">
                        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                        <input type="file" name="datei" required accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg">
                        <button type="submit" class="btn btn-primary btn-small">Hochladen</button>
                    </form>
                </div>
            </div>

            <div class="fb-bar" style="margin-top:-0.25rem">
                <form method="post" action="dateien.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                    <input type="hidden" name="action" value="set_plakat">
                    <button type="submit" class="btn btn-secondary btn-small" title="Diesen Ordner als Plakate-Quelle der Sponsor-Mail für <?= $rennJahr ?> festlegen">📌 Als Plakate-Ordner (<?= $rennJahr ?>)</button>
                </form>
                <?php if ($plakatFolder === $folder): ?><span class="designated">aktueller Plakate-Ordner <?= $rennJahr ?></span><?php endif; ?>
                <form method="post" action="dateien.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                    <input type="hidden" name="action" value="set_bilder">
                    <button type="submit" class="btn btn-secondary btn-small" title="Diesen Ordner als Bild-Quelle des Foto-Pickers festlegen">🖼️ Als Bilder-Ordner</button>
                </form>
                <?php if ($bilderFolder === $folder): ?><span class="designated">aktueller Bilder-Ordner</span><?php endif; ?>
            </div>

            <?php if (empty($ordner) && empty($dateien)): ?>
                <div class="empty-state"><div style="font-size:2.5rem">📁</div><p>Dieser Ordner ist leer.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Größe</th><th>Geändert</th><th>Aktionen</th></tr></thead>
                        <tbody>
                            <?php foreach ($ordner as $o): ?>
                                <tr class="row-folder drop-target" draggable="true" data-fid="<?= htmlspecialchars($o['id']) ?>" data-name="<?= htmlspecialchars($o['name']) ?>" data-folderid="<?= htmlspecialchars($o['id']) ?>">
                                    <td><span class="row-icon">📁</span><a href="?tab=<?= $tab ?>&folder=<?= urlencode($o['id']) ?>"><?= htmlspecialchars($o['name']) ?></a></td>
                                    <td class="file-meta">—</td>
                                    <td class="file-meta"><?= $o['modifiedTime'] ? htmlspecialchars(date('d.m.Y', strtotime($o['modifiedTime']))) : '' ?></td>
                                    <td>
                                        <button type="button" class="btn-action" data-fid="<?= htmlspecialchars($o['id']) ?>" data-name="<?= htmlspecialchars($o['name']) ?>" onclick="renameItem(this)">Umbenennen</button>
                                        <form method="post" action="api/file_delete.php" class="inline-form" onsubmit="return confirm('Ordner samt Inhalt in den Papierkorb verschieben?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="tab" value="<?= $tab ?>">
                                            <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                                            <input type="hidden" name="fid" value="<?= htmlspecialchars($o['id']) ?>">
                                            <button type="submit" class="btn-action btn-danger">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php foreach ($dateien as $d): ?>
                                <tr draggable="true" data-fid="<?= htmlspecialchars($d['id']) ?>" data-name="<?= htmlspecialchars($d['name']) ?>">
                                    <td><span class="row-icon"><?= getFileIcon($d['mimeType']) ?></span><?= htmlspecialchars($d['name']) ?></td>
                                    <td class="file-meta"><?= $d['size'] > 0 ? formatFileSize($d['size']) : '' ?></td>
                                    <td class="file-meta"><?= $d['modifiedTime'] ? htmlspecialchars(date('d.m.Y', strtotime($d['modifiedTime']))) : '' ?></td>
                                    <td>
                                        <a href="api/file_download.php?fid=<?= urlencode($d['id']) ?>" class="btn-download">Download</a>
                                        <button type="button" class="btn-action" data-fid="<?= htmlspecialchars($d['id']) ?>" data-name="<?= htmlspecialchars($d['name']) ?>" onclick="renameItem(this)">Umbenennen</button>
                                        <form method="post" action="api/file_delete.php" class="inline-form" onsubmit="return confirm('Datei in den Papierkorb verschieben?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="tab" value="<?= $tab ?>">
                                            <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                                            <input type="hidden" name="fid" value="<?= htmlspecialchars($d['id']) ?>">
                                            <button type="submit" class="btn-action btn-danger">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <p class="fb-hint">Die Struktur spiegelt 1:1 das geteilte Google-Laufwerk „Marktlauf Orga". Änderungen, die du direkt in Drive machst, erscheinen hier automatisch. Ziehen: Zeile rechts auf einen Ordner im Baum links.</p>
                </div><!-- .fb-main -->
            </div><!-- .fb-layout -->

            <?php endif; ?>
        </main>
    </div>
    <script>
    (function () {
        const CSRF = <?= json_encode($csrfToken) ?>;
        const TAB = <?= json_encode($tab ?? 'orga') ?>;
        const CURFOLDER = <?= json_encode($folder ?? '') ?>;

        window.renameItem = function (btn) {
            const fid = btn.dataset.fid;
            const cur = btn.dataset.name || '';
            const name = prompt('Neuer Name:', cur);
            if (name === null) return;
            const t = name.trim();
            if (t === '' || t === cur) return;
            const f = document.createElement('form');
            f.method = 'post';
            f.action = 'api/file_rename.php';
            [['csrf_token', CSRF], ['tab', TAB], ['folder', CURFOLDER], ['fid', fid], ['name', t]].forEach(function (kv) {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = kv[0]; i.value = kv[1];
                f.appendChild(i);
            });
            document.body.appendChild(f);
            f.submit();
        };

        // Ordnerbaum: Ebenen bei Bedarf nachladen
        const tree = document.getElementById('fb-tree');
        if (tree) {
            tree.addEventListener('click', function (e) {
                const tog = e.target.closest('.tree-toggle');
                if (!tog || !tog.dataset.fid) return;
                const li = tog.closest('li');
                const childUl = li.querySelector(':scope > ul.tree-children');
                if (!childUl) return;
                if (tog.dataset.loaded === '0') {
                    tog.dataset.loaded = '1';
                    tog.textContent = '▾';
                    fetch('api/folder_children.php?parent=' + encodeURIComponent(tog.dataset.fid))
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            childUl.innerHTML = '';
                            (d.folders || []).forEach(function (f) {
                                const item = document.createElement('li');
                                const node = document.createElement('div');
                                node.className = 'tree-node drop-target';
                                node.dataset.folderid = f.id;
                                const t = document.createElement('span');
                                t.className = 'tree-toggle'; t.dataset.loaded = '0'; t.dataset.fid = f.id; t.textContent = '▸';
                                const a = document.createElement('a');
                                a.href = '?tab=' + encodeURIComponent(TAB) + '&folder=' + encodeURIComponent(f.id);
                                a.textContent = f.name;
                                node.appendChild(t); node.appendChild(a);
                                const sub = document.createElement('ul');
                                sub.className = 'tree-children'; sub.hidden = true;
                                item.appendChild(node); item.appendChild(sub);
                                childUl.appendChild(item);
                            });
                            if (!(d.folders || []).length) { tog.classList.add('empty'); }
                            childUl.hidden = false;
                        })
                        .catch(function () { tog.dataset.loaded = '0'; tog.textContent = '▸'; });
                } else {
                    childUl.hidden = !childUl.hidden;
                    tog.textContent = childUl.hidden ? '▸' : '▾';
                }
            });
        }

        // Drag & Drop (delegiert – erfasst auch nachgeladene Baum-Knoten)
        let draggedFid = null;
        document.addEventListener('dragstart', function (e) {
            const row = e.target.closest('tr[draggable]');
            if (row) { draggedFid = row.dataset.fid; e.dataTransfer.effectAllowed = 'move'; }
        });
        let lastOver = null;
        document.addEventListener('dragover', function (e) {
            const dt = e.target.closest('.drop-target');
            if (!dt) return;
            e.preventDefault();
            if (lastOver && lastOver !== dt) lastOver.classList.remove('drag-over');
            dt.classList.add('drag-over'); lastOver = dt;
        });
        document.addEventListener('dragleave', function (e) {
            const dt = e.target.closest('.drop-target');
            if (dt) dt.classList.remove('drag-over');
        });
        document.addEventListener('drop', function (e) {
            const dt = e.target.closest('.drop-target');
            if (!dt) return;
            e.preventDefault();
            dt.classList.remove('drag-over');
            const target = dt.dataset.folderid;
            if (!draggedFid || !target || draggedFid === target || target === CURFOLDER) { draggedFid = null; return; }
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, fid: draggedFid, target: target, source: CURFOLDER });
            draggedFid = null;
            fetch('api/file_move.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.ok) { location.reload(); } else { alert(d.message || 'Verschieben fehlgeschlagen.'); } })
                .catch(function () { alert('Verschieben fehlgeschlagen.'); });
        });
    })();

    (function () {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!burger) return;
        function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function () { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', close);
        sidebar.querySelectorAll('.nav-item a').forEach(function (l) { l.addEventListener('click', close); });
    })();
    </script>
</body>
</html>
