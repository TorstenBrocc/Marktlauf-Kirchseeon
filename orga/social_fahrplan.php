<?php
/**
 * Social-Fahrplan: terminierter Contentplan als Einstieg der Social-Strecke.
 * Spec: intern/social-fahrplan-redesign-spec.md (Schnitt 1).
 * Zeile "oeffnen" springt vorerst in den Orchestrator mit vorgewaehltem Anlass;
 * ab Schnitt 2 haengt hier das Post-Detail dran (post_id).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/social_anlaesse.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();
$pdo     = getDbConnection();

$anlaesse = socialAnlaesse();
$nutzer   = orgaUserListe($pdo);

// Strava-Absprung (wie Orchestrator-Kopf) + Merkfeld-Notiz
$stravaUrl = '';
$merkfeld  = '';
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('strava_url', 'social_merkfeld')");
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if ($k === 'strava_url')      { $stravaUrl = (string) ($v ?? ''); }
        if ($k === 'social_merkfeld') { $merkfeld  = (string) ($v ?? ''); }
    }
} catch (PDOException $e) {
    // Einstellungen evtl. noch leer
}

$filter = $_GET['filter'] ?? 'offen';
if (!in_array($filter, ['offen', 'meine', 'erledigt', 'alle'], true)) {
    $filter = 'offen';
}

$eintraege = $pdo->query(
    'SELECT f.*, u.name AS zustaendig_name
       FROM social_fahrplan f
  LEFT JOIN users u ON u.id = f.zustaendig_user_id
   ORDER BY (f.zieldatum IS NULL), f.zieldatum, f.id'
)->fetchAll(PDO::FETCH_ASSOC);

$heute = date('Y-m-d');
$rows  = [];
foreach ($eintraege as $e) {
    if ($filter === 'offen'    && $e['status'] !== 'offen') { continue; }
    if ($filter === 'erledigt' && $e['status'] !== 'erledigt') { continue; }
    if ($filter === 'meine'    && (int) $e['zustaendig_user_id'] !== (int) $user['id']) { continue; }
    $rows[] = $e;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Social-Fahrplan | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Zielstil Helfer-Draht: hd-card/hd-table (orga.css) + abgetoentes Gruen fuer Aktionen */
        .fp-actions a, .fp-actions button {
            color: var(--primary-dark); background: none; border: none; padding: 0;
            font-size: var(--fs-base); cursor: pointer; text-decoration: none; font-family: inherit;
        }
        .fp-actions a:hover, .fp-actions button:hover { text-decoration: underline; }
        .fp-actions { display: flex; gap: 0.9rem; flex-wrap: wrap; }
        .fp-kopfzeile { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.9rem; }
        .fp-filter { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .fp-filter a {
            font-size: 0.82rem; padding: 0.25rem 0.7rem; border-radius: 999px;
            border: 1px solid var(--border); color: var(--text-light); text-decoration: none;
        }
        .fp-filter a.aktiv { border-color: var(--primary-dark); color: var(--primary-dark); font-weight: 600; }
        .fp-badge { display: inline-block; font-size: 0.75rem; padding: 0.15rem 0.6rem; border-radius: 999px; white-space: nowrap; }
        .fp-badge.ueberfaellig { background: #fff3cd; color: #856404; }
        .fp-badge.heute        { background: #d1fae5; color: #065f46; }
        .fp-badge.offen        { background: var(--bg); color: var(--text-light); border: 1px solid var(--border); }
        .fp-badge.erledigt     { background: var(--bg); color: var(--text-light); }
        .fp-wiederkehr { font-size: 0.78rem; color: var(--text-light); white-space: nowrap; }
        .fp-notiz input {
            width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.85rem;
            padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 6px;
        }
        .fp-form { display: none; margin-bottom: 1rem; padding: 0.9rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); }
        .fp-form.offen { display: block; }
        .fp-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.7rem; }
        .fp-form label { display: block; font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.25rem; }
        .fp-form input, .fp-form select {
            width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.88rem;
            padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; background: var(--white);
        }
        .fp-form .fp-form-actions { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.8rem; }
        #fp-msg { display: none; font-size: 0.85rem; margin-top: 0.6rem; }
        .hd-table td { vertical-align: middle; }
        @media (max-width: 720px) {
            .hd-table thead { display: none; }
            .hd-table tr { display: block; border-bottom: 1px solid var(--border); padding: 0.5rem 0; }
            .hd-table td { display: block; border: none; padding: 0.15rem 0.4rem; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'social_fahrplan'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Social-Fahrplan</h1>
            <p class="content-subtitle">Terminierter Contentplan — Thema öffnen, Entwürfe erzeugen, veröffentlichen</p>
            <?php if ($stravaUrl): ?>
            <ul class="quick-links" style="margin-top:0.75rem;padding:0;">
                <li style="border:none;padding:0;"><a href="<?= htmlspecialchars($stravaUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-strava">Strava öffnen</a></li>
            </ul>
            <?php endif; ?>
        </header>

        <div class="hd-card fp-notiz">
            <input type="text" id="fp-merkfeld" value="<?= htmlspecialchars($merkfeld) ?>"
                   placeholder="Notiz (Merkfeld) — speichert beim Verlassen des Feldes">
        </div>

        <div class="hd-card">
            <div class="fp-kopfzeile">
                <div class="fp-filter">
                    <?php foreach (['offen' => 'Offen', 'meine' => 'Meine', 'erledigt' => 'Erledigt', 'alle' => 'Alle'] as $fk => $fl): ?>
                    <a href="?filter=<?= $fk ?>" class="<?= $filter === $fk ? 'aktiv' : '' ?>"><?= $fl ?></a>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-small btn-primary" id="fp-neu">+ Thema einplanen</button>
            </div>

            <form class="fp-form" id="fp-form">
                <input type="hidden" name="id" value="">
                <div class="fp-form-grid">
                    <div>
                        <label for="fp-anlass">Thema / Anlass</label>
                        <select name="anlass_key" id="fp-anlass">
                            <?php
                            $gruppen = [];
                            foreach ($anlaesse as $key => $def) { $gruppen[$def['gruppe']][$key] = $def['ui']; }
                            foreach ($gruppen as $gruppe => $opts): ?>
                            <optgroup label="<?= htmlspecialchars($gruppe) ?>">
                                <?php foreach ($opts as $key => $ui): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($ui) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="fp-datum">Zieldatum</label>
                        <input type="date" name="zieldatum" id="fp-datum">
                    </div>
                    <div>
                        <label for="fp-zustaendig">Zuständig</label>
                        <select name="zustaendig_user_id" id="fp-zustaendig">
                            <option value="">— niemand —</option>
                            <?php foreach ($nutzer as $n): ?>
                            <option value="<?= (int) $n['id'] ?>"><?= htmlspecialchars($n['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="fp-frequenz">Wiederkehr (alle N Tage, leer = einmalig)</label>
                        <input type="number" name="frequenz_tage" id="fp-frequenz" min="1" max="365" step="1">
                    </div>
                    <div>
                        <label for="fp-ende">Wiederkehr-Ende</label>
                        <input type="date" name="ende" id="fp-ende">
                    </div>
                </div>
                <div class="fp-form-actions">
                    <button type="submit" class="btn btn-small btn-primary" id="fp-speichern">Speichern</button>
                    <button type="button" class="btn btn-small btn-secondary" id="fp-abbrechen">Abbrechen</button>
                </div>
            </form>
            <div id="fp-msg"></div>

            <?php if (!$rows): ?>
            <p style="color:var(--text-light);font-size:0.9rem;margin:0.5rem 0 0">Keine Einträge in dieser Ansicht.</p>
            <?php else: ?>
            <table class="hd-table">
                <thead>
                    <tr><th>Fällig</th><th>Thema</th><th>Zuständig</th><th>Status</th><th>Aktionen</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $e):
                    $def = $anlaesse[$e['anlass_key']] ?? null;
                    $themaLabel = $def ? $def['ui'] : $e['anlass_key'];
                    if ($e['status'] === 'erledigt') {
                        $badge = ['erledigt', 'erledigt'];
                    } elseif ($e['zieldatum'] !== null && $e['zieldatum'] < $heute) {
                        $badge = ['ueberfaellig', 'überfällig'];
                    } elseif ($e['zieldatum'] === $heute) {
                        $badge = ['heute', 'heute fällig'];
                    } else {
                        $badge = ['offen', 'offen'];
                    }
                ?>
                <tr>
                    <td><?= $e['zieldatum'] ? htmlspecialchars(date('d.m.Y', strtotime($e['zieldatum']))) : '—' ?></td>
                    <td>
                        <?= htmlspecialchars($themaLabel) ?>
                        <?php if ($e['frequenz_tage']): ?>
                        <span class="fp-wiederkehr">↻ alle <?= (int) $e['frequenz_tage'] ?> Tage<?= $e['ende'] ? ' bis ' . htmlspecialchars(date('d.m.', strtotime($e['ende']))) : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e['zustaendig_name'] ? htmlspecialchars($e['zustaendig_name']) : '<span style="color:var(--text-light)">—</span>' ?></td>
                    <td><span class="fp-badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                    <td class="fp-actions">
                        <a href="social_orchestrator.php?anlass=<?= rawurlencode($e['anlass_key']) ?>">öffnen</a>
                        <button type="button" class="fp-edit"
                            data-id="<?= (int) $e['id'] ?>"
                            data-anlass="<?= htmlspecialchars($e['anlass_key']) ?>"
                            data-datum="<?= htmlspecialchars((string) $e['zieldatum']) ?>"
                            data-zustaendig="<?= (int) $e['zustaendig_user_id'] ?>"
                            data-frequenz="<?= (int) $e['frequenz_tage'] ?>"
                            data-ende="<?= htmlspecialchars((string) $e['ende']) ?>">bearbeiten</button>
                        <?php if ($e['status'] === 'offen'): ?>
                        <button type="button" class="fp-erledigt" data-id="<?= (int) $e['id'] ?>">erledigt</button>
                        <?php endif; ?>
                        <button type="button" class="fp-loeschen" data-id="<?= (int) $e['id'] ?>">löschen</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;

function fpMsg(text, ok) {
    const el = document.getElementById('fp-msg');
    el.textContent = text;
    el.style.color = ok ? '#16a34a' : '#dc2626';
    el.style.display = 'block';
}
function fpPost(daten) {
    const body = new URLSearchParams(daten);
    body.set('csrf_token', csrf);
    return fetch('api/fahrplan_crud.php', { method: 'POST', body })
        .then(r => r.json());
}

const form = document.getElementById('fp-form');
document.getElementById('fp-neu').addEventListener('click', () => {
    form.reset();
    form.elements.id.value = '';
    form.classList.toggle('offen');
});
document.getElementById('fp-abbrechen').addEventListener('click', () => form.classList.remove('offen'));

form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const daten = {
        action:             form.elements.id.value ? 'update' : 'create',
        id:                 form.elements.id.value,
        anlass_key:         form.elements.anlass_key.value,
        zieldatum:          form.elements.zieldatum.value,
        zustaendig_user_id: form.elements.zustaendig_user_id.value,
        frequenz_tage:      form.elements.frequenz_tage.value,
        ende:               form.elements.ende.value,
    };
    fpPost(daten).then(d => {
        if (d.ok) { location.reload(); } else { fpMsg('⚠️ ' + (d.message || 'Fehler'), false); }
    }).catch(() => fpMsg('⚠️ Netzwerkfehler', false));
});

document.querySelectorAll('.fp-edit').forEach(btn => btn.addEventListener('click', () => {
    form.elements.id.value                 = btn.dataset.id;
    form.elements.anlass_key.value         = btn.dataset.anlass;
    form.elements.zieldatum.value          = btn.dataset.datum;
    form.elements.zustaendig_user_id.value = btn.dataset.zustaendig !== '0' ? btn.dataset.zustaendig : '';
    form.elements.frequenz_tage.value      = btn.dataset.frequenz !== '0' ? btn.dataset.frequenz : '';
    form.elements.ende.value               = btn.dataset.ende;
    form.classList.add('offen');
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}));

document.querySelectorAll('.fp-erledigt').forEach(btn => btn.addEventListener('click', () => {
    fpPost({ action: 'erledigt', id: btn.dataset.id }).then(d => {
        if (d.ok && d.message) { alert(d.message); }
        if (d.ok) { location.reload(); } else { fpMsg('⚠️ ' + (d.message || 'Fehler'), false); }
    }).catch(() => fpMsg('⚠️ Netzwerkfehler', false));
}));

document.querySelectorAll('.fp-loeschen').forEach(btn => btn.addEventListener('click', () => {
    if (!confirm('Eintrag wirklich löschen?')) { return; }
    fpPost({ action: 'loeschen', id: btn.dataset.id }).then(d => {
        if (d.ok) { location.reload(); } else { fpMsg('⚠️ ' + (d.message || 'Fehler'), false); }
    }).catch(() => fpMsg('⚠️ Netzwerkfehler', false));
}));

// Merkfeld: speichert beim Verlassen des Feldes (bestehender Endpoint)
document.getElementById('fp-merkfeld').addEventListener('blur', (ev) => {
    const body = new URLSearchParams();
    body.set('csrf_token', csrf);
    body.set('merkfeld', ev.target.value);
    fetch('api/social_merkfeld.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body });
});

// Burger-Menü (wie alle anderen Orga-Seiten)
const burgerBtn      = document.getElementById('burger-btn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('active');
    });
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
    });
}
</script>
</body>
</html>
