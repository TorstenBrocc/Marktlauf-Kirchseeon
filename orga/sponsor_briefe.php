<?php
/**
 * Sponsorenbriefe-Editor (Admin + Orga).
 * Split-View: links Markdown, rechts Live-Vorschau (serverseitig gerendert,
 * identisch zum echten Versand). Drei Vorlagen: Erstanschreiben, Folgejahr, Frei.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_brief.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$slug = (string) ($_GET['slug'] ?? 'erstanschreiben');
if (!sponsorBriefSlugValid($slug)) {
    $slug = 'erstanschreiben';
}

$pdo = getDbConnection();
$vorlage = sponsorBriefLoad($pdo, $slug);
$defaults = sponsorBriefDefaults();
$default = $defaults[$slug];
$platzhalter = sponsorBriefPlatzhalterHilfe();

// Einstellungen für den Admin-Bereich laden
$briefSettings = [];
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('sponsor_brief_event_datum','sponsor_brief_antwort_bis','sponsoring_pakete')");
    while ($row = $stmt->fetch()) { $briefSettings[$row['key']] = $row['value']; }
} catch (PDOException $e) {}

$briefEventDatum = $briefSettings['sponsor_brief_event_datum'] ?? '';
$briefAntwortBis = $briefSettings['sponsor_brief_antwort_bis'] ?? '';
$paketeDefaults = [
    ['key'=>'hauptsponsor','name'=>'Hauptsponsor','investition'=>'auf Anfrage',
     'highlights'=>'Zentraler Partner des Events, maximale Sichtbarkeit auf allen Kanälen'],
    ['key'=>'gold','name'=>'Gold','investition'=>'1.000 €',
     'highlights'=>'Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fläche, 5 Startplätze, Moderations-Erwähnungen'],
    ['key'=>'silber','name'=>'Silber','investition'=>'500 €',
     'highlights'=>'Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze'],
    ['key'=>'bronze','name'=>'Bronze','investition'=>'250 €',
     'highlights'=>'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben'],
];
$paketeMap = [];
if (!empty($briefSettings['sponsoring_pakete'])) {
    $decoded = json_decode($briefSettings['sponsoring_pakete'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $p) { if (isset($p['key'])) $paketeMap[$p['key']] = $p; }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sponsorenbriefe | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .brief-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .brief-tab {
            padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none;
            background: var(--white); border: 1px solid var(--border); color: var(--text);
            font-size: 0.9rem;
        }
        .brief-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .brief-card {
            background: var(--white); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem; margin-bottom: 1.25rem;
        }
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
        #koerper_md {
            width: 100%; min-height: 460px; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 4px; font-family: monospace; font-size: 0.85rem; line-height: 1.5;
            box-sizing: border-box; resize: vertical;
        }
        #preview-frame {
            width: 100%; min-height: 460px; border: 1px solid var(--border); border-radius: 4px; background: #fff;
        }
        .brief-actions { display: flex; gap: 1rem; margin-top: 1.25rem; align-items: center; }
        .brief-hint { font-size: 0.8rem; color: var(--text-light); }
    </style>
</head>
<body>
<?php $activeNav = 'sponsor_briefe'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Sponsorenbriefe</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="brief-tabs">
                <?php foreach ($defaults as $s => $d): ?>
                    <a class="brief-tab<?= $s === $slug ? ' active' : '' ?>" href="sponsor_briefe.php?slug=<?= urlencode($s) ?>"><?= htmlspecialchars($d['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <form method="post" action="api/sponsor_brief_save.php" id="brief-form">
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
                            <h3>Markdown</h3>
                            <textarea id="koerper_md" name="koerper_md"><?= htmlspecialchars($vorlage['koerper_md']) ?></textarea>
                        </div>
                        <div>
                            <h3>Vorschau (Beispieldaten)</h3>
                            <iframe id="preview-frame" sandbox="" title="Vorschau"></iframe>
                        </div>
                    </div>

                    <div class="brief-actions">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                        <button type="button" class="btn btn-secondary" id="reset-default">Standardtext wiederherstellen</button>
                        <span class="brief-hint">Leerer Text = Standardvorlage wird verwendet.</span>
                    </div>
                </div>
            </form>

            <?php if ($isAdmin): ?>
            <form method="post" action="api/sponsor_brief_settings_save.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <div class="brief-card" style="margin-top:1.5rem">
                    <h3 style="font-size:0.95rem;margin:0 0 1rem">Platzhalter-Einstellungen</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
                        <div>
                            <label style="display:block;font-size:0.85rem;color:var(--text-light);margin-bottom:0.35rem">
                                Event-Datum <code style="font-size:0.8rem">{{event_datum}}</code>
                            </label>
                            <input type="date" name="sponsor_brief_event_datum"
                                   value="<?= htmlspecialchars($briefEventDatum) ?>"
                                   style="padding:0.4rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.9rem;width:100%;box-sizing:border-box">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.85rem;color:var(--text-light);margin-bottom:0.35rem">
                                Rückmeldefrist <code style="font-size:0.8rem">{{antwort_bis}}</code>
                            </label>
                            <input type="date" name="sponsor_brief_antwort_bis"
                                   value="<?= htmlspecialchars($briefAntwortBis) ?>"
                                   style="padding:0.4rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.9rem;width:100%;box-sizing:border-box">
                        </div>
                    </div>
                    <h3 style="font-size:0.95rem;margin:0 0 0.6rem">
                        Sponsoring-Pakete <code style="font-size:0.8rem">{{paket_tabelle}}</code>
                    </h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-bottom:1rem">
                        <thead>
                            <tr style="background:var(--bg)">
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border);width:110px">Paket</th>
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border);width:140px">Investition</th>
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border)">Highlights</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paketeDefaults as $pd):
                            $pVals = $paketeMap[$pd['key']] ?? $pd; ?>
                            <tr>
                                <td style="padding:0.4rem 0.5rem;font-weight:500;white-space:nowrap"><?= htmlspecialchars($pd['name']) ?></td>
                                <td style="padding:0.4rem 0.5rem">
                                    <input type="text" name="paket_<?= htmlspecialchars($pd['key']) ?>_investition"
                                           value="<?= htmlspecialchars((string)($pVals['investition'] ?? $pd['investition'])) ?>"
                                           maxlength="60"
                                           style="width:100%;padding:0.35rem;border:1px solid var(--border);border-radius:4px;box-sizing:border-box">
                                </td>
                                <td style="padding:0.4rem 0.5rem">
                                    <input type="text" name="paket_<?= htmlspecialchars($pd['key']) ?>_highlights"
                                           value="<?= htmlspecialchars((string)($pVals['highlights'] ?? $pd['highlights'])) ?>"
                                           maxlength="255"
                                           style="width:100%;padding:0.35rem;border:1px solid var(--border);border-radius:4px;box-sizing:border-box">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="brief-actions">
                        <button type="submit" class="btn btn-secondary">Einstellungen speichern</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>

        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const defaultText = <?= json_encode($default['koerper_md']) ?>;
        const defaultBetreff = <?= json_encode($default['betreff']) ?>;
        const ta = document.getElementById('koerper_md');
        const betreff = document.getElementById('betreff');
        const frame = document.getElementById('preview-frame');
        let timer = null;

        function renderPreview() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('koerper_md', ta.value);
            fetch('api/sponsor_brief_preview.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            })
                .then(function(r) { return r.text(); })
                .then(function(html) { frame.srcdoc = html; })
                .catch(function() { /* Vorschau optional */ });
        }
        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(renderPreview, 400);
        }
        ta.addEventListener('input', schedule);
        renderPreview();

        // Platzhalter an Cursorposition einfügen
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
            if (!confirm('Text und Betreff auf die Standardvorlage zurücksetzen? Ungespeicherte Änderungen gehen verloren.')) {
                return;
            }
            ta.value = defaultText;
            betreff.value = defaultBetreff;
            renderPreview();
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
