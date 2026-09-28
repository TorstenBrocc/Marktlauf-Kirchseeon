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

// Cockpit-„Rückgängig" (Inhaber-Entscheid Runde 3): nach einem Abhaken aus dem Cockpit
// hängt aufgabe_orga_crud.php ?erledigt=<id> an. Titel wird ausschließlich in der bereits
// geladenen $orgaErledigt-Liste nachgeschlagen — unbekannte/fremde IDs zeigen nichts an.
$erledigtId = 0;
$erledigtTitel = '';
if (isset($_GET['erledigt']) && ctype_digit((string) $_GET['erledigt'])) {
    $erledigtKandidat = (int) $_GET['erledigt'];
    foreach ($orgaErledigt as $oe) {
        if ((int) $oe['id'] === $erledigtKandidat) {
            $erledigtId = $erledigtKandidat;
            $erledigtTitel = (string) $oe['titel'];
            break;
        }
    }
}

/**
 * Status-Punkt-Formular für eine Aufgabenzeile (Meine + Orga, Task 5/6 — geteilte Closure,
 * Inhaber-Entscheid Runde 3: nur ein Punkt, kein Auswahlfeld). Ein Submit-Button je Zeile
 * schaltet den Status im Kreis weiter (offen → in Arbeit → erledigt → offen); Form + Farbe
 * tragen die Bedeutung, nicht nur die Farbe (WCAG 1.4.1) — aria-label/title benennen
 * Ist- und Ziel-Zustand ausdrücklich. `zurueck=cockpit` fällt im Endpunkt heute noch auf
 * den Standard-Rücksprung `../index.php` zurück (kein `todos`/`sponsor`-Wert) — harmlos,
 * bis Task 6 den Wert dort auswertet.
 */
$renderStatusPunkt = function (array $aufgabe) use ($csrfToken): string {
    $naechsterStatus = ['offen' => 'in_arbeit', 'in_arbeit' => 'erledigt', 'erledigt' => 'offen'];
    $label = ['offen' => 'offen', 'in_arbeit' => 'in Arbeit', 'erledigt' => 'erledigt'];
    $ist = (string) ($aufgabe['status'] ?? 'offen');
    if (!isset($naechsterStatus[$ist])) {
        $ist = 'offen';
    }
    $naechster = $naechsterStatus[$ist];
    $ueberfaellig = $ist !== 'erledigt'
        && !empty($aufgabe['faellig_am'])
        && (string) $aufgabe['faellig_am'] < date('Y-m-d');
    $klasse = 'status-punkt status-' . $ist . ($ueberfaellig ? ' ist-ueberfaellig' : '');
    $aufgabenTitel = (string) ($aufgabe['titel'] ?? '');
    $titel = $aufgabenTitel . ' – Status: ' . $label[$ist] . ($ueberfaellig ? ', überfällig' : '') . ' – klicken: ' . $label[$naechster];
    return '<form method="post" action="api/aufgabe_orga_crud.php" class="status-form">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">'
        . '<input type="hidden" name="action" value="set_status">'
        . '<input type="hidden" name="aufgabe_id" value="' . (int) $aufgabe['id'] . '">'
        . '<input type="hidden" name="status" value="' . htmlspecialchars($naechster) . '">'
        . '<input type="hidden" name="zurueck" value="cockpit">'
        . '<button type="submit" class="' . htmlspecialchars($klasse) . '" aria-label="' . htmlspecialchars($titel) . '" title="' . htmlspecialchars($titel) . '"></button>'
        . '</form>';
};

/** Alters-/Fristtext für eine Sponsoring-Zeile im Cockpit — Kurzform von $alter aus
 * orga/offene_todos.php, eigene Kopie: das Layout hier ist ein reiner Fließtext ohne
 * Badges, die Datenquelle bleibt dieselbe ($todos aus offeneTodosAlle()). */
$frist = static function (int $tage, string $heuteText, string $mehrText): string {
    return $tage <= 0 ? $heuteText : sprintf($mehrText, $tage);
};

/**
 * Eine Zeile im Reiter „Sponsoring" — Firma (Link, außer versand_fehler) + Grund/Frist,
 * ohne Status/Wer/Löschen (das sind Sponsoring-ToDos, keine Orga-Aufgaben). Gruppen und
 * Reihenfolge wie orga/offene_todos.php, nur die Felder unterscheiden sich je Gruppe.
 */
