<?php
/**
 * Helfer-Draht: Verzeichnis (Telefonbuch) + Briefings + Gruppen-Links.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user  = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf  = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();

$helfer = $pdo->query(
    "SELECT id, vorname, nachname, phone, email FROM helfer WHERE status = 'bestaetigt' ORDER BY nachname, vorname"
)->fetchAll(PDO::FETCH_ASSOC);

// Schicht-Titel je Helfer
$shifts = [];
try {
    $st = $pdo->query(
        'SELECT sz.helfer_id, sc.titel FROM schicht_zuteilung sz
           JOIN schichten sc ON sc.id = sz.schicht_id
          ORDER BY sc.tag, sc.von'
    );
    foreach ($st as $r) { $shifts[$r['helfer_id']][] = $r['titel']; }
} catch (PDOException $e) { /* Tabellen evtl. nicht vorhanden */ }

// Briefings (alle, zur Verwaltung)
$briefings = [];
try {
    $briefings = $pdo->query(
        'SELECT id, text, prioritaet, sichtbar, created_at FROM briefings ORDER BY id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Migration 019 noch nicht angewandt */ }

// Gruppen-Links
$telegramUrl = ''; $whatsappUrl = '';
try {
    $g = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('telegram_gruppe_url','whatsapp_gruppe_url')");
    foreach ($g->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if ($k === 'telegram_gruppe_url') { $telegramUrl = (string) ($v ?? ''); }
        if ($k === 'whatsapp_gruppe_url') { $whatsappUrl = (string) ($v ?? ''); }
    }
} catch (PDOException $e) { /* egal */ }

$prioLabel = ['normal' => 'Normal', 'wichtig' => 'Wichtig', 'notfall' => '⚠️ Notfall'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Helfer-Draht | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .hd-card { background: var(--white); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.25rem; margin-bottom: 1.25rem; }
        .hd-card h2 { font-size: 1rem; margin: 0 0 0.9rem; }
        .hd-table { width: 100%; border-collapse: collapse; }
        .hd-table th, .hd-table td { padding: 0.5rem 0.6rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.9rem; vertical-align: top; }
        .hd-table th { background: var(--bg); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); }
        .hd-table a { color: var(--primary); text-decoration: none; }
        .hd-shift { font-size: 0.78rem; color: var(--text-light); }
        .hd-field { margin-bottom: 0.9rem; }
        .hd-field label { display: block; font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.35rem; }
        .hd-field input, .hd-field textarea, .hd-field select { width: 100%; padding: 0.5rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; font-family: inherit; box-sizing: border-box; }
        .hd-row { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
        .briefing-item { padding: 0.6rem 0.8rem; border-left: 3px solid var(--border); border-radius: 6px; background: var(--bg); margin-bottom: 0.6rem; }
        .briefing-item.p-wichtig { border-left-color: var(--primary); }
        .briefing-item.p-notfall { border-left-color: #d32f2f; background: #fdecea; }
        .briefing-item.hidden-b { opacity: 0.5; }
        .briefing-meta { font-size: 0.72rem; color: var(--text-light); margin-top: 0.3rem; }
        .hd-alert { padding: 0.6rem 0.9rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .hd-alert.ok { background: #d1fae5; color: #065f46; }
        .hd-alert.err { background: #fdecea; color: #b91c1c; }
        .inline-form { display: inline; }
    </style>
</head>
<body>
<?php $activeNav = 'helfer_draht'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Helfer-Draht</h1>
            <p class="content-subtitle">Verzeichnis, Briefings &amp; Gruppen — Helfer am Renntag erreichen</p>
        </header>

        <?php if ($flashSuccess): ?><div class="hd-alert ok"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="hd-alert err"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

        <!-- Verzeichnis / Telefonbuch -->
        <div class="hd-card">
            <h2>Verzeichnis (<?= count($helfer) ?> bestätigte Helfer)</h2>
            <div class="hd-row" style="margin-bottom:0.8rem">
                <input type="text" id="hd-search" placeholder="Suche (Name, Schicht) …" style="flex:1 1 220px;padding:0.45rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.9rem">
                <button class="btn btn-small btn-secondary" id="hd-copy-phone">Sichtbare Nummern kopieren</button>
                <button class="btn btn-small btn-secondary" id="hd-copy-mail">Sichtbare Mails kopieren</button>
                <span id="hd-copied" style="display:none;font-size:0.8rem;color:#16a34a">kopiert</span>
            </div>
            <?php if (empty($helfer)): ?>
                <p style="color:var(--text-light);font-size:0.9rem">Noch keine bestätigten Helfer.</p>
            <?php else: ?>
            <table class="hd-table">
                <thead><tr><th>Name</th><th>Telefon</th><th>E-Mail</th><th>Schicht(en)</th></tr></thead>
                <tbody>
                <?php foreach ($helfer as $h):
                    $name = trim($h['vorname'] . ' ' . $h['nachname']);
                    $shiftList = $shifts[$h['id']] ?? [];
                    $tel = preg_replace('/\s+/', '', (string) $h['phone']);
                    $rowSearch = strtolower($name . ' ' . implode(' ', $shiftList));
                ?>
                <tr class="hd-trow" data-search="<?= htmlspecialchars($rowSearch) ?>" data-phone="<?= htmlspecialchars((string) $h['phone']) ?>" data-mail="<?= htmlspecialchars((string) $h['email']) ?>">
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?php if ($tel !== ''): ?><a href="tel:<?= htmlspecialchars($tel) ?>"><?= htmlspecialchars($h['phone']) ?></a><?php endif; ?></td>
                    <td><?php if ($h['email'] !== ''): ?><a href="mailto:<?= htmlspecialchars($h['email']) ?>"><?= htmlspecialchars($h['email']) ?></a><?php endif; ?></td>
                    <td class="hd-shift"><?= $shiftList ? htmlspecialchars(implode(', ', $shiftList)) : '–' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Briefings -->
        <div class="hd-card">
            <h2>Briefings / Infos an Helfer</h2>
            <form method="post" action="api/briefing_crud.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="create">
                <div class="hd-field">
                    <label for="b-text">Neue Info / Briefing</label>
                    <textarea id="b-text" name="text" rows="3" placeholder="z. B. Treffpunkt 07:30 Uhr am Westring, Warnwesten am Zelt abholen …"></textarea>
                </div>
                <div class="hd-row">
                    <select name="prioritaet" style="max-width:180px;padding:0.45rem 0.6rem;border:1px solid var(--border);border-radius:6px">
                        <option value="normal">Normal</option>
                        <option value="wichtig">Wichtig</option>
                        <option value="notfall">⚠️ Notfall</option>
                    </select>
                    <button type="submit" class="btn btn-small btn-primary">Veröffentlichen</button>
                </div>
            </form>

            <div style="margin-top:1rem">
                <?php if (empty($briefings)): ?>
                    <p style="color:var(--text-light);font-size:0.9rem">Noch keine Briefings.</p>
                <?php else: foreach ($briefings as $b): ?>
                    <div class="briefing-item p-<?= htmlspecialchars($b['prioritaet']) ?> <?= $b['sichtbar'] ? '' : 'hidden-b' ?>">
                        <?php if ($b['prioritaet'] !== 'normal'): ?><strong><?= $prioLabel[$b['prioritaet']] ?></strong><br><?php endif; ?>
                        <?= nl2br(htmlspecialchars($b['text'])) ?>
                        <div class="briefing-meta">
                            <?= date('d.m.Y H:i', strtotime($b['created_at'])) ?> Uhr ·
                            <?= $b['sichtbar'] ? 'sichtbar' : 'ausgeblendet' ?>
                            <form class="inline-form" method="post" action="api/briefing_crud.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="btn-icon" style="border:none;background:none;color:var(--primary);cursor:pointer;font-size:0.72rem"><?= $b['sichtbar'] ? 'ausblenden' : 'einblenden' ?></button>
                            </form>
                            ·
                            <form class="inline-form" method="post" action="api/briefing_crud.php" onsubmit="return confirm('Briefing löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="btn-icon" style="border:none;background:none;color:#b91c1c;cursor:pointer;font-size:0.72rem">löschen</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Gruppen-Links -->
        <div class="hd-card">
            <h2>Helfer-Gruppen (Beitritts-Links)</h2>
            <p class="content-subtitle" style="margin-bottom:0.9rem">Werden den Helfern in ihrer Zugangsansicht als „Beitreten"-Buttons angezeigt. Telegram empfohlen (kostenlos).</p>
            <form method="post" action="api/briefing_crud.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="links">
                <div class="hd-field">
                    <label for="tg">Telegram-Gruppen-Link</label>
                    <input type="url" id="tg" name="telegram_url" value="<?= htmlspecialchars($telegramUrl) ?>" placeholder="https://t.me/…">
                </div>
                <div class="hd-field">
                    <label for="wa">WhatsApp-Gruppen-Link (optional)</label>
                    <input type="url" id="wa" name="whatsapp_url" value="<?= htmlspecialchars($whatsappUrl) ?>" placeholder="https://chat.whatsapp.com/…">
                </div>
                <button type="submit" class="btn btn-small btn-primary">Links speichern</button>
            </form>
        </div>
    </main>
</div>

<script>
// Suche filtert die Verzeichnis-Zeilen (client-seitig)
const search = document.getElementById('hd-search');
if (search) {
    search.addEventListener('input', () => {
        const q = search.value.toLowerCase().trim();
        document.querySelectorAll('.hd-trow').forEach(tr => {
            tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
        });
    });
}
function copyVisible(attr) {
    const vals = [];
    document.querySelectorAll('.hd-trow').forEach(tr => {
        if (tr.style.display !== 'none' && tr.dataset[attr]) vals.push(tr.dataset[attr]);
    });
    if (!vals.length) return;
    navigator.clipboard.writeText(vals.join(', ')).then(() => {
        const m = document.getElementById('hd-copied');
        m.style.display = 'inline';
        setTimeout(() => { m.style.display = 'none'; }, 2000);
    });
}
document.getElementById('hd-copy-phone').addEventListener('click', () => copyVisible('phone'));
document.getElementById('hd-copy-mail').addEventListener('click', () => copyVisible('mail'));

// Burger-Menü
const burgerBtn = document.getElementById('burger-btn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
if (burgerBtn) {
    burgerBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); sidebarOverlay.classList.toggle('active'); });
    sidebarOverlay.addEventListener('click', () => { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); });
}
</script>
</body>
</html>
