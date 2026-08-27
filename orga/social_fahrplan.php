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
require_once __DIR__ . '/../src/social_insights.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();
$pdo     = getDbConnection();

$anlaesse = socialAnlaesse();
$nutzer   = orgaUserListe($pdo);

// Werkzeug-Links (Strava/Meta) + Zugangsdaten-Hinweise je Button — Cockpit-Muster.
$stravaUrl       = '';
$metaBusinessUrl = '';
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('strava_url', 'meta_business_url')");
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if ($k === 'strava_url')        { $stravaUrl       = (string) ($v ?? ''); }
        if ($k === 'meta_business_url') { $metaBusinessUrl = (string) ($v ?? ''); }
    }
} catch (PDOException $e) {
    // Einstellungen evtl. noch leer
}

// Zugangsdaten-Hinweise je Werkzeug-Button — NUR für Admins (Klartext bleibt DB-intern).
$linkHinweise = [];
if ($isAdmin) {
    try {
        $hinweisStmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('strava_hinweis', 'meta_business_hinweis')");
        foreach ($hinweisStmt as $row) {
            $linkHinweise[$row['key']] = $row['value'];
        }
    } catch (PDOException $e) {
        // Tabelle evtl. noch nicht da
    }
}

/**
 * ⓘ-Button + aufklappbare, kopierbare Zugangsdaten-Notiz (identisch zum Cockpit, index.php).
 * Leerer String, wenn kein Admin oder kein Hinweis hinterlegt.
 */
$renderHinweis = function (string $key) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id      = 'hint-' . $key;
    $anzRows = min(6, max(2, substr_count($text, "\n") + 1));
    return '<button type="button" class="qc-info" aria-expanded="false" aria-controls="' . $id . '" onclick="toggleHint(this)" title="' . htmlspecialchars($text) . '">&#9432;</button>'
        . '<div class="qc-note" id="' . $id . '" hidden>'
        . '<textarea class="qc-note-text" readonly rows="' . $anzRows . '" onclick="this.select()">' . htmlspecialchars($text) . '</textarea>'
        . '<div class="qc-note-actions">'
        . '<button type="button" class="qc-copy" onclick="copyHint(this)">Kopieren</button>'
        . '<a class="qc-edit" href="einstellungen.php#link-' . htmlspecialchars($key) . '">Bearbeiten &rarr;</a>'
        . '</div></div>';
};

// Ansicht-Umschalter: Planung (Contentplan/Versand) vs. Auswertung (Insights-Rücklauf).
$ansicht = $_GET['ansicht'] ?? 'planung';
if (!in_array($ansicht, ['planung', 'auswertung'], true)) {
    $ansicht = 'planung';
}