$renderSponsorZeile = function (string $gruppe, array $t) use ($frist): string {
    switch ($gruppe) {
        case 'bestaetigung':
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>';
            $grundHtml = htmlspecialchars($frist((int) $t['tage'], 'heute zugesagt', 'seit %d Tagen zugesagt'));
            $ueberfaellig = false;
            break;
        case 'bedingungen':
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>';
            $grundHtml = htmlspecialchars($frist((int) $t['tage'], 'seit heute', 'seit %d Tagen'));
            $ueberfaellig = false;
            break;
        case 'wiedervorlagen':
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>';
            $grundHtml = htmlspecialchars($frist((int) $t['tage'], 'heute fällig', 'seit %d Tagen überfällig'));
            $ueberfaellig = (int) $t['tage'] > 0;
            break;
        case 'versand_fehler':
            $firmaHtml = htmlspecialchars((string) $t['firma']);
            $fehlerText = (string) $t['fehler'] !== '' ? (string) $t['fehler'] : 'Versand fehlgeschlagen';
            $grundHtml = '<a href="offene_todos.php">' . htmlspecialchars($fehlerText) . '</a>';
            $ueberfaellig = false;
            break;
        case 'nie_angeschrieben':
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>';
            $grundHtml = htmlspecialchars($frist((int) $t['tage'], 'heute angelegt', 'liegt seit %d Tagen'));
            $ueberfaellig = false;
            break;
        case 'ohne_reaktion':
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>';
            $grundHtml = htmlspecialchars($frist((int) $t['tage'], 'seit heute', 'seit %d Tagen ohne Antwort'));
            $ueberfaellig = false;
            break;
        default: // sponsor_aufgaben
            // Real task at a sponsor: show what to do, not just the firm.
            $firmaHtml = '<a href="sponsor_form.php?id=' . (int) $t['sponsor_id'] . '">' . htmlspecialchars((string) $t['firma']) . '</a>'
                . ' · ' . htmlspecialchars((string) $t['titel']);
            $tage = (int) $t['tage_ueberfaellig'];
            // Negative = due in the future ($frist would wrongly say "heute fällig").
            $grundHtml = $tage < 0
                ? 'fällig ' . htmlspecialchars(date('d.m.', strtotime((string) $t['faellig_am'])))
                : htmlspecialchars($frist($tage, 'heute fällig', 'seit %d Tagen überfällig'));
            $ueberfaellig = $tage > 0;
            break;
    }
    $faelligKlasse = 'aufgabe-faellig' . ($ueberfaellig ? ' ueberfaellig' : '');
    $ueberfaelligHinweis = $ueberfaellig ? '<span class="sr-only"> (überfällig)</span>' : '';
    return '<div class="aufgabe-zeile aufgabe-zeile-sponsor">'
        . '<div class="aufgabe-titel">' . $firmaHtml . '</div>'
        . '<div class="' . $faelligKlasse . '">' . $grundHtml . $ueberfaelligHinweis . '</div>'
        . '</div>';
};

// Reihenfolge wie orga/offene_todos.php, aber nur die Gruppen, die in $todos['gesamt']
// zählen (bedingungen_beleg bleibt außen vor — „inhaltlich erledigt, nur Beleg fehlt").
$sponsoringReihenfolge = ['bestaetigung', 'bedingungen', 'wiedervorlagen', 'versand_fehler', 'nie_angeschrieben', 'ohne_reaktion', 'sponsor_aufgaben'];
$todoGruppenMeta = todoGruppenMeta();

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
$renderHinweisButton = function (string $key, string $label) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id = 'hint-' . $key;
    $bezeichnung = 'Zugangshinweis ' . $label;
    return '<button type="button" class="qc-info" aria-expanded="false" aria-controls="' . $id . '" onclick="toggleHint(this)" aria-label="' . htmlspecialchars($bezeichnung) . '" title="' . htmlspecialchars($bezeichnung) . '">&#9432;</button>';
};

/**
 * Render-Helfer: aufklappbare, kopierbare Notiz zu einem Schnellzugriff-Link (Gegenstück
 * zu $renderHinweisButton — liegt separat, damit die Leiste die Panels gesammelt unter
 * sich zeigen kann statt je Button eingestreut).
 */
