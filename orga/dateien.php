<?php
/**
 * Dateien — EIN Datei-Baum über das geteilte Google-Laufwerk (Paket 7).
 * Ordner klappen inline auf (mit Dateien darin), ganze Zeile klickbar, Aktionen an
 * jeder Zeile, oben eine Leiste für den gewählten Ordner. Alles per JS/fetch, ohne
 * Neuladen (der Baum bleibt offen). Zwei Wurzeln über die Tabs (Orga/Helfer).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/google_drive.php';

$user      = getCurrentUserFromGuard();
$csrfToken = generateCsrfToken();
$tab       = ($_GET['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$pdo       = getDbConnection();

// Ordner als Plakate-/Bilder-Ordner festlegen (POST, per fetch aufgerufen).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '') && driveConfigured()) {
    $target = trim((string) ($_POST['folder'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');
    if ($target !== '' && in_array($action, ['set_plakat', 'set_bilder'], true)) {
        $set = $pdo->prepare('INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2');
        if ($action === 'set_plakat') {
            $jahr = driveRennJahr($pdo);
            $set->execute(['k' => 'plakat_folder_' . $jahr, 'v' => $target, 'v2' => $target]);
        } else {
            $set->execute(['k' => 'bilder_folder_id', 'v' => $target, 'v2' => $target]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$configured = driveConfigured();
$rootId = $rootName = '';
$rennJahr = (int) date('Y');
$plakatFolder = $bilderFolder = '';
if ($configured) {
    $rootId   = driveRootFolderId($pdo, $tab);
    $rootMeta = driveFileMeta($rootId);
    $rootName = (string) ($rootMeta['name'] ?? driveBereichName($tab));
    $rennJahr = driveRennJahr($pdo);
    $plakatFolder = (string) (drivePlakatFolderId($pdo, $rennJahr) ?? '');
    $bilderFolder = (string) (driveBilderFolderId($pdo) ?? '');
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
        .tabs { display:flex; gap:0; margin-bottom:1.25rem; border-bottom:2px solid var(--border); }
        .tab { padding:0.75rem 1.5rem; color:var(--text-light); border-bottom:2px solid transparent; margin-bottom:-2px; text-decoration:none; }
        .tab:hover { color:var(--text); }
        .tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:500; }
        .fb-toolbar { background:var(--white); border:1px solid var(--border); border-radius:8px; padding:0.75rem 1rem; box-shadow:var(--shadow-card); margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center; }
        .fb-toolbar .sel { font-size:0.9rem; margin-right:auto; }
        .fb-toolbar .sel b { color:var(--primary-dark); }
        .fb-toolbar input[type=text], .fb-toolbar input[type=file] { padding:0.35rem 0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.85rem; }
        .fb-toolbar .designated { font-size:0.72rem; color:var(--primary-dark); }
        .ftree { background:var(--white); border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.75rem; box-shadow:var(--shadow-card); list-style:none; margin:0; }
        .ftree ul { list-style:none; margin:0; padding-left:1.25rem; }
        .tnode { display:flex; align-items:center; gap:0.45rem; padding:0.32rem 0.45rem; border-radius:6px; cursor:pointer; }
        .tnode:hover { background:#f2f8f4; }
        .tnode.selected { background:rgba(0,150,64,0.12); }
        .tnode-file { cursor:default; }
        .ttoggle { width:1.15rem; text-align:center; color:var(--text-light); flex:0 0 auto; user-select:none; }
        .tname { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tmeta { color:var(--text-light); font-size:0.78rem; flex:0 0 auto; }
        .tactions { display:flex; gap:0.3rem; flex:0 0 auto; opacity:0; transition:opacity .1s; }
        .tnode:hover .tactions { opacity:1; }
        .tbtn { font-size:0.72rem; padding:0.15rem 0.55rem; border:1px solid var(--border); border-radius:4px; background:var(--white); cursor:pointer; text-decoration:none; color:var(--text); white-space:nowrap; }
        .tbtn:hover { border-color:var(--primary); color:var(--primary); }
        .tbtn.danger:hover { border-color:#dc2626; color:#dc2626; }
        .tnode.drag-over { outline:2px solid var(--primary); outline-offset:-2px; }
        .fb-hint { font-size:0.75rem; color:var(--text-light); margin-top:0.75rem; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light); }
        #fb-toast { position:fixed; bottom:1.25rem; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:0.6rem 1.1rem; border-radius:6px; font-size:0.85rem; opacity:0; transition:opacity .2s; pointer-events:none; z-index:50; }
        #fb-toast.show { opacity:1; }
    </style>
</head>
<body>
<?php $activeNav = 'dateien'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header"><h1>Dateien</h1></header>

            <?php if (!$configured): ?>
                <div class="empty-state"><div style="font-size:3rem">📁</div>
                    <p>Google Drive ist noch nicht konfiguriert. Sobald die Zugangsdaten in der Server-Konfiguration hinterlegt sind, erscheint hier der Datei-Baum.</p>
                </div>
            <?php else: ?>

            <div class="tabs">
                <a href="?tab=orga" class="tab <?= $tab === 'orga' ? 'active' : '' ?>">Orga</a>
                <a href="?tab=helfer" class="tab <?= $tab === 'helfer' ? 'active' : '' ?>">Helfer</a>
            </div>

            <div class="fb-toolbar">
                <span class="sel">Aktueller Ordner: <b id="sel-name"><?= htmlspecialchars($rootName) ?></b></span>
                <input type="text" id="nf-name" placeholder="Neuer Ordner" maxlength="120">
                <button type="button" class="btn btn-secondary btn-small" id="nf-btn">＋ Ordner</button>
                <input type="file" id="up-file" accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg">
                <button type="button" class="btn btn-primary btn-small" id="up-btn">Hochladen</button>
                <button type="button" class="btn btn-secondary btn-small" id="set-plakat" title="Diesen Ordner als Plakate-Quelle der Sponsor-Mail für <?= $rennJahr ?> festlegen">📌 Plakate-Ordner (<?= $rennJahr ?>)</button>
                <button type="button" class="btn btn-secondary btn-small" id="set-bilder" title="Diesen Ordner als Bild-Quelle des Foto-Pickers festlegen">🖼️ Bilder-Ordner</button>
            </div>

            <ul class="ftree" id="fb-tree">
                <li>
                    <div class="tnode tnode-folder selected" data-fid="<?= htmlspecialchars($rootId) ?>" data-name="<?= htmlspecialchars($rootName) ?>" data-folderid="<?= htmlspecialchars($rootId) ?>" data-loaded="0" draggable="false">
                        <span class="ttoggle">▾</span>
                        <span class="tname">📁 <?= htmlspecialchars($rootName) ?></span>
                    </div>
                    <ul class="tchildren"></ul>
                </li>
            </ul>

            <p class="fb-hint">Ein Baum, live aus dem geteilten Laufwerk „Marktlauf Orga". Ordnerzeile anklicken = auswählen + auf-/zuklappen. Ziehen: eine Zeile auf einen Ordner. Aktionen erscheinen beim Überfahren der Zeile.</p>

            <?php endif; ?>
        </main>
    </div>
    <div id="fb-toast"></div>

    <?php if ($configured): ?>
    <script>
    (function () {
        const CSRF = <?= json_encode($csrfToken) ?>;
        const TAB = <?= json_encode($tab) ?>;
        const ROOT = <?= json_encode($rootId) ?>;
        const RENNJAHR = <?= json_encode($rennJahr) ?>;
        let plakatFolder = <?= json_encode($plakatFolder) ?>;
        let bilderFolder = <?= json_encode($bilderFolder) ?>;

        const tree = document.getElementById('fb-tree');
        const selName = document.getElementById('sel-name');
        const toast = document.getElementById('fb-toast');
        let selectedFolder = ROOT;
        let selectedName = <?= json_encode($rootName) ?>;
        let draggedFid = null;

        function showToast(msg) { toast.textContent = msg; toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 2200); }
        function fileIcon(mime) {
            if (mime.indexOf('pdf') > -1) return '📄';
            if (mime.indexOf('word') > -1) return '📝';
            if (mime.indexOf('sheet') > -1) return '📊';
            if (mime.indexOf('image') > -1) return '🖼️';
            return '📄';
        }
        function fmtSize(b) {
            if (b >= 1048576) return (b / 1048576).toFixed(1).replace('.', ',') + ' MB';
            if (b >= 1024) return Math.round(b / 1024) + ' KB';
            return b ? b + ' B' : '';
        }

        function makeBtn(label, cls) { const b = document.createElement('button'); b.type = 'button'; b.className = 'tbtn' + (cls ? ' ' + cls : ''); b.textContent = label; return b; }

        function buildRow(item) {
            const li = document.createElement('li');
            const node = document.createElement('div');
            node.dataset.fid = item.id;
            node.dataset.name = item.name;
            node.draggable = true;
            const actions = document.createElement('span');
            actions.className = 'tactions';

            if (item.isFolder) {
                node.className = 'tnode tnode-folder drop-target';
                node.dataset.folderid = item.id;
                node.dataset.loaded = '0';
                const tog = document.createElement('span'); tog.className = 'ttoggle'; tog.textContent = '▸';
                const name = document.createElement('span'); name.className = 'tname'; name.textContent = '📁 ' + item.name;
                node.appendChild(tog); node.appendChild(name);
                const bRen = makeBtn('Umbenennen'); bRen.dataset.act = 'rename';
                const bDel = makeBtn('Löschen', 'danger'); bDel.dataset.act = 'delete';
                actions.appendChild(bRen); actions.appendChild(bDel);
                node.appendChild(actions);
                li.appendChild(node);
                const sub = document.createElement('ul'); sub.className = 'tchildren'; sub.hidden = true; li.appendChild(sub);
            } else {
                node.className = 'tnode tnode-file';
                const sp = document.createElement('span'); sp.className = 'ttoggle'; sp.textContent = '';
                const name = document.createElement('span'); name.className = 'tname'; name.textContent = fileIcon(item.mimeType || '') + ' ' + item.name;
                const meta = document.createElement('span'); meta.className = 'tmeta'; meta.textContent = fmtSize(item.size || 0);
                node.appendChild(sp); node.appendChild(name); node.appendChild(meta);
                const dl = document.createElement('a'); dl.className = 'tbtn'; dl.textContent = 'Download'; dl.href = 'api/file_download.php?fid=' + encodeURIComponent(item.id);
                const bRen = makeBtn('Umbenennen'); bRen.dataset.act = 'rename';
                const bDel = makeBtn('Löschen', 'danger'); bDel.dataset.act = 'delete';
                actions.appendChild(dl); actions.appendChild(bRen); actions.appendChild(bDel);
                node.appendChild(actions);
                li.appendChild(node);
            }
            return li;
        }

        function childUlOf(node) { return node.closest('li').querySelector(':scope > ul.tchildren'); }

        function loadChildren(node) {
            const ul = childUlOf(node);
            return fetch('api/folder_children.php?parent=' + encodeURIComponent(node.dataset.fid))
                .then(r => r.json())
                .then(d => {
                    ul.innerHTML = '';
                    (d.items || []).forEach(it => ul.appendChild(buildRow(it)));
                    node.dataset.loaded = '1';
                    ul.hidden = false;
                    const tog = node.querySelector('.ttoggle'); if (tog) tog.textContent = '▾';
                });
        }
        function refreshFolder(fid) {
            const node = tree.querySelector('.tnode-folder[data-fid="' + (window.CSS && CSS.escape ? CSS.escape(fid) : fid) + '"]');
            if (node && node.dataset.loaded === '1') { loadChildren(node); }
        }
        function toggleFolder(node) {
            const ul = childUlOf(node);
            const tog = node.querySelector('.ttoggle');
            if (node.dataset.loaded === '0') { loadChildren(node); return; }
            ul.hidden = !ul.hidden;
            if (tog) tog.textContent = ul.hidden ? '▸' : '▾';
        }
        function selectFolder(node) {
            tree.querySelectorAll('.tnode.selected').forEach(n => n.classList.remove('selected'));
            node.classList.add('selected');
            selectedFolder = node.dataset.fid;
            selectedName = node.dataset.name;
            selName.textContent = selectedName;
        }

        // Klicks im Baum (delegiert)
        tree.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-act]');
            if (btn) {
                const node = btn.closest('.tnode');
                if (btn.dataset.act === 'rename') { doRename(node); }
                else if (btn.dataset.act === 'delete') { doDelete(node); }
                return;
            }
            if (e.target.closest('a.tbtn')) return; // Download-Link
            const folderNode = e.target.closest('.tnode-folder');
            if (folderNode) { selectFolder(folderNode); toggleFolder(folderNode); }
        });

        function doRename(node) {
            const cur = node.dataset.name;
            const name = prompt('Neuer Name:', cur);
            if (name === null) return;
            const t = name.trim();
            if (t === '' || t === cur) return;
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, fid: node.dataset.fid, name: t });
            fetch('api/file_rename.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => {
                    node.dataset.name = t;
                    const nm = node.querySelector('.tname');
                    nm.textContent = nm.textContent.slice(0, 2) + t; // Icon + neuer Name
                    if (node.dataset.fid === selectedFolder) { selectedName = t; selName.textContent = t; }
                    showToast('Umbenannt.');
                });
        }
        function doDelete(node) {
            const isFolder = node.classList.contains('tnode-folder');
            if (!confirm((isFolder ? 'Ordner samt Inhalt' : 'Datei') + ' „' + node.dataset.name + '“ in den Papierkorb?')) return;
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, fid: node.dataset.fid });
            fetch('api/file_delete.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => { node.closest('li').remove(); showToast('In den Papierkorb verschoben.'); });
        }

        // Toolbar
        document.getElementById('nf-btn').addEventListener('click', function () {
            const inp = document.getElementById('nf-name');
            const name = inp.value.trim();
            if (name === '') { inp.focus(); return; }
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, parent: selectedFolder, name: name });
            fetch('api/folder_create.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => { inp.value = ''; refreshFolder(selectedFolder); showToast('Ordner angelegt in „' + selectedName + '“.'); });
        });
        document.getElementById('up-btn').addEventListener('click', function () {
            const inp = document.getElementById('up-file');
            if (!inp.files || !inp.files[0]) { inp.click(); return; }
            const fd = new FormData();
            fd.append('csrf_token', CSRF); fd.append('tab', TAB); fd.append('folder', selectedFolder); fd.append('datei', inp.files[0]);
            fetch('api/file_upload.php', { method: 'POST', body: fd, redirect: 'manual' })
                .then(() => { inp.value = ''; refreshFolder(selectedFolder); showToast('Hochgeladen in „' + selectedName + '“.'); });
        });
        function designate(action, msg) {
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, action: action, folder: selectedFolder });
            fetch('dateien.php', { method: 'POST', body: body })
                .then(() => { if (action === 'set_plakat') plakatFolder = selectedFolder; else bilderFolder = selectedFolder; showToast(msg + ' „' + selectedName + '“.'); });
        }
        document.getElementById('set-plakat').addEventListener('click', () => designate('set_plakat', 'Plakate-Ordner (' + RENNJAHR + ') gesetzt:'));
        document.getElementById('set-bilder').addEventListener('click', () => designate('set_bilder', 'Bilder-Ordner gesetzt:'));

        // Drag & Drop (delegiert)
        tree.addEventListener('dragstart', function (e) {
            const node = e.target.closest('.tnode[data-fid]');
            if (node) { draggedFid = node.dataset.fid; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', node.dataset.fid); } catch (_) {} }
        });
        let lastOver = null;
        tree.addEventListener('dragover', function (e) {
            const dt = e.target.closest('.tnode-folder');
            if (!dt) return;
            e.preventDefault();
            if (lastOver && lastOver !== dt) lastOver.classList.remove('drag-over');
            dt.classList.add('drag-over'); lastOver = dt;
        });
        tree.addEventListener('dragleave', function (e) { const dt = e.target.closest('.tnode-folder'); if (dt) dt.classList.remove('drag-over'); });
        tree.addEventListener('drop', function (e) {
            const dt = e.target.closest('.tnode-folder');
            if (!dt) return;
            e.preventDefault(); dt.classList.remove('drag-over');
            const target = dt.dataset.folderid;
            const sourceLi = tree.querySelector('.tnode[data-fid="' + (window.CSS && CSS.escape ? CSS.escape(draggedFid) : draggedFid) + '"]');
            const sourceNode = sourceLi;
            const source = sourceNode ? (sourceNode.closest('ul.tchildren') ? sourceNode.closest('ul.tchildren').closest('li').querySelector(':scope > .tnode').dataset.fid : ROOT) : ROOT;
            if (!draggedFid || !target || draggedFid === target) { draggedFid = null; return; }
            const body = new URLSearchParams({ csrf_token: CSRF, tab: TAB, fid: draggedFid, target: target, source: source });
            const moved = draggedFid; draggedFid = null;
            fetch('api/file_move.php', { method: 'POST', body: body })
                .then(r => r.json())
                .then(d => {
                    if (!d.ok) { alert(d.message || 'Verschieben fehlgeschlagen.'); return; }
                    const li = tree.querySelector('.tnode[data-fid="' + (window.CSS && CSS.escape ? CSS.escape(moved) : moved) + '"]');
                    if (li) li.closest('li').remove();
                    refreshFolder(target);
                    showToast('Verschoben.');
                })
                .catch(() => alert('Verschieben fehlgeschlagen.'));
        });

        // Start: Wurzel aufklappen
        const rootNode = tree.querySelector('.tnode-folder');
        if (rootNode) { loadChildren(rootNode); }
    })();
    </script>
    <?php endif; ?>

    <script>
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
