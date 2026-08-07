<?php
/**
 * Dateien — EIN Datei-Baum über das geteilte Google-Laufwerk (Paket 7 + Strang 1).
 * Orga und Helfer liegen als zwei Wurzeln auf gleicher Ebene (kein Tab), sodass man
 * Dateien per Drag & Drop dazwischen ziehen kann. Alle Aktionen laufen über Rechtsklick
 * (Kontextmenü) bzw. Drag & Drop; kein Kopf-Balken. Alles per JS/fetch, ohne Neuladen.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/google_drive.php';

$user      = getCurrentUserFromGuard();
$csrfToken = generateCsrfToken();
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

$configured   = driveConfigured();
$rennJahr     = (int) date('Y');
$plakatFolder = $bilderFolder = '';
$roots        = []; // beide Bereichswurzeln als Geschwister: [['id'=>, 'name'=>], ...]
if ($configured) {
    $rennJahr     = driveRennJahr($pdo);
    $plakatFolder = (string) (drivePlakatFolderId($pdo, $rennJahr) ?? '');
    $bilderFolder = (string) (driveBilderFolderId($pdo) ?? '');
    // Wurzel-IDs in einstellungen verankern (idempotent), damit ein Umbenennen der
    // Ordner (z. B. "Orga" -> "Orga-Team") sie nicht verwaisen lässt — driveRootFolderId
    // würde sonst bei fehlender ID einen neuen Namensordner anlegen.
    $anchor = $pdo->prepare('INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = `value`');
    foreach (['orga' => 'drive_root_orga_id', 'helfer' => 'drive_root_helfer_id'] as $bereich => $settingKey) {
        $rid = driveRootFolderId($pdo, $bereich);
        $anchor->execute(['k' => $settingKey, 'v' => $rid]);
        $meta = driveFileMeta($rid);
        $roots[] = ['id' => $rid, 'name' => (string) ($meta['name'] ?? driveBereichName($bereich))];
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
        .ftree { background:var(--white); border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.75rem; box-shadow:var(--shadow-card); list-style:none; margin:0; }
        .ftree > li + li { margin-top:0.25rem; }
        .ftree ul { list-style:none; margin:0; padding-left:1.5rem; }
        .tnode { display:flex; align-items:center; gap:0.45rem; padding:0.32rem 0.45rem; border-radius:6px; cursor:pointer; }
        .tnode:hover { background:#f2f8f4; }
        .tnode.selected { background:rgba(0,150,64,0.12); }
        .tnode-root > .tname { font-weight:600; color:var(--primary-dark); }
        .tnode-file { cursor:default; }
        .ttoggle { width:2.2rem; font-size:2rem; line-height:1; text-align:center; color:var(--text-light); flex:0 0 auto; user-select:none; }
        .tname { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tmeta { color:var(--text-light); font-size:0.78rem; flex:0 0 auto; }
        .tnode.drag-over { outline:2px solid var(--primary); outline-offset:-2px; }
        .fb-hint { font-size:0.75rem; color:var(--text-light); margin-top:0.75rem; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-light); }
        #fb-toast { position:fixed; bottom:1.25rem; left:50%; transform:translateX(-50%); background:#333; color:#fff; padding:0.6rem 1.1rem; border-radius:6px; font-size:0.85rem; opacity:0; transition:opacity .2s; pointer-events:none; z-index:50; }
        #fb-toast.show { opacity:1; }
        #fb-ctx { position:fixed; z-index:60; background:var(--white); border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-card); padding:0.3rem; min-width:200px; display:flex; flex-direction:column; }
        #fb-ctx[hidden] { display:none; }
        .fb-ctx-item { text-align:left; background:none; border:none; padding:0.42rem 0.7rem; border-radius:5px; font-size:0.85rem; color:var(--text); cursor:pointer; white-space:nowrap; }
        .fb-ctx-item:hover { background:#f2f8f4; color:var(--primary); }
        .fb-ctx-item.danger:hover { background:#fef2f2; color:#dc2626; }
        .fb-ctx-sep { height:1px; background:var(--border); margin:0.25rem 0.3rem; }
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

            <input type="file" id="up-file" accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg" hidden>

            <ul class="ftree" id="fb-tree">
                <?php foreach ($roots as $r): ?>
                <li>
                    <div class="tnode tnode-folder tnode-root" data-fid="<?= htmlspecialchars($r['id']) ?>" data-name="<?= htmlspecialchars($r['name']) ?>" data-folderid="<?= htmlspecialchars($r['id']) ?>" data-loaded="0" draggable="false">
                        <span class="ttoggle">▸</span>
                        <span class="tname">📁 <?= htmlspecialchars($r['name']) ?></span>
                    </div>
                    <ul class="tchildren" hidden></ul>
                </li>
                <?php endforeach; ?>
            </ul>

            <p class="fb-hint">Live aus dem geteilten Laufwerk „Marktlauf Orga". Ordnerzeile anklicken = auf-/zuklappen. <b>Rechtsklick</b> auf eine Zeile öffnet das Menü (Umbenennen, Löschen, Neuer Unterordner, Hochladen, Ordner festlegen). Dateien vom Rechner auf einen Ordner <b>ziehen</b> lädt sie hoch; Baum-Zeilen untereinander ziehen verschiebt.</p>

            <?php endif; ?>
        </main>
    </div>
    <div id="fb-toast"></div>

    <?php if ($configured): ?>
    <script>
    (function () {
        const CSRF     = <?= json_encode($csrfToken) ?>;
        const ROOTS    = <?= json_encode(array_column($roots, 'id')) ?>;
        const RENNJAHR = <?= json_encode($rennJahr) ?>;
        let plakatFolder = <?= json_encode($plakatFolder) ?>;
        let bilderFolder = <?= json_encode($bilderFolder) ?>;

        const tree = document.getElementById('fb-tree');
        const toast = document.getElementById('fb-toast');
        let draggedFid = null;

        const esc = s => (window.CSS && CSS.escape) ? CSS.escape(s) : s;
        const isRoot = fid => ROOTS.indexOf(fid) > -1;
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

        function buildRow(item) {
            const li = document.createElement('li');
            const node = document.createElement('div');
            node.dataset.fid = item.id;
            node.dataset.name = item.name;
            node.draggable = true;
            if (item.isFolder) {
                node.className = 'tnode tnode-folder';
                node.dataset.folderid = item.id;
                node.dataset.loaded = '0';
                const tog = document.createElement('span'); tog.className = 'ttoggle'; tog.textContent = '▸';
                const name = document.createElement('span'); name.className = 'tname'; name.textContent = '📁 ' + item.name;
                node.appendChild(tog); node.appendChild(name);
                li.appendChild(node);
                const sub = document.createElement('ul'); sub.className = 'tchildren'; sub.hidden = true; li.appendChild(sub);
            } else {
                node.className = 'tnode tnode-file';
                const sp = document.createElement('span'); sp.className = 'ttoggle'; sp.textContent = '';
                const name = document.createElement('span'); name.className = 'tname'; name.textContent = fileIcon(item.mimeType || '') + ' ' + item.name;
                const meta = document.createElement('span'); meta.className = 'tmeta'; meta.textContent = fmtSize(item.size || 0);
                node.appendChild(sp); node.appendChild(name); node.appendChild(meta);
                li.appendChild(node);
            }
            return li;
        }

        function childUlOf(node) { return node.closest('li').querySelector(':scope > ul.tchildren'); }
        // Elternordner-fid eines Knotens (für Move); '' wenn Wurzel (keine tchildren-Ebene darüber).
        function parentFidOf(fid) {
            const node = tree.querySelector('.tnode[data-fid="' + esc(fid) + '"]');
            if (!node) return '';
            const parentUl = node.closest('ul.tchildren');
            if (!parentUl) return '';
            const parentNode = parentUl.closest('li').querySelector(':scope > .tnode');
            return parentNode ? parentNode.dataset.fid : '';
        }

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
            const node = tree.querySelector('.tnode-folder[data-fid="' + esc(fid) + '"]');
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
        }

        // Klicks im Baum: Ordner auf-/zuklappen (delegiert).
        tree.addEventListener('click', function (e) {
            const folderNode = e.target.closest('.tnode-folder');
            if (folderNode) { selectFolder(folderNode); toggleFolder(folderNode); }
        });

        function doRename(node) {
            const cur = node.dataset.name;
            const name = prompt('Neuer Name:', cur);
            if (name === null) return;
            const t = name.trim();
            if (t === '' || t === cur) return;
            const body = new URLSearchParams({ csrf_token: CSRF, fid: node.dataset.fid, name: t });
            fetch('api/file_rename.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => {
                    node.dataset.name = t;
                    const nm = node.querySelector('.tname');
                    nm.textContent = nm.textContent.slice(0, 2) + t; // Icon + neuer Name
                    showToast('Umbenannt.');
                });
        }
        function doDelete(node) {
            const isFolder = node.classList.contains('tnode-folder');
            if (!confirm((isFolder ? 'Ordner samt Inhalt' : 'Datei') + ' „' + node.dataset.name + '“ in den Papierkorb?')) return;
            const body = new URLSearchParams({ csrf_token: CSRF, fid: node.dataset.fid });
            fetch('api/file_delete.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => { node.closest('li').remove(); showToast('In den Papierkorb verschoben.'); });
        }
        function doCreateFolder(parentFid, parentName, name) {
            const body = new URLSearchParams({ csrf_token: CSRF, parent: parentFid, name: name });
            return fetch('api/folder_create.php', { method: 'POST', body: body, redirect: 'manual' })
                .then(() => { refreshFolder(parentFid); showToast('Ordner angelegt in „' + parentName + '“.'); });
        }
        function uploadOne(file, folderFid) {
            const fd = new FormData();
            fd.append('csrf_token', CSRF); fd.append('folder', folderFid); fd.append('datei', file);
            return fetch('api/file_upload.php', { method: 'POST', body: fd, redirect: 'manual' });
        }
        function doUpload(file, folderFid, folderName) {
            return uploadOne(file, folderFid).then(() => { refreshFolder(folderFid); showToast('Hochgeladen in „' + folderName + '“.'); });
        }
        // Öffnet den Datei-Dialog und lädt die Wahl direkt in den Zielordner (Einmal-Listener).
        function pickAndUpload(folderFid, folderName) {
            const picker = document.getElementById('up-file');
            picker.value = '';
            const once = function () {
                picker.removeEventListener('change', once);
                if (picker.files && picker.files[0]) { doUpload(picker.files[0], folderFid, folderName).then(() => { picker.value = ''; }); }
            };
            picker.addEventListener('change', once);
            picker.click();
        }
        function designate(action, msg, fid, name) {
            const body = new URLSearchParams({ csrf_token: CSRF, action: action, folder: fid });
            fetch('dateien.php', { method: 'POST', body: body })
                .then(() => { if (action === 'set_plakat') plakatFolder = fid; else bilderFolder = fid; showToast(msg + ' „' + name + '“.'); });
        }

        // Drag & Drop: interne Verschiebung (Knoten auf Ordner) + externer Upload (Datei vom Rechner).
        tree.addEventListener('dragstart', function (e) {
            const node = e.target.closest('.tnode[data-fid]');
            if (node && node.draggable) { draggedFid = node.dataset.fid; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', node.dataset.fid); } catch (_) {} }
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
            if (!dt) { draggedFid = null; return; }
            e.preventDefault(); dt.classList.remove('drag-over');
            const target = dt.dataset.folderid;
            const tname  = dt.dataset.name;

            // (a) Externer Datei-Upload vom Rechner.
            const files = e.dataTransfer.files;
            if (files && files.length) {
                draggedFid = null;
                Promise.all([].map.call(files, f => uploadOne(f, target)))
                    .then(() => { refreshFolder(target); showToast(files.length + ' Datei(en) hochgeladen in „' + tname + '“.'); })
                    .catch(() => alert('Upload fehlgeschlagen.'));
                return;
            }

            // (b) Interne Verschiebung eines Baum-Knotens.
            if (!draggedFid || draggedFid === target) { draggedFid = null; return; }
            const source = parentFidOf(draggedFid);
            const moved = draggedFid; draggedFid = null;
            if (source === '') { showToast('Diese Zeile lässt sich nicht verschieben.'); return; }
            const body = new URLSearchParams({ csrf_token: CSRF, fid: moved, target: target, source: source });
            fetch('api/file_move.php', { method: 'POST', body: body })
                .then(r => r.json())
                .then(d => {
                    if (!d.ok) { alert(d.message || 'Verschieben fehlgeschlagen.'); return; }
                    const li = tree.querySelector('.tnode[data-fid="' + esc(moved) + '"]');
                    if (li) li.closest('li').remove();
                    refreshFolder(target);
                    showToast('Verschoben.');
                })
                .catch(() => alert('Verschieben fehlgeschlagen.'));
        });

        // Rechtsklick-Kontextmenü (delegiert).
        const ctx = document.createElement('div');
        ctx.id = 'fb-ctx'; ctx.hidden = true;
        document.body.appendChild(ctx);

        function hideCtx() { ctx.hidden = true; ctx.innerHTML = ''; }
        function ctxItem(label, cls, fn) {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'fb-ctx-item' + (cls ? ' ' + cls : '');
            b.textContent = label;
            b.addEventListener('click', function () { hideCtx(); fn(); });
            return b;
        }
        function ctxSep() { const s = document.createElement('div'); s.className = 'fb-ctx-sep'; return s; }

        tree.addEventListener('contextmenu', function (e) {
            const node = e.target.closest('.tnode');
            if (!node) return;
            e.preventDefault();
            ctx.innerHTML = '';
            const isFolder = node.classList.contains('tnode-folder');
            const root = isRoot(node.dataset.fid);
            if (isFolder) {
                ctx.appendChild(ctxItem('Umbenennen', '', () => doRename(node)));
                if (!root) { ctx.appendChild(ctxItem('Löschen', 'danger', () => doDelete(node))); }
                ctx.appendChild(ctxSep());
                ctx.appendChild(ctxItem('＋ Neuer Unterordner', '', () => {
                    selectFolder(node);
                    const name = prompt('Name des neuen Ordners in „' + node.dataset.name + '“:', '');
                    if (name === null) return;
                    const t = name.trim(); if (t === '') return;
                    doCreateFolder(node.dataset.fid, node.dataset.name, t);
                }));
                ctx.appendChild(ctxItem('⬆ Datei hochladen', '', () => { selectFolder(node); pickAndUpload(node.dataset.fid, node.dataset.name); }));
                ctx.appendChild(ctxSep());
                ctx.appendChild(ctxItem('📌 Als Plakate-Ordner (' + RENNJAHR + ')', '', () => { selectFolder(node); designate('set_plakat', 'Plakate-Ordner (' + RENNJAHR + ') gesetzt:', node.dataset.fid, node.dataset.name); }));
                ctx.appendChild(ctxItem('🖼️ Als Bilder-Ordner', '', () => { selectFolder(node); designate('set_bilder', 'Bilder-Ordner gesetzt:', node.dataset.fid, node.dataset.name); }));
            } else {
                const dlHref = 'api/file_download.php?fid=' + encodeURIComponent(node.dataset.fid);
                ctx.appendChild(ctxItem('⬇ Download', '', () => { window.location.href = dlHref; }));
                ctx.appendChild(ctxItem('Umbenennen', '', () => doRename(node)));
                ctx.appendChild(ctxItem('Löschen', 'danger', () => doDelete(node)));
            }
            // Sichtbar machen und in den Viewport klemmen (position:fixed → clientX/Y direkt).
            ctx.hidden = false;
            let x = e.clientX, y = e.clientY;
            if (x + ctx.offsetWidth > window.innerWidth) x = window.innerWidth - ctx.offsetWidth - 4;
            if (y + ctx.offsetHeight > window.innerHeight) y = window.innerHeight - ctx.offsetHeight - 4;
            ctx.style.left = Math.max(4, x) + 'px';
            ctx.style.top = Math.max(4, y) + 'px';
        });
        document.addEventListener('click', function (e) { if (!ctx.hidden && !ctx.contains(e.target)) hideCtx(); });
        document.addEventListener('contextmenu', function (e) { if (!ctx.hidden && !tree.contains(e.target)) hideCtx(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideCtx(); });
        window.addEventListener('scroll', hideCtx, true);

        // Start: beide Wurzeln aufklappen.
        tree.querySelectorAll(':scope > li > .tnode-root').forEach(n => loadChildren(n));
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
