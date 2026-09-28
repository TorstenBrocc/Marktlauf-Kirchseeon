<?php
/**
 * Orga Dashboard Startseite
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/offene_todos.php';

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
$dashboardTitles = [];
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
    // Gehört der Abschnitt zu einer Gruppe (dritte Sidebar-Ebene), wird sie in die
    // Überschrift gezogen: „DATEN" allein sagt auf dem Cockpit nichts, „SPONSOREN · DATEN"
    // schon. Ohne Gruppe bleibt der Abschnittsname wie bisher stehen.
    $group = $item['group'] ?? '';
    $dashboardTitles[$section] = $group !== '' ? $group . ' · ' . $section : $section;
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
    // Optionales eigenes Kachel-Ziel: manche Module wollen im Cockpit woanders
    // hin als in der Sidebar (siehe href_tile in _nav.php).
    $tileHref = $item['href_tile'] ?? $item['href'];
    echo '<a class="card card-tile signal-' . htmlspecialchars($kpi['signal'] ?? 'neutral') . '" href="' . htmlspecialchars($tileHref) . '">'
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
$orgaOffen = [];
$orgaErledigt = [];
$orgaUsers = [];
$todos = ['gesamt' => 0];
try {
    $meineStmt = $pdo->prepare("
        SELECT a.id, a.titel, a.faellig_am, a.status, a.kontext_typ, a.kontext_id, s.firma
        FROM aufgaben a
        LEFT JOIN sponsors s ON a.kontext_typ = 'sponsor' AND s.id = a.kontext_id
        WHERE a.status != 'erledigt' AND a.verantwortlich_user_id = :user_id
        ORDER BY a.faellig_am ASC, a.created_at DESC
    ");
    $meineStmt->execute(['user_id' => $user['id']]);
    $meineAufgaben = $meineStmt->fetchAll();

    // Nur kontextlose Aufgaben: seit Migration 063 liegen auch die Sponsor-Aufgaben in
    // dieser Tabelle, die haben aber ihre eigene Sicht (orga/offene_todos.php + Sponsor-Maske).
    // Ohne den Filter würde diese Verwaltungsliste mit Sponsoring-Einträgen volllaufen.
    // „Meine offenen Aufgaben" oben filtert bewusst NICHT — was mir zugewiesen ist, gehört
    // in meine persönliche Liste, egal woran es hängt.
    $orgaOffenStmt = $pdo->query("
        SELECT a.*, u.name AS verantwortlich_name
        FROM aufgaben a
        LEFT JOIN users u ON a.verantwortlich_user_id = u.id
        WHERE a.kontext_typ IS NULL AND a.status != 'erledigt'
        ORDER BY
            CASE a.status WHEN 'offen' THEN 1 WHEN 'in_arbeit' THEN 2 ELSE 3 END,
            a.faellig_am ASC,
            a.created_at DESC
    ");
    $orgaOffen = $orgaOffenStmt->fetchAll();

    // Erledigte verschwinden aus der offenen Liste (Inhaber-Entscheid Runde 3), bleiben aber
    // 30 Tage im Cockpit einsehbar (Klapp-Bereich „✓ N erledigt", Task 6) — u. a. damit
    // „Rückgängig" nach einem Klick funktioniert. Älteres bleibt in der DB, taucht hier nicht mehr auf.
    $orgaErledigtStmt = $pdo->query("
        SELECT a.*, u.name AS verantwortlich_name
        FROM aufgaben a
        LEFT JOIN users u ON a.verantwortlich_user_id = u.id
        WHERE a.kontext_typ IS NULL AND a.status = 'erledigt'
          AND a.updated_at >= NOW() - INTERVAL 30 DAY
        ORDER BY a.updated_at DESC
    ");
    $orgaErledigt = $orgaErledigtStmt->fetchAll();

    $orgaUsers = orgaUserListe($pdo);

    $todos = offeneTodosAlle($pdo);
} catch (PDOException $e) {
    // Table may not exist yet
}

// Zähler für die drei Reiter der Aufgaben-Karte (Meine · Sponsoring · Orga).
$anzMeine = count($meineAufgaben);
$anzSponsoring = (int) ($todos['gesamt'] ?? 0);
$anzOrga = count($orgaOffen);

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

$stravaUrl = '';
try {
    $stravaStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $stravaStmt->execute(['key' => 'strava_url']);
    $stravaUrl = $stravaStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table may not exist yet
}

$metaBusinessUrl = '';
try {
    $metaBusinessStmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :key');
    $metaBusinessStmt->execute(['key' => 'meta_business_url']);
    $metaBusinessUrl = $metaBusinessStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table may not exist yet
}

// Zugangsdaten-Hinweise je Button — NUR für Admins, Klartext bleibt DB-intern.
$linkHinweise = [];
if ($isAdmin) {
    try {
        $hinweisStmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('raceresult_hinweis','trello_hinweis','strava_hinweis','meta_business_hinweis')");
        foreach ($hinweisStmt as $row) {
            $linkHinweise[$row['key']] = $row['value'];
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

/**
 * Render-Helfer: ⓘ-Button für einen Schnellzugriff-Link (aufklappt die zugehörige Notiz).
 * Gibt leeren String zurück, wenn kein Admin oder kein Hinweis hinterlegt ist.
 */