$renderHinweisNote = function (string $key, string $label) use ($isAdmin, $linkHinweise): string {
    if (!$isAdmin) {
        return '';
    }
    $text = trim((string) ($linkHinweise[$key] ?? ''));
    if ($text === '') {
        return '';
    }
    $id = 'hint-' . $key;
    $rows = min(6, max(2, substr_count($text, "\n") + 1));
    $bezeichnung = 'Zugangshinweis ' . $label;
    return '<div class="qc-note" id="' . $id . '" hidden>'
        . '<textarea class="qc-note-text" readonly rows="' . $rows . '" aria-label="' . htmlspecialchars($bezeichnung) . '" onclick="this.select()">' . htmlspecialchars($text) . '</textarea>'
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
                        <?php if ($link['hint']): ?><?= $renderHinweisButton($link['hint'], $link['label']) ?><?php endif; ?>
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
                        <?php if ($link['hint']): ?><?= $renderHinweisNote($link['hint'], $link['label']) ?><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </nav>

            <?php if ($erledigtId > 0): ?>
            <div class="alert alert-success aufgabe-erledigt-hinweis" role="status" tabindex="-1">
                ✓ „<?= htmlspecialchars($erledigtTitel) ?>“ erledigt.
                <form method="post" action="api/aufgabe_orga_crud.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="aufgabe_id" value="<?= $erledigtId ?>">
                    <input type="hidden" name="status" value="offen">
                    <input type="hidden" name="zurueck" value="cockpit">
                    <button type="submit" class="link-button">Rückgängig</button>
                </form>
            </div>
            <?php endif; ?>

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
                                <?php foreach ($meineAufgaben as $ma):
                                    $faelligAm = (string) ($ma['faellig_am'] ?? '');
                                    $ueberfaellig = $faelligAm !== '' && $faelligAm < date('Y-m-d');
                                    $faelligText = $faelligAm !== '' ? 'Fällig: ' . date('d.m.Y', strtotime($faelligAm)) : '';
                                ?>
                                <div class="aufgabe-zeile">
                                    <?= $renderStatusPunkt($ma) ?>
                                    <div class="aufgabe-titel">
                                        <?= htmlspecialchars((string) $ma['titel']) ?>
                                        <?php if (($ma['kontext_typ'] ?? '') === 'sponsor' && !empty($ma['firma'])): ?>
                                            &middot; <a href="sponsor_form.php?id=<?= (int) $ma['kontext_id'] ?>"><?= htmlspecialchars((string) $ma['firma']) ?></a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aufgabe-meta">
                                        <?php if ($faelligText !== ''): ?>
                                        <span class="aufgabe-faellig<?= $ueberfaellig ? ' ueberfaellig' : '' ?>"><?= htmlspecialchars($faelligText) ?><?php if ($ueberfaellig): ?><span class="sr-only"> (überfällig)</span><?php endif; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>

                        <section class="aufgaben-panel" id="panel-sponsoring" data-panel="sponsoring">
                            <h3 class="aufgaben-panel-titel">Sponsoring (<?= $anzSponsoring ?>)</h3>
                            <?php if ($anzSponsoring === 0): ?>
                            <p class="aufgaben-leer">Nichts offen.</p>
                            <?php else: ?>
                                <?php $sponsoringRest = TODO_LISTE_MAX; ?>
                                <?php foreach ($sponsoringReihenfolge as $gruppe):
                                    if ($sponsoringRest <= 0) {
                                        break;
                                    }
                                    $liste = $gruppe === 'sponsor_aufgaben'
                                        ? array_values(array_filter($todos['sponsor_aufgaben'] ?? [], static fn (array $a): bool => !empty($a['faellig_am'])))
                                        : ($todos[$gruppe] ?? []);
                                    if (empty($liste)) {
                                        continue;
                                    }
                                ?>
                                <h4 class="todo-gruppe"><?= htmlspecialchars($todoGruppenMeta[$gruppe]['titel']) ?> (<?= count($liste) ?>)</h4>
                                <?php foreach (array_slice($liste, 0, $sponsoringRest) as $eintrag):
                                    echo $renderSponsorZeile($gruppe, $eintrag);
                                    $sponsoringRest--;
                                endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <p class="aufgaben-mehr"><a href="offene_todos.php">Alle <?= $anzSponsoring ?> ToDos &rarr;</a></p>
                        </section>

                        <section class="aufgaben-panel" id="panel-orga" data-panel="orga">
                            <h3 class="aufgaben-panel-titel">Orga (<?= $anzOrga ?>)</h3>
                            <?php if (empty($orgaOffen)): ?>
                            <p class="aufgaben-leer">Nichts offen.</p>
                            <?php else: ?>
                                <?php foreach ($orgaOffen as $oa):
                                    $faelligAm = (string) ($oa['faellig_am'] ?? '');
                                    $ueberfaellig = (string) $oa['status'] !== 'erledigt' && $faelligAm !== '' && $faelligAm < date('Y-m-d');
                                    $faelligText = $faelligAm !== '' ? 'Fällig: ' . date('d.m.Y', strtotime($faelligAm)) : '';
                                    $notizAuszug = trim((string) ($oa['notiz'] ?? ''));
                                    if (mb_strlen($notizAuszug) > 60) {
                                        $notizAuszug = mb_substr($notizAuszug, 0, 60) . '…';
                                    }
                                    $verantwortlichName = trim((string) ($oa['verantwortlich_name'] ?? ''));
                                    $initialen = '';
                                    foreach (array_slice(preg_split('/\s+/', $verantwortlichName, -1, PREG_SPLIT_NO_EMPTY), 0, 2) as $wortteil) {
                                        $initialen .= mb_strtoupper(mb_substr($wortteil, 0, 1));
                                    }
                                ?>
                                <div class="aufgabe-zeile">
                                    <?= $renderStatusPunkt($oa) ?>
                                    <div class="aufgabe-titel">
                                        <?= htmlspecialchars((string) $oa['titel']) ?>
                                        <?php if ($notizAuszug !== ''): ?>
                                        <small><?= htmlspecialchars($notizAuszug) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aufgabe-meta">
                                        <?php if ($faelligText !== ''): ?>
                                        <span class="aufgabe-faellig<?= $ueberfaellig ? ' ueberfaellig' : '' ?>"><?= htmlspecialchars($faelligText) ?><?php if ($ueberfaellig): ?><span class="sr-only"> (überfällig)</span><?php endif; ?></span>
                                        <?php endif; ?>
                                        <?php if ($verantwortlichName !== ''): ?>
                                        <span class="aufgabe-wer" aria-hidden="true" title="<?= htmlspecialchars($verantwortlichName) ?>"><?= htmlspecialchars($initialen) ?></span>
                                        <span class="sr-only">Verantwortlich: <?= htmlspecialchars($verantwortlichName) ?></span>
                                        <?php else: ?>
                                        <span class="aufgabe-wer" aria-hidden="true">–</span>
                                        <span class="sr-only">Verantwortlich: niemand</span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="api/aufgabe_orga_crud.php" class="aufgabe-loeschen-form" onsubmit="return confirm('Aufgabe löschen?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="aufgabe_id" value="<?= (int) $oa['id'] ?>">
                                        <input type="hidden" name="zurueck" value="cockpit">
                                        <button type="submit" class="aufgabe-loeschen" aria-label="Aufgabe löschen: <?= htmlspecialchars((string) $oa['titel']) ?>">✕</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <form method="post" action="api/aufgabe_orga_crud.php" class="aufgabe-neu">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="zurueck" value="cockpit">
                                <label for="neu_titel" class="sr-only">Neue Aufgabe</label>
                                <input id="neu_titel" name="titel" required placeholder="+ Neue Aufgabe">
                                <select name="verantwortlich_user_id" aria-label="Verantwortlich" class="aufgabe-neu-opt">
                                    <option value="">– Niemand –</option>
                                    <?php foreach ($orgaUsers as $ou): ?>
                                    <option value="<?= (int) $ou['id'] ?>"><?= htmlspecialchars($ou['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="faellig_am" aria-label="Fällig am" class="aufgabe-neu-opt">
                                <button type="submit" class="aufgabe-neu-plus" aria-label="Aufgabe anlegen">+</button>
                            </form>

                            <?php if (!empty($orgaErledigt)): ?>
                            <details class="aufgaben-erledigt" id="orga-erledigt">
                                <summary>✓ <?= count($orgaErledigt) ?> erledigt</summary>
                                <?php foreach ($orgaErledigt as $oe):
                                    $faelligAmE = (string) ($oe['faellig_am'] ?? '');
                                    $faelligTextE = $faelligAmE !== '' ? 'Fällig: ' . date('d.m.Y', strtotime($faelligAmE)) : '';
                                ?>
                                <div class="aufgabe-zeile">
                                    <?= $renderStatusPunkt($oe) ?>
                                    <div class="aufgabe-titel"><?= htmlspecialchars((string) $oe['titel']) ?></div>
                                    <div class="aufgabe-meta">
                                        <?php if ($faelligTextE !== ''): ?>
                                        <span class="aufgabe-faellig"><?= htmlspecialchars($faelligTextE) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="api/aufgabe_orga_crud.php" class="aufgabe-loeschen-form" onsubmit="return confirm('Aufgabe löschen?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="aufgabe_id" value="<?= (int) $oe['id'] ?>">
                                        <input type="hidden" name="zurueck" value="cockpit">
                                        <button type="submit" class="aufgabe-loeschen" aria-label="Aufgabe löschen: <?= htmlspecialchars((string) $oe['titel']) ?>">✕</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </details>
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

    // Aufgaben-Karte: barrierefreies Tab-Widget (progressive enhancement — ohne JS
    // bleiben alle drei Panels sichtbar mit eigener Überschrift, siehe Markup oben).
    (function initTabs() {
        const tabsEl = document.querySelector('.aufgaben-karte .tabs');
        if (!tabsEl) return;
        const tabButtons = Array.prototype.slice.call(tabsEl.querySelectorAll('.tab'));
        const panels = Array.prototype.slice.call(document.querySelectorAll('.aufgaben-karte .aufgaben-panel'));
        if (!tabButtons.length || !panels.length) return;

        const KEYS = ['meine', 'sponsoring', 'orga'];

        tabsEl.setAttribute('role', 'tablist');
        tabsEl.setAttribute('aria-label', 'Aufgaben nach Bereich');
        tabsEl.removeAttribute('hidden');

        tabButtons.forEach(function (btn) {
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-controls', 'panel-' + btn.dataset.tab);
        });
        panels.forEach(function (panel) {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', 'tab-' + panel.dataset.panel);
            const titel = panel.querySelector('.aufgaben-panel-titel');
            if (titel) { titel.classList.add('sr-only'); }
        });

        function activate(key, opts) {
            opts = opts || {};
            tabButtons.forEach(function (btn) {
                const isActive = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
            });
            panels.forEach(function (panel) {
                if (panel.dataset.panel === key) {
                    panel.removeAttribute('hidden');
                    panel.setAttribute('tabindex', '0');
                } else {
                    panel.setAttribute('hidden', '');
                    panel.setAttribute('tabindex', '-1');
                }
            });
            if (opts.focus) {
                const active = tabButtons.filter(function (btn) { return btn.dataset.tab === key; })[0];
                if (active) { active.focus(); }
            }
            try { localStorage.setItem('mkl_cockpit_tab', key); } catch (e) {}
        }

        function startTab() {
            const hatErledigtHinweis = new URLSearchParams(window.location.search).has('erledigt')
                && document.querySelector('.aufgabe-erledigt-hinweis');
            if (hatErledigtHinweis) { return 'orga'; }
            try {
                const gespeichert = localStorage.getItem('mkl_cockpit_tab');
                if (KEYS.indexOf(gespeichert) !== -1) { return gespeichert; }
            } catch (e) {}
            return 'meine';
        }

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { activate(btn.dataset.tab); });
        });

        tabsEl.addEventListener('keydown', function (event) {
            const currentIndex = tabButtons.indexOf(document.activeElement);
            if (currentIndex === -1) return;
            let nextIndex = null;
            if (event.key === 'ArrowRight') {
                nextIndex = (currentIndex + 1) % tabButtons.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabButtons.length - 1;
            }
            if (nextIndex === null) return;
            event.preventDefault();
            activate(tabButtons[nextIndex].dataset.tab, { focus: true });
        });

        activate(startTab());

        // Nach dem Reload aus „Erledigt": Fokus auf den Hinweis, damit Screenreader-/
        // Tastatur-Nutzer die Bestätigung samt Rückgängig-Button sofort mitbekommen.
        const erledigtHinweisEl = document.querySelector('.aufgabe-erledigt-hinweis');
        if (erledigtHinweisEl) { erledigtHinweisEl.focus(); }
    })();

    // Cockpit: Auf-/Zu-Zustand des Erledigt-Bereichs merken (Reiter Orga).
    (function initErledigtGedaechtnis() {
        const details = document.getElementById('orga-erledigt');
        if (!details) return;
        const STORAGE_KEY = 'mkl_cockpit_erledigt';
        try {
            if (localStorage.getItem(STORAGE_KEY) === 'open') { details.open = true; }
        } catch (e) {}
        details.addEventListener('toggle', function () {
            try { localStorage.setItem(STORAGE_KEY, details.open ? 'open' : 'closed'); } catch (e) {}
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
