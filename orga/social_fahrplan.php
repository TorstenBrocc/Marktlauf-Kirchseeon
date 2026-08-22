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
    'SELECT f.*, u.name AS zustaendig_name,
            p.llm_text_social AS post_social, p.geprueft_am AS post_geprueft, p.status AS post_status
       FROM social_fahrplan f
  LEFT JOIN users u ON u.id = f.zustaendig_user_id
  LEFT JOIN post_race_contents p ON p.id = f.post_id
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

// Monochrome Inline-SVG-Icons für die Zeilen-Aktionen (erben currentColor über .fp-icon).
$svg = static fn (string $inner): string =>
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
$icons = [
    'edit'    => $svg('<path d="M4 20h4L18.5 9.5a2.12 2.12 0 0 0-3-3L5 17z"/><path d="M13.5 6.5l4 4"/>'),
    'check'   => $svg('<path d="M5 13l4 4L19 7"/>'),
    'trash'   => $svg('<path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="M6 7l1 13h10l1-13"/>'),
    'calendar'=> $svg('<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/>'),
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Social-Pipeline | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Zielstil Helfer-Draht: hd-card/hd-table (orga.css) + abgetoentes Gruen fuer Aktionen */
        /* Actions als reine Icon-Buttons */
        .fp-actions { display: flex; gap: 0.35rem; align-items: center; }
        .fp-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.9rem; height: 1.9rem; border: 1px solid var(--border); border-radius: 6px;
            background: var(--white); color: var(--text-light); cursor: pointer; font-size: 0.95rem;
            text-decoration: none; font-family: inherit; line-height: 1; padding: 0;
        }
        .fp-icon:hover { border-color: var(--primary-dark); color: var(--primary-dark); background: var(--bg); }
        .fp-icon:focus-visible { outline: 2px solid var(--primary-dark); outline-offset: 1px; }
        .fp-icon svg { width: 15px; height: 15px; display: block; }
        /* Termin (Kalender) bewusst vom Post-Stift abgesetzt */
        .fp-icon.fp-termin { margin-left: 0.35rem; }
        /* Thema öffnet einen neuen Entwurf von Grund auf */
        .fp-thema-link { color: var(--primary-dark); text-decoration: none; font-weight: 600; }
        .fp-thema-link:hover { text-decoration: underline; }
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
            <h1>Social-Pipeline</h1>
            <p class="content-subtitle">Terminierter Contentplan — Thema anklicken für einen neuen Entwurf · ✎ öffnet den gespeicherten Stand · 📅 ändert den Termin</p>
        </header>

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
                    // Post-Zustand anhaengen (ab Schnitt 2 verknuepft)
                    if ($e['status'] !== 'erledigt' && $e['post_id']) {
                        if ($e['post_status'] === 'approved') { $badge[1] .= ' · freigegeben'; }
                        elseif ($e['post_geprueft'])          { $badge[1] .= ' · geprüft'; }
                        elseif (trim((string) $e['post_social']) !== '') { $badge[1] .= ' · Entwurf'; }
                    }
                ?>
                <tr>
                    <td><?= $e['zieldatum'] ? htmlspecialchars(date('d.m.Y', strtotime($e['zieldatum']))) : '—' ?></td>
                    <td>
                        <a class="fp-thema-link" href="social_post.php?fahrplan=<?= (int) $e['id'] ?>&amp;neu=1"
                           title="Öffnen – neuer Entwurf von Grund auf"><?= htmlspecialchars($themaLabel) ?></a>
                        <?php if ($e['frequenz_tage']): ?>
                        <span class="fp-wiederkehr">↻ alle <?= (int) $e['frequenz_tage'] ?> Tage<?= $e['ende'] ? ' bis ' . htmlspecialchars(date('d.m.', strtotime($e['ende']))) : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e['zustaendig_name'] ? htmlspecialchars($e['zustaendig_name']) : '<span style="color:var(--text-light)">—</span>' ?></td>
                    <td><span class="fp-badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                    <td class="fp-actions">
                        <a class="fp-icon fp-bearbeiten" href="social_post.php?fahrplan=<?= (int) $e['id'] ?>"
                           title="Bearbeiten – zuletzt gespeicherter Entwurf" aria-label="Gespeicherten Entwurf bearbeiten"><?= $icons['edit'] ?></a>
                        <?php if ($e['status'] === 'offen'): ?>
                        <button type="button" class="fp-icon fp-erledigt" data-id="<?= (int) $e['id'] ?>"
                            title="Als erledigt markieren" aria-label="Als erledigt markieren"><?= $icons['check'] ?></button>
                        <?php endif; ?>
                        <button type="button" class="fp-icon fp-loeschen" data-id="<?= (int) $e['id'] ?>"
                            title="Eintrag löschen" aria-label="Eintrag löschen"><?= $icons['trash'] ?></button>
                        <button type="button" class="fp-icon fp-termin"
                            title="Termin &amp; Planung dieser Zeile (Datum, Anlass, Zuständig)"
                            aria-label="Termin bearbeiten"
                            data-id="<?= (int) $e['id'] ?>"
                            data-anlass="<?= htmlspecialchars($e['anlass_key']) ?>"
                            data-datum="<?= htmlspecialchars((string) $e['zieldatum']) ?>"
                            data-zustaendig="<?= (int) $e['zustaendig_user_id'] ?>"
                            data-frequenz="<?= (int) $e['frequenz_tage'] ?>"
                            data-ende="<?= htmlspecialchars((string) $e['ende']) ?>"><?= $icons['calendar'] ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Werkzeuge & Notizen — bewusst unten, nicht im Kopf (Inhaber 2026-08-14) -->
        <div class="hd-card fp-notiz">
            <h2 style="font-size:0.95rem;margin:0 0 0.7rem">Werkzeuge &amp; Notizen</h2>
            <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
                <a class="btn btn-small" style="background:#1877F2;color:#fff;white-space:nowrap"
                   href="https://business.facebook.com/latest/home?nav_ref=bm_home_redirect&amp;asset_id=1236742862857199"
                   target="_blank" rel="noopener noreferrer">Meta Business Account öffnen ↗</a>
                <?php if ($stravaUrl): ?>
                <a class="btn-brand btn-brand-strava" style="white-space:nowrap"
                   href="<?= htmlspecialchars($stravaUrl) ?>" target="_blank" rel="noopener">Strava öffnen</a>
                <?php endif; ?>
                <input type="text" id="fp-merkfeld" value="<?= htmlspecialchars($merkfeld) ?>" style="flex:1 1 260px"
                       placeholder="Arbeitsnotiz — keine Zugangsdaten (die gehören in Einstellungen → Meta Business)">
            </div>
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

document.querySelectorAll('.fp-termin').forEach(btn => btn.addEventListener('click', () => {
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