$renderHinweisButton = function (string $key) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id = 'hint-' . $key;
    return '<button type="button" class="qc-info" aria-expanded="false" aria-controls="' . $id . '" onclick="toggleHint(this)" title="' . htmlspecialchars($text) . '">&#9432;</button>';
};

/**
 * Render-Helfer: aufklappbare, kopierbare Notiz zu einem Schnellzugriff-Link (Gegenstück
 * zu $renderHinweisButton — liegt separat, damit die Leiste die Panels gesammelt unter
 * sich zeigen kann statt je Button eingestreut).
 */
$renderHinweisNote = function (string $key) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id = 'hint-' . $key;
    $rows = min(6, max(2, substr_count($text, "\n") + 1));
    return '<div class="qc-note" id="' . $id . '" hidden>'
        . '<textarea class="qc-note-text" readonly rows="' . $rows . '" onclick="this.select()">' . htmlspecialchars($text) . '</textarea>'
        . '<div class="qc-note-actions">'
        . '<button type="button" class="qc-copy" onclick="copyHint(this)">Kopieren</button>'
        . '<a class="qc-edit" href="einstellungen.php#link-' . htmlspecialchars($key) . '">Bearbeiten &rarr;</a>'
        . '</div></div>';
};

// Schnellzugriff-Leiste: Reihenfolge und Bedingungen wie bisher (Inhaber-Entscheid Runde 3),
// Helfer-Anmeldung steht als eigener, letzter Button außerhalb dieser Liste (grüner Rahmen,
// kein Hinweis-Panel).
$quickLinks = [
    [
        'href'  => 'https://www.raceresult.com/de-de/account/index',
        'label' => 'Race Result',
        'icon'  => 'raceresult.png',
        'hint'  => 'raceresult_hinweis',
    ],
    [
        'href'  => 'https://github.com/TorstenBrocc/Marktlauf-Kirchseeon',
        'label' => 'GitHub',
        'icon'  => 'github.svg',
        'hint'  => null,
    ],
];
if ($trelloBoardUrl) {
    $quickLinks[] = [
        'href'  => $trelloBoardUrl,
        'label' => 'Trello',
        'icon'  => 'trello.svg',
        'hint'  => 'trello_hinweis',
    ];
}
if ($stravaUrl) {
    $quickLinks[] = [
        'href'  => $stravaUrl,
        'label' => 'Strava',
        'icon'  => 'strava.png',
        'hint'  => 'strava_hinweis',
    ];
}
$quickLinks[] = [
    'href'  => $metaBusinessUrl ?: 'https://business.facebook.com/latest/home?nav_ref=bm_home_redirect&asset_id=1236742862857199',
    'label' => 'Meta Business',
    'icon'  => 'meta.svg',
    'hint'  => 'meta_business_hinweis',
];

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
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
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

            <nav class="quick-bar" aria-label="Schnellzugriff">
                <ul class="quick-bar-liste">
                    <?php foreach ($quickLinks as $link): ?>
                    <li>
                        <a class="quick-btn" href="<?= htmlspecialchars($link['href']) ?>" target="_blank" rel="noopener">
                            <img src="../assets/images/brands/<?= htmlspecialchars($link['icon']) ?>" alt="" width="16" height="16">
                            <?= htmlspecialchars($link['label']) ?>
                            <span aria-hidden="true">&#8599;</span>
                            <span class="sr-only">(öffnet neuen Tab)</span>
                        </a>
                        <?php if ($link['hint']): ?><?= $renderHinweisButton($link['hint']) ?><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                    <li class="quick-bar-trenner" aria-hidden="true"></li>
                    <li>
                        <a class="quick-btn quick-btn-primaer" href="../helfer-anmeldung.php" target="_blank" rel="noopener">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
                            Helfer-Anmeldung
                            <span aria-hidden="true">&#8599;</span>
                            <span class="sr-only">(öffnet neuen Tab)</span>
                        </a>
                    </li>
                </ul>
                <div class="quick-bar-notes">
                    <?php foreach ($quickLinks as $link): ?>
                        <?php if ($link['hint']): ?><?= $renderHinweisNote($link['hint']) ?><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </nav>

            <section class="dashboard-group cockpit-aufgaben">
                <div class="dashboard-grid">
                    <article class="card aufgaben-karte">
                        <div class="aufgaben-kopf">
                            <h2>Aufgaben</h2>
                            <div class="tabs" hidden>
                                <button type="button" class="tab" id="tab-meine" data-tab="meine">Meine <span class="tab-zahl">(<?= $anzMeine ?>)</span></button>
                                <button type="button" class="tab" id="tab-sponsoring" data-tab="sponsoring">Sponsoring <span class="tab-zahl">(<?= $anzSponsoring ?>)</span></button>
                                <button type="button" class="tab" id="tab-orga" data-tab="orga">Orga <span class="tab-zahl">(<?= $anzOrga ?>)</span></button>
                            </div>
                        </div>

                        <section class="aufgaben-panel" id="panel-meine" data-panel="meine">
                            <h3 class="aufgaben-panel-titel">Meine (<?= $anzMeine ?>)</h3>
                            <?php if (empty($meineAufgaben)): ?>
                            <p class="aufgaben-leer">Nichts offen.</p>
                            <?php else: ?>
                            <ul>
                                <?php foreach ($meineAufgaben as $ma): ?>
                                <li><?= htmlspecialchars($ma['titel']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </section>

                        <section class="aufgaben-panel" id="panel-sponsoring" data-panel="sponsoring">
                            <h3 class="aufgaben-panel-titel">Sponsoring (<?= $anzSponsoring ?>)</h3>
                            <?php if ($anzSponsoring === 0): ?>
                            <p class="aufgaben-leer">Nichts offen.</p>
                            <?php else: ?>
                            <ul>
                                <?php foreach ($todos as $gruppe => $eintraege): ?>
                                    <?php if ($gruppe === 'gesamt' || !is_array($eintraege)) { continue; } ?>
                                    <?php foreach ($eintraege as $eintrag): ?>
                                    <li><?= htmlspecialchars($eintrag['firma'] ?? '') ?></li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </section>

                        <section class="aufgaben-panel" id="panel-orga" data-panel="orga">
                            <h3 class="aufgaben-panel-titel">Orga (<?= $anzOrga ?>)</h3>
                            <?php if (empty($orgaOffen)): ?>
                            <p class="aufgaben-leer">Nichts offen.</p>
                            <?php else: ?>
                            <ul>
                                <?php foreach ($orgaOffen as $oa): ?>
                                <li><?= htmlspecialchars($oa['titel']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </section>
                    </article>
                </div>
            </section>

            <?php foreach ($dashboardGroups as $section => $tiles): ?>
                <?php if ($section === 'ADMIN') { continue; } // ADMIN nicht aufs Dashboard ?>
                <section class="dashboard-group">
                    <?php if ($section !== ''): ?>
                    <h2 class="dashboard-group-title"><?= htmlspecialchars($dashboardTitles[$section] ?? $section) ?></h2>
                    <?php endif; ?>
                    <div class="dashboard-grid">
                        <?php foreach ($tiles as $tile) { $renderTile($tile); } ?>
                    </div>
                </section>
            <?php endforeach; ?>
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
