<?php
/**
 * Vereins-/Laufevent-Anschreiben-Editor (Admin + Orga).
 * Split-View: links Markdown, rechts Live-Vorschau (serverseitig gerendert,
 * identisch zum echten Versand). Zwei Vorlagen: Verein, Laufevent.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/verein_brief.php';
require_once __DIR__ . '/../src/channels/mail.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$slug = (string) ($_GET['slug'] ?? 'verein');
if (!vereinBriefSlugValid($slug)) {
    $slug = 'verein';
}

$pdo = getDbConnection();
$vorlage = vereinBriefLoad($pdo, $slug, (int) $user['id']);
$defaults = vereinBriefDefaults();
$default = $defaults[$slug];
$platzhalter = vereinBriefPlatzhalterHilfe();

$draftHinweis = '';
if ($vorlage['draft'] && $vorlage['draft_ts'] !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $vorlage['draft_ts']);
    if ($dt) {
        $draftHinweis = 'Gespeichert am ' . $dt->format('d.m.Y, H:i') . ' Uhr';
    }
}
$plakate = plakateAnhang($pdo);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Vereins-Anschreiben | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .brief-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .brief-tab {
            padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none;
            background: var(--white); border: 1px solid var(--border); color: var(--text); font-size: 0.9rem;
        }
        .brief-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .brief-card { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.5rem; margin-bottom: 1.25rem; }
        .brief-betreff { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; }
        .brief-platzhalter { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.75rem 0; }
        .ph-chip {
            font-family: monospace; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;
            background: var(--bg); border: 1px solid var(--border); cursor: pointer; color: var(--text);
        }
        .ph-chip:hover { background: var(--border); }
        .brief-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 900px) { .brief-split { grid-template-columns: 1fr; } }
        .brief-split h3 { font-size: 0.9rem; margin: 0 0 0.5rem; color: var(--text-light); }
        /* Kopfzeile wie im Sponsoren-Editor, damit die Legende rechts andockt */
        .brief-split-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
        .brief-split-head h3 { margin: 0; }
        #koerper_md {
            width: 100%; min-height: 460px; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 4px; font-family: monospace; font-size: 0.85rem; line-height: 1.5;
            box-sizing: border-box; resize: vertical;
        }
        #preview-frame { width: 100%; min-height: 460px; border: 1px solid var(--border); border-radius: 4px; background: #fff; }
        .brief-actions { display: flex; gap: 1rem; margin-top: 1.25rem; align-items: center; flex-wrap: wrap; }
        .brief-hint { font-size: 0.8rem; color: var(--text-light); }
        .plakat-liste { list-style: none; margin: 0.75rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
        .plakat-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; }
        .plakat-item span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .plakat-item .btn-del { flex-shrink: 0; padding: 0.25rem 0.6rem; font-size: 0.8rem; }
        .plakat-badge { display: inline-flex; align-items: center; gap: 0.3rem; background: var(--primary); color: #fff; border-radius: 12px; padding: 0.15rem 0.6rem; font-size: 0.75rem; font-weight: 600; }
        .plakat-section { }
        .plakat-section-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .plakat-anleitung { font-size: 0.85rem; color: var(--text-light); margin: 0.4rem 0 0.9rem; line-height: 1.55; }
        .plakat-upload-form { margin-top: 0.75rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body>
<?php $activeNav = 'vereine_briefe'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Vereins- &amp; Laufevent-Anschreiben</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="brief-tabs">
                <?php foreach ($defaults as $s => $d): ?>
                    <a class="brief-tab<?= $s === $slug ? ' active' : '' ?>" href="vereine_briefe.php?slug=<?= urlencode($s) ?>"><?= htmlspecialchars($d['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <form method="post" action="api/verein_brief_save.php" id="brief-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">

                <div class="brief-card">
                    <label for="betreff"><strong>Betreff</strong></label>
                    <input type="text" id="betreff" name="betreff" class="brief-betreff" maxlength="255"
                           value="<?= htmlspecialchars($vorlage['betreff']) ?>">

                    <div class="brief-platzhalter">
                        <span class="brief-hint">Platzhalter einfügen:</span>
                        <?php foreach ($platzhalter as $ph => $beschreibung): ?>
                            <span class="ph-chip" data-ph="<?= htmlspecialchars($ph) ?>" title="<?= htmlspecialchars($beschreibung) ?>"><?= htmlspecialchars($ph) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="brief-split">
                        <div>
                            <div class="brief-split-head">
                                <h3>Markdown</h3>
                                <?= sponsorMarkdownLegende() ?>
                            </div>
                            <textarea id="koerper_md" name="koerper_md"><?= htmlspecialchars($vorlage['koerper_md']) ?></textarea>
                        </div>
                        <div>
                            <h3>Vorschau (Beispieldaten)</h3>
                            <iframe id="preview-frame" sandbox="" title="Vorschau"></iframe>
                        </div>
                    </div>

                    <div class="brief-actions">
                        <button type="button" class="btn btn-primary" id="btn-save">Speichern</button>
                        <button type="button" class="btn btn-secondary" id="reset-default">Standardtext wiederherstellen</button>
                        <span id="draft-status" class="brief-hint"><?= htmlspecialchars($draftHinweis) ?></span>
                    </div>

                </div>
            </form>

            <div class="brief-card">
                <div class="plakat-section">
                    <div class="plakat-section-header">
                        <strong>📎 Plakate als PDF-Anhang</strong>
                        <?php if (count($plakate) > 0): ?>
                            <span class="plakat-badge"><?= count($plakate) ?> PDF<?= count($plakate) !== 1 ? 's' : '' ?> werden angehängt</span>
                        <?php endif; ?>
                    </div>
                    <?php
                    try {
                        $stmtP = $pdo->query("SELECT id, originalname, groesse FROM dateien WHERE bereich = 'orga' AND kategorie = 'plakat' ORDER BY id ASC");
                        $plakat_rows = $stmtP->fetchAll();
                    } catch (PDOException $e) {
                        $plakat_rows = [];
                    }
                    if (count($plakat_rows) > 0): ?>
                    <ul class="plakat-liste">
                        <?php foreach ($plakat_rows as $pr):
                            $kb = round((int)$pr['groesse'] / 1024);
                        ?>
                            <li class="plakat-item">
                                <span title="<?= htmlspecialchars($pr['originalname']) ?>">📄 <?= htmlspecialchars($pr['originalname']) ?></span>
                                <small class="brief-hint"><?= $kb ?> KB</small>
                                <form method="post" action="api/plakat_loeschen.php" style="margin:0;" onsubmit="return confirm('Plakat löschen?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="datei_id" value="<?= (int)$pr['id'] ?>">
                                    <input type="hidden" name="redirect" value="vereine_briefe.php?slug=<?= urlencode($slug) ?>">
                                    <button type="submit" class="btn btn-secondary btn-del">Löschen</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="brief-hint" style="margin:0.5rem 0;">Noch keine Plakate hochgeladen — Anschreiben werden ohne Anhang gesendet.</p>
                    <?php endif; ?>

                    <form method="post" action="api/file_upload.php" enctype="multipart/form-data" class="plakat-upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="bereich" value="orga">
                        <input type="hidden" name="kategorie" value="plakat">
                        <input type="hidden" name="redirect_after" value="vereine_briefe.php?slug=<?= urlencode($slug) ?>">
                        <input type="file" name="datei" accept="application/pdf" required style="font-size:0.9rem;">
                        <button type="submit" class="btn btn-primary">PDF hochladen</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const slug = <?= json_encode($slug) ?>;
        const defaultText = <?= json_encode($default['koerper_md']) ?>;
        const defaultBetreff = <?= json_encode($default['betreff']) ?>;
        const ta = document.getElementById('koerper_md');
        const betreff = document.getElementById('betreff');
        const frame = document.getElementById('preview-frame');
        let timer = null;

        function renderPreview() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('slug', slug);
            body.set('koerper_md', ta.value);
            fetch('api/verein_brief_preview.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.text(); })
                .then(function(html) { frame.srcdoc = html; })
                .catch(function() { /* Vorschau optional */ });
        }
        function schedule() { clearTimeout(timer); timer = setTimeout(renderPreview, 400); }
        ta.addEventListener('input', schedule);
        renderPreview();

        document.querySelectorAll('.ph-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                const ph = chip.dataset.ph;
                const start = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.slice(0, start) + ph + ta.value.slice(end);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + ph.length;
                schedule();
            });
        });

        document.getElementById('reset-default').addEventListener('click', function() {
            if (!confirm('Text und Betreff auf die Standardvorlage zurücksetzen? Ungespeicherte Änderungen gehen verloren.')) return;
            ta.value = defaultText;
            betreff.value = defaultBetreff;
            renderPreview();
        });

        document.getElementById('btn-save').addEventListener('click', function() {
            const statusEl = document.getElementById('draft-status');
            statusEl.textContent = 'Speichert…';
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('vorlage_art', 'verein');
            body.set('slug', slug);
            body.set('betreff', betreff.value);
            body.set('koerper_md', ta.value);
            fetch('api/draft_save.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    var m = (data.gespeichert_am || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
                    var ts = m ? m[3] + '.' + m[2] + '.' + m[1] + ', ' + m[4] + ':' + m[5] + ' Uhr' : '';
                    statusEl.textContent = 'Gespeichert am ' + ts;
                } else {
                    statusEl.textContent = 'Fehler: ' + (data.error || 'Unbekannt');
                }
            })
            .catch(function() { statusEl.textContent = 'Speichern fehlgeschlagen.'; });
        });
    })();

    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function() { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) { link.addEventListener('click', closeSidebar); });
    })();
    </script>
</body>
</html>