// Auswertung liest nur — den Datenzug erst holen, wenn die Ansicht ihn braucht.
$insightsPosts = $ansicht === 'auswertung' ? socialInsightsPosts($pdo) : [];

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
        /* Thema öffnet den gespeicherten Entwurf */
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
        /* Zuständig-Inline-Dropdown je Zeile */
        .fp-zust-select {
            font-family: inherit; font-size: 0.85rem; padding: 0.25rem 0.4rem; max-width: 100%;
            border: 1px solid var(--border); border-radius: 6px; background: var(--white); color: var(--text);
        }
        /* Plattformen-Buttons nebeneinander (statt gestapelt wie im Cockpit) */
        .pf-links { display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-start; }
        .pf-links li { border-bottom: none; padding: 0; }
        .pf-links .qc-note { min-width: 240px; }
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
        /* --- Ansicht-Umschalter (Planung | Auswertung) --- */
        .sp-tabs { display: flex; gap: 0.25rem; margin: 0 0 1rem; border-bottom: 1px solid var(--border); }
        .sp-tabs a {
            padding: 0.5rem 1.05rem; font-size: 0.9rem; text-decoration: none; color: var(--text-light);
            border: 1px solid transparent; border-bottom: none; border-radius: 8px 8px 0 0; margin-bottom: -1px;
        }
        .sp-tabs a:hover { color: var(--primary-dark); }
        .sp-tabs a.aktiv {
            color: var(--primary-dark); font-weight: 600; background: var(--white);
            border-color: var(--border); border-bottom-color: var(--white);
        }
        /* --- Kennzahlen-Kopf (B) --- */
        .sp-kpi { display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
        .sp-kpi-item { flex: 1 1 150px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.7rem 0.9rem; }
        .sp-kpi-val { display: block; font-size: 1.4rem; font-weight: 700; color: var(--primary-dark); line-height: 1.2; word-break: break-word; }
        .sp-kpi-lbl { display: block; font-size: 0.78rem; color: var(--text-light); margin-top: 0.25rem; }
        /* --- Auswertungs-Tabelle --- */
        .sp-table-wrap { overflow-x: auto; }
        .sp-auswertung th.sp-sortable { cursor: pointer; white-space: nowrap; user-select: none; }
        .sp-auswertung th.sp-asc::after  { content: ' \25B2'; font-size: 0.7em; }
        .sp-auswertung th.sp-desc::after { content: ' \25BC'; font-size: 0.7em; }
        .sp-auswertung td { vertical-align: middle; }
        .sp-links a { color: var(--primary-dark); text-decoration: none; margin-right: 0.45rem; font-size: 0.85rem; white-space: nowrap; }
        .sp-links a:hover { text-decoration: underline; }
        .sp-none { color: var(--text-light); }
        .sp-empty { color: var(--text-light); font-size: 0.9rem; margin: 0.4rem 0 0; }
        .sp-hint { color: var(--text-light); font-size: 0.78rem; margin: 0.8rem 0 0; }
        @media (max-width: 720px) {
            .sp-auswertung tr { display: block; border-bottom: 1px solid var(--border); padding: 0.5rem 0; }
            .sp-auswertung td { display: flex; justify-content: space-between; gap: 1rem; border: none; padding: 0.2rem 0.4rem; text-align: right; }
            .sp-auswertung td::before { content: attr(data-label); color: var(--text-light); font-size: 0.78rem; font-weight: 600; text-align: left; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'social_fahrplan'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Social-Pipeline</h1>
            <?php if ($ansicht === 'auswertung'): ?>
            <p class="content-subtitle">Auswertung — was die versendeten Posts an Reichweite und Likes zurückgemeldet haben (make.com-Rücklauf)</p>
            <?php else: ?>
            <p class="content-subtitle">Terminierter Contentplan — Thema anklicken öffnet den gespeicherten Entwurf · 📅 ändert den Termin</p>
            <?php endif; ?>
        </header>

        <nav class="sp-tabs" aria-label="Ansicht wechseln">
            <a href="?ansicht=planung"<?= $ansicht === 'planung' ? ' class="aktiv"' : '' ?>>Planung</a>
            <a href="?ansicht=auswertung"<?= $ansicht === 'auswertung' ? ' class="aktiv"' : '' ?>>Auswertung</a>
        </nav>

<?php if ($ansicht === 'auswertung'):
    $kpi = socialInsightsKennzahlen($insightsPosts);
?>
        <div class="hd-card">
            <div class="sp-kpi">
                <div class="sp-kpi-item">
                    <span class="sp-kpi-val"><?= number_format($kpi['gesamt_reichweite'], 0, ',', '.') ?></span>
                    <span class="sp-kpi-lbl">Reichweite gesamt (IG)</span>
                </div>
                <div class="sp-kpi-item">
                    <span class="sp-kpi-val"><?= $kpi['schnitt_likes'] !== null ? htmlspecialchars(number_format($kpi['schnitt_likes'], 1, ',', '.')) : '—' ?></span>
                    <span class="sp-kpi-lbl">Ø Likes pro Post</span>
                </div>
                <div class="sp-kpi-item">
                    <?php if ($kpi['bester']): $bd = $anlaesse[$kpi['bester']['anlass_key']] ?? null; ?>
                    <span class="sp-kpi-val"><?= htmlspecialchars($bd ? $bd['ui'] : (string) $kpi['bester']['anlass_key']) ?></span>
                    <span class="sp-kpi-lbl">Bester Post<?= $kpi['bester']['ig_reichweite'] !== null ? ' · ' . (int) $kpi['bester']['ig_reichweite'] . ' Reichweite' : '' ?></span>
                    <?php else: ?>
                    <span class="sp-kpi-val">—</span>
                    <span class="sp-kpi-lbl">Bester Post</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$insightsPosts): ?>
            <p class="sp-empty">Noch keine Insights — sie erscheinen, sobald versendete Posts Reichweite/Likes zurückmelden (nächster Sammler-Lauf).</p>
            <?php else: ?>
            <div class="sp-table-wrap">
            <table class="hd-table sp-auswertung" id="sp-tbl">
                <thead>
                    <tr>
                        <th class="sp-sortable" data-sort="text">Thema</th>
                        <th class="sp-sortable" data-sort="num">Datum</th>
                        <th class="sp-sortable" data-sort="num">IG Reichw.</th>
                        <th class="sp-sortable" data-sort="num">IG Likes</th>
                        <th class="sp-sortable" data-sort="num">FB Likes</th>
                        <th>Stand</th>
                        <th>Links</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($insightsPosts as $p):
                    $pdef  = $anlaesse[$p['anlass_key']] ?? null;
                    $thema = $pdef ? $pdef['ui'] : ((string) $p['anlass_key'] !== '' ? (string) $p['anlass_key'] : '—');
                    $ts    = $p['gesendet_am'] ? strtotime((string) $p['gesendet_am']) : 0;
                ?>
                <tr>
                    <td data-label="Thema" data-v="<?= htmlspecialchars($thema) ?>"><strong><?= htmlspecialchars($thema) ?></strong></td>
                    <td data-label="Datum" data-v="<?= $ts ?>"><?= $ts ? htmlspecialchars(date('d.m.Y', $ts)) : '—' ?></td>
                    <td data-label="IG Reichw." data-v="<?= $p['ig_reichweite'] !== null ? (int) $p['ig_reichweite'] : -1 ?>"><?= $p['ig_reichweite'] !== null ? (int) $p['ig_reichweite'] : '<span class="sp-none">—</span>' ?></td>
                    <td data-label="IG Likes" data-v="<?= $p['ig_likes'] !== null ? (int) $p['ig_likes'] : -1 ?>"><?= $p['ig_likes'] !== null ? (int) $p['ig_likes'] : '<span class="sp-none">—</span>' ?></td>
                    <td data-label="FB Likes" data-v="<?= $p['fb_likes'] !== null ? (int) $p['fb_likes'] : -1 ?>"><?= $p['fb_likes'] !== null ? (int) $p['fb_likes'] : '<span class="sp-none">—</span>' ?></td>
                    <td data-label="Stand"><?= $p['versand_insights_am'] ? htmlspecialchars(date('d.m. H:i', strtotime((string) $p['versand_insights_am']))) : '<span class="sp-none">ausstehend</span>' ?></td>
                    <td data-label="Links" class="sp-links">
                        <?php if (!empty($p['ig_permalink'])): ?><a href="<?= htmlspecialchars((string) $p['ig_permalink']) ?>" target="_blank" rel="noopener">IG&#8599;</a><?php endif; ?>
                        <?php if (!empty($p['fb_permalink'])): ?><a href="<?= htmlspecialchars((string) $p['fb_permalink']) ?>" target="_blank" rel="noopener">FB&#8599;</a><?php endif; ?>
                        <?php if (empty($p['ig_permalink']) && empty($p['fb_permalink'])): ?><span class="sp-none">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="sp-hint">Klick auf eine Spaltenüberschrift sortiert. FB-Reichweite wird bewusst nicht erhoben (nur Likes/Reaktionen).</p>
            <?php endif; ?>
        </div>
<?php else: ?>
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
                        <a class="fp-thema-link" href="social_post.php?fahrplan=<?= (int) $e['id'] ?>"
                           title="Öffnen – gespeicherter Entwurf"><?= htmlspecialchars($themaLabel) ?></a>
                        <?php if ($e['frequenz_tage']): ?>
                        <span class="fp-wiederkehr">↻ alle <?= (int) $e['frequenz_tage'] ?> Tage<?= $e['ende'] ? ' bis ' . htmlspecialchars(date('d.m.', strtotime($e['ende']))) : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <select class="fp-zust-select" data-id="<?= (int) $e['id'] ?>" aria-label="Zuständig ändern">
                            <option value=""<?= !$e['zustaendig_user_id'] ? ' selected' : '' ?>>— niemand —</option>
                            <?php foreach ($nutzer as $n): ?>
                            <option value="<?= (int) $n['id'] ?>"<?= (int) $e['zustaendig_user_id'] === (int) $n['id'] ? ' selected' : '' ?>><?= htmlspecialchars($n['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span class="fp-badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                    <td class="fp-actions">
                        <button type="button" class="fp-icon fp-termin"
                            title="Termin &amp; Planung dieser Zeile (Datum, Anlass, Zuständig)"
                            aria-label="Termin bearbeiten"
                            data-id="<?= (int) $e['id'] ?>"
                            data-anlass="<?= htmlspecialchars($e['anlass_key']) ?>"
                            data-datum="<?= htmlspecialchars((string) $e['zieldatum']) ?>"
                            data-zustaendig="<?= (int) $e['zustaendig_user_id'] ?>"
                            data-frequenz="<?= (int) $e['frequenz_tage'] ?>"
                            data-ende="<?= htmlspecialchars((string) $e['ende']) ?>"><?= $icons['calendar'] ?></button>
                        <?php if ($e['status'] === 'offen'): ?>
                        <button type="button" class="fp-icon fp-erledigt" data-id="<?= (int) $e['id'] ?>"
                            title="Als erledigt markieren" aria-label="Als erledigt markieren"><?= $icons['check'] ?></button>
                        <?php endif; ?>
                        <button type="button" class="fp-icon fp-loeschen" data-id="<?= (int) $e['id'] ?>"
                            title="Eintrag löschen" aria-label="Eintrag löschen"><?= $icons['trash'] ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Plattformen — Schnellzugriff im Cockpit-Muster (ⓘ = Zugangsdaten je Button, nur Admin) -->
        <div class="hd-card fp-notiz">
            <h2 style="font-size:0.95rem;margin:0 0 0.7rem">Plattformen</h2>
            <ul class="quick-links pf-links">
                <li><a href="<?= htmlspecialchars($metaBusinessUrl ?: 'https://business.facebook.com/latest/home?nav_ref=bm_home_redirect&asset_id=1236742862857199') ?>"
                       target="_blank" rel="noopener" class="btn-brand btn-brand-meta">Meta Business</a><?= $renderHinweis('meta_business_hinweis') ?></li>
                <?php if ($stravaUrl): ?>
                <li><a href="<?= htmlspecialchars($stravaUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-strava">Strava</a><?= $renderHinweis('strava_hinweis') ?></li>
                <?php endif; ?>
            </ul>
        </div>
<?php endif; ?>
    </main>
</div>

<script>
const csrf = <?= json_encode($csrf) ?>;

<?php if ($ansicht === 'planung'): ?>
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

// Zuständigkeit direkt per Inline-Dropdown setzen (dedizierte Action, kein Formular nötig)
document.querySelectorAll('.fp-zust-select').forEach(sel => sel.addEventListener('change', () => {
    fpPost({ action: 'zustaendig', id: sel.dataset.id, zustaendig_user_id: sel.value }).then(d => {
        if (d.ok) { fpMsg('Zuständigkeit gespeichert.', true); } else { fpMsg('⚠️ ' + (d.message || 'Fehler'), false); }
    }).catch(() => fpMsg('⚠️ Netzwerkfehler', false));
}));

// Zugangsdaten-Hinweis je Werkzeug-Button: aufklappen + kopieren (identisch zum Cockpit)
function toggleHint(btn) {
    const note = document.getElementById(btn.getAttribute('aria-controls'));
    if (!note) { return; }
    const willOpen = note.hasAttribute('hidden');
    if (willOpen) { note.removeAttribute('hidden'); } else { note.setAttribute('hidden', ''); }
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
}
function copyHint(btn) {
    const ta = btn.closest('.qc-note').querySelector('.qc-note-text');
    if (!ta) { return; }
    ta.select();
    const done = () => {
        const label = btn.textContent;
        btn.textContent = 'Kopiert ✓';
        setTimeout(() => { btn.textContent = label; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ta.value).then(done).catch(() => { document.execCommand('copy'); done(); });
    } else {
        document.execCommand('copy');
        done();
    }
}
<?php endif; ?>

<?php if ($ansicht === 'auswertung'): ?>
// Auswertungs-Tabelle: Client-seitiges Sortieren per Klick auf die Spaltenüberschrift.
(function () {
    const tbl = document.getElementById('sp-tbl');
    if (!tbl) { return; }
    const tbody = tbl.tBodies[0];
    const heads = Array.from(tbl.querySelectorAll('th.sp-sortable'));
    let sortIdx = -1, dir = 1;
    heads.forEach((th, idx) => th.addEventListener('click', () => {
        const type = th.dataset.sort;
        dir = (sortIdx === idx) ? -dir : (type === 'text' ? 1 : -1);
        sortIdx = idx;
        const rows = Array.from(tbody.rows);
        rows.sort((a, b) => {
            const av = a.cells[idx].dataset.v, bv = b.cells[idx].dataset.v;
            return type === 'num'
                ? (Number(av) - Number(bv)) * dir
                : av.toLowerCase().localeCompare(bv.toLowerCase(), 'de') * dir;
        });
        rows.forEach(r => tbody.appendChild(r));
        heads.forEach(h => h.classList.remove('sp-asc', 'sp-desc'));
        th.classList.add(dir === 1 ? 'sp-asc' : 'sp-desc');
    }));
})();
<?php endif; ?>

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
