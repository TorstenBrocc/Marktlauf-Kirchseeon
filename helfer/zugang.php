<?php
/**
 * Persönlicher Helfer-Zugang (öffentlich, UUID-validiert)
 * Keine Session erforderlich – UUID ist der Auth-Token.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/google_drive.php';
require_once __DIR__ . '/../src/helfer_aufgaben.php'; // schichtenOrderBy()

/*
 * Kachel-Schalter Jahrgang 2026 (TT, 20.09.2026).
 * "Meine Anmeldung" und "Dateien" sind NUR FUER DIESES JAHR aus -- das Konzept
 * bleibt, der Code bleibt. Fuer 2027 hier auf true stellen, nichts weiter noetig.
 */
$zeigeKachelAnmeldung = false;
$zeigeKachelDateien   = false;

$uuid = trim($_GET['uuid'] ?? '');
$helfer = null;
$slots = [];
$beitraege = [];
$error = false;

if ($uuid === '' || strlen($uuid) > 64) {
    $error = true;
} else {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare('
            SELECT id, vorname, nachname, email, phone, status, created_at
            FROM helfer
            WHERE uuid = :uuid AND status = :status
        ');
        $stmt->execute(['uuid' => $uuid, 'status' => 'bestaetigt']);
        $helfer = $stmt->fetch();

        if (!$helfer) {
            $error = true;
        } else {
            $slotStmt = $pdo->prepare('
                SELECT tag, zeitfenster, aufgabe FROM helfer_slots WHERE helfer_id = :id ORDER BY tag, zeitfenster
            ');
            $slotStmt->execute(['id' => $helfer['id']]);
            $slots = $slotStmt->fetchAll();

            $beitragStmt = $pdo->prepare('
                SELECT typ, freitext FROM helfer_beitrag WHERE helfer_id = :id
            ');
            $beitragStmt->execute(['id' => $helfer['id']]);
            $beitraege = $beitragStmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = true;
    }
}

// Helfer-Dateien: live aus dem Drive-Helfer-Ordner (Drive = Quelle der Wahrheit).
$helferDateien = [];
if (!$error && $zeigeKachelDateien && driveConfigured()) {
    try {
        $pdo = getDbConnection();
        $helferRoot = driveRootFolderId($pdo, 'helfer');
        foreach (driveListFilesRecursiveCached($helferRoot) as $f) {
            $helferDateien[] = [
                'source'       => 'drive',
                'ref'          => $f['id'],
                'originalname' => $f['name'],
                'mimetype'     => $f['mimeType'],
                'groesse'      => $f['size'],
                'created_at'   => $f['modifiedTime'],
                '_anc'         => $f['ancestors'] ?? [],
            ];
        }
    } catch (Throwable $e) {
        logError('Helfer-Zugang Drive-Liste: ' . $e->getMessage());
    }
}
// Strang 3: einteilungsspezifische Sichtbarkeit. Eine Drive-Datei mit Schicht-Zuordnung
// erscheint nur, wenn der Helfer einer der zugeordneten Schichten zugeteilt ist; Dateien
// ohne Zuordnung (und lokaler Alt-Bestand) bleiben für alle sichtbar.
if (!$error && $zeigeKachelDateien && $helfer && $helferDateien !== []) {
    try {
        $pdo = getDbConnection();
        $visMap = [];
        foreach ($pdo->query('SELECT drive_file_id, schicht_id FROM helfer_datei_sichtbarkeit')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $visMap[$row['drive_file_id']][] = (int) $row['schicht_id'];
        }
        if ($visMap !== []) {
            $meine = $pdo->prepare('SELECT schicht_id FROM schicht_zuteilung WHERE helfer_id = :id');
            $meine->execute(['id' => $helfer['id']]);
            $meineSchichten = array_map('intval', $meine->fetchAll(PDO::FETCH_COLUMN));
            $helferDateien = array_values(array_filter($helferDateien, static function ($d) use ($visMap, $meineSchichten) {
                if ($d['source'] !== 'drive') {
                    return true; // lokaler Alt-Bestand: global
                }
                // Vererbung: nächste Zuordnung gewinnt — Datei selbst, sonst Elternordner aufsteigend.
                $chain = array_merge([$d['ref']], array_reverse($d['_anc'] ?? []));
                foreach ($chain as $node) {
                    if (isset($visMap[$node])) {
                        return array_intersect($visMap[$node], $meineSchichten) !== [];
                    }
                }
                return true; // nirgends eine Zuordnung: global
            }));
        }
    } catch (PDOException $e) {
        // Migration 041 evtl. noch nicht angewandt -> kein Filter (alles global)
    }
}

$einsaetze = [];
if (!$error) {
    try {
        $pdo = getDbConnection();
        // postennummer/lat/lon gibt es erst ab Migration 097 — fehlt sie noch,
        // faellt die Abfrage auf die Grundfelder zurueck, statt die Seite des
        // Helfers mit einem SQL-Fehler abzuwerfen.
        $hatPosten  = $pdo->query("SHOW COLUMNS FROM schichten LIKE 'postennummer'")->fetch() !== false;
        $hatKennung = $pdo->query("SHOW COLUMNS FROM schichten LIKE 'kennung'")->fetch() !== false;
        $postenFelder = $hatPosten ? ', sc.postennummer, sc.lat, sc.lon' : '';
        $postenFelder .= $hatKennung ? ', sc.kennung' : '';
        $einsatzStmt = $pdo->prepare('
            SELECT sc.titel, sc.beschreibung, sc.ort, sc.tag, sc.von, sc.bis, sc.zeitfenster' . $postenFelder . '
            FROM schicht_zuteilung sz
            JOIN schichten sc ON sc.id = sz.schicht_id
            WHERE sz.helfer_id = :id
            ORDER BY ' . schichtenOrderBy($pdo, 'sc') . '
        ');
        $einsatzStmt->execute(['id' => $helfer['id']]);
        $einsaetze = $einsatzStmt->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

// Briefings (sichtbar) + Gruppen-Links für den Helfer-Draht
$briefings = [];
$telegramUrl = '';
$whatsappUrl = '';
if (!$error) {
    try {
        $pdo = getDbConnection();
        $briefings = $pdo->query(
            'SELECT text, prioritaet, created_at FROM briefings WHERE sichtbar = 1 ORDER BY id DESC LIMIT 30'
        )->fetchAll();
    } catch (PDOException $e) { /* Migration 019 evtl. noch nicht angewandt */ }
    try {
        $pdo = getDbConnection();
        $g = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('telegram_gruppe_url','whatsapp_gruppe_url')");
        foreach ($g->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'telegram_gruppe_url') { $telegramUrl = (string) ($v ?? ''); }
            if ($k === 'whatsapp_gruppe_url') { $whatsappUrl = (string) ($v ?? ''); }
        }
    } catch (PDOException $e) { /* egal */ }
}

function formatEinsatzZeit(array $s): string {
    $parts = [];
    if (!empty($s['tag'])) {
        // Wochentag deutsch -- date('l') waere Englisch und wich vom PDF ab.
        $wt = helferWochentag((string) $s['tag']);
        $parts[] = ($wt !== '' ? $wt . ', ' : '') . date('d.m.Y', strtotime($s['tag']));
    }
    if (!empty($s['von'])) {
        $zeit = substr($s['von'], 0, 5);
        if (!empty($s['bis'])) {
            $zeit .= '–' . substr($s['bis'], 0, 5);
        }
        $parts[] = $zeit . ' Uhr';
    } elseif (!empty($s['zeitfenster'])) {
        // Posten ohne feste Uhrzeit ("während des Laufs") — sonst stünde hier nichts.
        $parts[] = (string) $s['zeitfenster'];
    }
    return implode(' · ', $parts);
}

/**
 * Klartext zum Zeitfenster: ab wann vor Ort, bis wann bleiben.
 * Der Helfer liest "ab 11:00 Uhr … bis 12:30 Uhr" schneller als "11:00–12:30".
 */
function formatEinsatzFenster(array $s): string {
    // Ohne feste Uhrzeit steht das Zeitfenster schon in der Meta-Zeile darüber.
    if (empty($s['von'])) {
        return '';
    }
    $text = 'ab ' . substr((string) $s['von'], 0, 5) . ' Uhr vor Ort';
    if (!empty($s['bis'])) {
        $text .= ', bis ' . substr((string) $s['bis'], 0, 5) . ' Uhr';
    }
    return $text;
}

/** Google-Maps-Link zu einem Posten (leer, wenn keine Koordinaten hinterlegt). */
function einsatzKartenLink(array $s): string {
    if (empty($s['lat']) || empty($s['lon'])) {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query='
        . rawurlencode($s['lat'] . ',' . $s['lon']);
}

function formatFileSizeHelfer(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function getFileIconHelfer(string $mimetype): string {
    return match (true) {
        str_contains($mimetype, 'pdf') => '📄',
        str_contains($mimetype, 'word') => '📝',
        str_contains($mimetype, 'sheet') => '📊',
        str_contains($mimetype, 'image') => '🖼️',
        default => '📁',
    };
}

$config = [];
try {
    $config = getConfig();
} catch (Throwable $e) {
    // Config not available
}

$orgaEmail = $config['orga']['email'] ?? 'info@atsv-kirchseeon-marktlauf.de';
$orgaPhone = $config['orga']['phone'] ?? '';
$notfallPhone = $config['orga']['notfall_phone'] ?? '';

// Erreichbarkeiten am Renntag -- eine Quelle fuer Seite und PDF, gepflegt in
// storage/config.php unter orga.renntag_kontakte. Siehe renntagKontakte() in
// src/helfer_aufgaben.php. Ohne Config bleibt die Kachel weg.
$renntagKontakte = renntagKontakte();

$basePath = '../';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Mein Helfer-Zugang | ATSV Marktlauf Kirchseeon</title>
    <?php require_once __DIR__ . '/../src/layout/head.php'; ?>
    <style>
        .zugang-page {
            min-height: 100vh;
            background: var(--gray-100);
            display: flex;
            flex-direction: column;
        }
        .zugang-page main {
            flex: 1;
        }
        .zugang-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--space-xl) var(--space-md);
        }
        .zugang-header {
            text-align: center;
            margin-bottom: var(--space-lg);
        }
        .zugang-header h1 {
            font-size: 2rem;
            margin-bottom: var(--space-sm);
        }
        .zugang-header p {
            color: var(--gray-600);
        }
        .zugang-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        .zugang-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 22px -4px rgba(0,0,0,0.2);
            padding: var(--space-md);
        }
        .zugang-section h2 {
            font-size: 1.1rem;
            margin-bottom: var(--space-sm);
            padding-bottom: var(--space-xs);
            border-bottom: 2px solid var(--gray-200);
        }
        .zugang-page .main-footer {
            background: var(--gray-900);
            color: var(--gray-400);
            padding: var(--space-lg) 0;
            margin-top: auto;
        }
        .zugang-page .main-footer a {
            color: var(--gray-300);
        }
        .zugang-page .main-footer a:hover {
            color: var(--white);
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-sm);
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: var(--text-sm);
            color: var(--gray-500);
            margin-bottom: var(--space-xs);
        }
        .info-value {
            font-weight: 600;
            color: var(--gray-800);
        }
        .slot-list {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }
        .slot-badge {
            display: inline-block;
            padding: var(--space-xs) var(--space-sm);
            background: var(--gray-100);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .beitrag-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .beitrag-list li {
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .beitrag-list li:last-child {
            border-bottom: none;
        }
        .beitrag-type {
            font-weight: 600;
            text-transform: capitalize;
        }
        .beitrag-detail {
            color: var(--gray-600);
            font-size: var(--text-sm);
        }
        .placeholder-notice {
            text-align: center;
            padding: var(--space-md);
            color: var(--gray-500);
            background: var(--gray-50);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .placeholder-notice p {
            margin: 0 0 var(--space-xs) 0;
        }
        .placeholder-notice p:last-child {
            margin-bottom: 0;
        }
        .einsatz-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }
        .einsatz-item {
            padding: var(--space-md);
            background: var(--gray-50);
            border-left: 3px solid var(--primary);
            border-radius: var(--radius-md);
        }
        .einsatz-titel {
            font-weight: 600;
            color: var(--gray-900);
        }
        .einsatz-meta {
            font-size: var(--text-sm);
            color: var(--gray-600);
            margin-top: 2px;
        }
        .einsatz-desc {
            font-size: var(--text-sm);
            color: var(--gray-700);
            margin-top: var(--space-xs);
        }
        .einsatz-kopf {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }
        .posten-badge {
            display: inline-block;
            background: var(--color-primary);
            color: #fff;
            border-radius: var(--radius-sm);
            padding: 0.1rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .einsatz-fenster {
            margin-top: var(--space-xs);
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--gray-800);
        }
        .einsatz-karte {
            display: inline-block;
            margin-top: var(--space-sm);
            padding: var(--space-xs) var(--space-sm);
            border: 1px solid var(--color-primary);
            border-radius: var(--radius-sm);
            color: var(--color-primary);
            text-decoration: none;
            font-size: 0.8rem;
        }
        .einsatz-karte:hover { background: var(--color-primary); color: #fff; }
        .einsatz-koord {
            margin-top: 0.2rem;
            font-size: 0.7rem;
            color: var(--gray-600);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        /* Streckenplan: Vorschau in der Seite */
        .plan-vorschau { margin: var(--space-md) 0 0; }
        .plan-knopf {
            display: block; width: 100%; padding: 0; border: 1px solid var(--gray-200);
            border-radius: var(--radius-md); overflow: hidden; background: var(--gray-100);
            cursor: zoom-in; position: relative;
        }
        .plan-knopf img { display: block; width: 100%; height: auto; }
        .plan-lupe {
            position: absolute; left: 50%; bottom: 10px; transform: translateX(-50%);
            background: rgba(0,0,0,0.72); color: #fff; border-radius: 999px;
            padding: 5px 12px; font-size: 0.75rem; white-space: nowrap;
        }
        .plan-vorschau figcaption {
            margin-top: var(--space-xs); font-size: 0.75rem; color: var(--gray-600);
        }

        /* Streckenplan: Vollbild */
        .plan-buehne {
            position: fixed; inset: 0; z-index: 1000; background: #0d120e;
            display: flex; flex-direction: column;
            padding-top: env(safe-area-inset-top, 0px);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .plan-buehne[hidden] { display: none; }
        .plan-leiste {
            display: flex; align-items: center; gap: 8px; padding: 10px 12px;
            background: rgba(255,255,255,0.06); color: #fff; flex: 0 0 auto;
        }
        .plan-titel { font-weight: 600; font-size: 0.9rem; margin-right: auto; }
        .plan-aktion {
            min-width: 40px; height: 36px; border-radius: 8px; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.1);
            color: #fff; font-size: 1rem; line-height: 1;
        }
        .plan-aktion:hover { background: rgba(255,255,255,0.2); }
        .plan-schliessen { border-color: rgba(255,255,255,0.45); }
        .plan-flaeche {
            flex: 1 1 auto; overflow: hidden; display: flex;
            align-items: center; justify-content: center;
            touch-action: none; cursor: grab;
        }
        .plan-flaeche:active { cursor: grabbing; }
        .plan-flaeche img {
            max-width: 100%; max-height: 100%; transform-origin: center center;
            will-change: transform; user-select: none; -webkit-user-drag: none;
        }
        .plan-hinweis {
            margin: 0; padding: 8px 12px; text-align: center;
            font-size: 0.72rem; color: rgba(255,255,255,0.65); flex: 0 0 auto;
        }

        .einsatz-pdf {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }
        .einsatz-pdf-hinweis {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        .btn-disabled {
            display: inline-block;
            padding: var(--space-sm) var(--space-md);
            background: var(--gray-200);
            color: var(--gray-500);
            border-radius: var(--radius-md);
            cursor: not-allowed;
            font-size: var(--text-sm);
        }
        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .file-list li {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .file-list li:last-child {
            border-bottom: none;
        }
        .file-icon {
            font-size: 1.25rem;
        }
        .file-info {
            flex: 1;
            min-width: 0;
        }
        .file-name {
            font-weight: 500;
            font-size: var(--text-sm);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-meta {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        .file-download {
            padding: var(--space-xs) var(--space-sm);
            background: var(--color-primary);
            color: white;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .file-download:hover {
            opacity: 0.9;
        }
        .contact-grid {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            font-size: var(--text-sm);
        }
        .contact-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--space-xs);
        }
        .contact-item a {
            color: var(--color-primary);
            text-decoration: none;
        }
        .contact-item a:hover {
            text-decoration: underline;
        }
        /* Benannte Erreichbarkeiten am Renntag: Rolle, Person, Nummer untereinander.
           Die Nummer ist der Tap-Target -- gross genug fuer Handschuh und Eile. */
        .contact-person {
            flex-direction: column;
            gap: 0.1rem;
            align-items: flex-start;
        }
        .contact-person .rolle {
            font-weight: 600;
            color: var(--gray-800);
        }
        .contact-person .name {
            font-size: 0.78rem;
            color: var(--gray-600);
        }
        .contact-person a.tel {
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .contact-sep {
            height: 1px;
            background: var(--gray-100);
            margin: 0.45rem 0;
        }
        .briefing-block { margin-bottom: 1rem; }
        .briefing-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--space-sm); }
        .briefing-item { padding: var(--space-md); background: var(--gray-50); border-left: 3px solid var(--gray-300); border-radius: var(--radius-md); }
        .briefing-item.p-wichtig { border-left-color: var(--color-primary); background: #eef7f0; }
        .briefing-item.p-notfall { border-left-color: #d32f2f; background: #fdecea; }
        .briefing-prio { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: var(--gray-500); }
        .briefing-item.p-notfall .briefing-prio { color: #d32f2f; }
        .briefing-time { font-size: 0.7rem; color: var(--gray-500); margin-top: var(--space-xs); }
        .gruppe-join { display: flex; gap: var(--space-sm); flex-wrap: wrap; margin-top: var(--space-sm); }
        .btn-telegram, .btn-whatsapp { padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); text-decoration: none; font-size: var(--text-sm); color: #fff; }
        .btn-telegram { background: #229ED9; }
        .btn-whatsapp { background: #25D366; }
        .error-section {
            text-align: center;
            padding: var(--space-xxl) var(--space-md);
        }
        .error-section h1 {
            font-size: 2rem;
            margin-bottom: var(--space-md);
            color: var(--gray-700);
        }
        .error-section p {
            color: var(--gray-600);
            margin-bottom: var(--space-lg);
        }
    </style>
</head>
<body class="zugang-page">
    <?php require_once __DIR__ . '/../src/layout/header-minimal.php'; ?>

    <main>
        <?php if ($error): ?>
        <section class="error-section">
            <div class="container">
                <h1>Zugang nicht verfügbar</h1>
                <p>Der angeforderte Zugang ist ungültig oder wurde noch nicht freigeschaltet.</p>
                <a href="<?= $basePath ?: './' ?>" class="btn btn-primary">Zur Startseite</a>
            </div>
        </section>
        <?php else: ?>
        <div class="zugang-content">
            <div class="zugang-header">
                <h1>Hallo, <?= htmlspecialchars($helfer['vorname']) ?>!</h1>
                <p>Hier findest du alle Infos zu deiner Helfer-Anmeldung beim Marktlauf Kirchseeon.</p>
            </div>

            <?php if (!empty($briefings) || $telegramUrl !== '' || $whatsappUrl !== ''): ?>
            <section class="zugang-section briefing-block">
                <h2>Infos &amp; Briefings</h2>
                <?php if (!empty($briefings)): ?>
                <ul class="briefing-list">
                    <?php foreach ($briefings as $b): $prio = $b['prioritaet']; ?>
                    <li class="briefing-item p-<?= htmlspecialchars($prio) ?>">
                        <?php if ($prio !== 'normal'): ?><span class="briefing-prio"><?= $prio === 'notfall' ? '⚠️ Notfall' : 'Wichtig' ?></span><br><?php endif; ?>
                        <?= nl2br(htmlspecialchars($b['text'])) ?>
                        <div class="briefing-time"><?= date('d.m.Y H:i', strtotime($b['created_at'])) ?> Uhr</div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="color: var(--gray-600); font-size: var(--text-sm);">Aktuell keine Infos.</p>
                <?php endif; ?>
                <?php if ($telegramUrl !== '' || $whatsappUrl !== ''): ?>
                <div class="gruppe-join">
                    <?php if ($telegramUrl !== ''): ?><a class="btn-telegram" href="<?= htmlspecialchars($telegramUrl) ?>" target="_blank" rel="noopener">Telegram-Gruppe beitreten</a><?php endif; ?>
                    <?php if ($whatsappUrl !== ''): ?><a class="btn-whatsapp" href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener">WhatsApp-Gruppe beitreten</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <div class="zugang-grid">
            <?php if ($renntagKontakte !== []): ?>
            <section class="zugang-section">
                <h2>Kontakt am Renntag</h2>
                <div class="contact-grid">
                    <?php foreach ($renntagKontakte as $i => $k): ?>
                    <?php if ($i > 0): ?><div class="contact-sep"></div><?php endif; ?>
                    <div class="contact-item contact-person">
                        <span class="rolle"><?= htmlspecialchars($k['rolle']) ?></span>
                        <span class="name"><?= htmlspecialchars($k['name']) ?> · <?= htmlspecialchars($k['wann']) ?></span>
                        <a class="tel" href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $k['tel_e164'])) ?>"><?= htmlspecialchars($k['tel']) ?></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($zeigeKachelAnmeldung): ?>
            <section class="zugang-section">
                <h2>Meine Anmeldung</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($helfer['vorname'] . ' ' . $helfer['nachname']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Angemeldet am</span>
                        <span class="info-value"><?= date('d.m.Y', strtotime($helfer['created_at'])) ?></span>
                    </div>
                </div>

                <?php if (!empty($slots)): ?>
                <div style="margin-top: var(--space-md);">
                    <span class="info-label">Verfügbare Zeitfenster</span>
                    <div class="slot-list" style="margin-top: var(--space-xs);">
                        <?php foreach ($slots as $slot): ?>
                            <?php
                            $tagFormatted = date('D, d.m.', strtotime($slot['tag']));
                            // Legacy-Werte (vormittag/nachmittag) hübsch, sonst Freitext-Zeitfenster
                            $zf = (string) $slot['zeitfenster'];
                            $zeitLabel = $zf === 'vormittag' ? 'Vormittag' : ($zf === 'nachmittag' ? 'Nachmittag' : $zf);
                            $aufgabe = trim((string) ($slot['aufgabe'] ?? ''));
                            $slotText = $tagFormatted . ' ' . ($aufgabe !== '' ? $aufgabe . ' – ' : '') . $zeitLabel;
                            ?>
                            <span class="slot-badge"><?= htmlspecialchars($slotText) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($beitraege)): ?>
                <div style="margin-top: var(--space-md);">
                    <span class="info-label">Dein Beitrag</span>
                    <ul class="beitrag-list" style="margin-top: var(--space-xs);">
                        <?php foreach ($beitraege as $b): ?>
                            <li>
                                <span class="beitrag-type"><?= htmlspecialchars($b['typ']) ?></span>
                                <?php if (!empty($b['freitext'])): ?>
                                    <span class="beitrag-detail"> – <?= htmlspecialchars($b['freitext']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="zugang-section">
                <h2>Einsatzplan</h2>
                <?php if (empty($einsaetze)): ?>
                <div class="placeholder-notice">
                    <p>Der Einsatzplan wird noch erstellt.</p>
                    <p>Du erhältst eine Benachrichtigung, sobald dein Einsatzort feststeht.</p>
                </div>
                <?php else: ?>
                <ul class="einsatz-list">
                    <?php foreach ($einsaetze as $e): ?>
                        <?php
                        $zeit    = formatEinsatzZeit($e);
                        $fenster = formatEinsatzFenster($e);
                        $karte   = einsatzKartenLink($e);
                        // Streckenposten tragen Nummern, die Stationen Buchstaben (V1/V2).
                        $marke = !empty($e['kennung'])
                            ? $e['kennung']
                            : (!empty($e['postennummer']) ? 'Posten ' . (int) $e['postennummer'] : '');
                        ?>
                        <li class="einsatz-item">
                            <div class="einsatz-kopf">
                                <?php if ($marke !== ''): ?>
                                    <span class="posten-badge" title="Deine Kennung am Renntag"><?= htmlspecialchars($marke) ?></span>
                                <?php endif; ?>
                                <span class="einsatz-titel"><?= htmlspecialchars($e['titel']) ?></span>
                            </div>
                            <div class="einsatz-meta">
                                <?php if ($zeit !== ''): ?><?= htmlspecialchars($zeit) ?><?php endif; ?>
                                <?php if (!empty($e['ort'])): ?><?= $zeit !== '' ? ' · ' : '' ?><?= htmlspecialchars($e['ort']) ?><?php endif; ?>
                            </div>
                            <?php if ($fenster !== ''): ?>
                                <div class="einsatz-fenster">🕒 <?= htmlspecialchars($fenster) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($e['beschreibung'])): ?>
                                <div class="einsatz-desc"><?= nl2br(htmlspecialchars($e['beschreibung'])) ?></div>
                            <?php endif; ?>
                            <?php if ($karte !== ''): ?>
                                <a class="einsatz-karte" href="<?= htmlspecialchars($karte) ?>" target="_blank" rel="noopener">
                                    📍 Standort in Google Maps öffnen
                                </a>
                                <div class="einsatz-koord"><?= htmlspecialchars($e['lat'] . ', ' . $e['lon']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="einsatz-pdf">
                    <a href="plan_pdf.php?uuid=<?= urlencode($uuid) ?>" target="_blank" rel="noopener" class="file-download">
                        📄 Einsatzplan als PDF
                    </a>
                    <span class="einsatz-pdf-hinweis">zum Ausdrucken oder Speichern aufs Handy</span>
                </p>
                <?php endif; ?>

                <!-- Streckenplan: klein in der Seite, auf Tipp groß und zoombar. -->
                <figure class="plan-vorschau">
                    <button type="button" class="plan-knopf" id="plan-oeffnen" aria-label="Streckenplan vergrößern">
                        <img src="<?= $basePath ?>assets/images/strecke/streckenplan-luftbild-klein.jpg"
                             width="452" height="640" loading="lazy"
                             alt="Streckenplan Marktlauf Kirchseeon 2026 mit Strecken und Streckenposten">
                        <span class="plan-lupe">🔍 Antippen zum Vergrößern</span>
                    </button>
                    <figcaption>Streckenplan mit allen Posten · im großen Bild mit zwei Fingern zoomen</figcaption>
                </figure>
            </section>

            <?php if ($zeigeKachelDateien): ?>
            <section class="zugang-section">
                <h2>Dateien</h2>
                <?php if (empty($helferDateien)): ?>
                    <p style="color: var(--gray-600); font-size: var(--text-sm);">Noch keine Dateien verfügbar.</p>
                <?php else: ?>
                    <ul class="file-list">
                        <?php foreach ($helferDateien as $d): ?>
                            <li>
                                <span class="file-icon"><?= getFileIconHelfer($d['mimetype']) ?></span>
                                <div class="file-info">
                                    <div class="file-name"><?= htmlspecialchars($d['originalname']) ?></div>
                                    <div class="file-meta"><?= formatFileSizeHelfer((int)$d['groesse']) ?> · <?= date('d.m.Y', strtotime($d['created_at'])) ?></div>
                                </div>
                                <a href="file_download.php?<?= $d['source'] === 'drive' ? 'fid=' . urlencode($d['ref']) : 'id=' . (int) $d['ref'] ?>&uuid=<?= urlencode($uuid) ?>" class="file-download">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../src/layout/footer.php'; ?>

<?php if (!$error): ?>
<!-- Vollbild-Streckenplan. Liegt außerhalb von <main>, damit ihn nichts überlagert. -->
<div class="plan-buehne" id="plan-buehne" hidden>
    <div class="plan-leiste">
        <span class="plan-titel">Streckenplan</span>
        <button type="button" class="plan-aktion" data-zoom="-1" aria-label="Verkleinern">−</button>
        <button type="button" class="plan-aktion" data-zoom="1" aria-label="Vergrößern">+</button>
        <button type="button" class="plan-aktion" id="plan-reset" aria-label="Ansicht zurücksetzen">↺</button>
        <button type="button" class="plan-aktion plan-schliessen" id="plan-schliessen" aria-label="Schließen">✕</button>
    </div>
    <div class="plan-flaeche" id="plan-flaeche">
        <img id="plan-bild" src="<?= $basePath ?>assets/images/strecke/streckenplan-luftbild.jpg"
             alt="Streckenplan Marktlauf Kirchseeon 2026 – Strecken, Streckenposten, Vollsperrung">
    </div>
    <p class="plan-hinweis">Mit zwei Fingern zoomen · ziehen zum Verschieben · Doppeltipp für schnellen Zoom</p>
</div>

<script>
(function () {
    'use strict';
    var oeffnen = document.getElementById('plan-oeffnen');
    var buehne  = document.getElementById('plan-buehne');
    if (!oeffnen || !buehne) { return; }

    var flaeche = document.getElementById('plan-flaeche');
    var bild    = document.getElementById('plan-bild');
    var skala = 1, vx = 0, vy = 0;          // Zoomfaktor und Verschiebung
    var MIN = 1, MAX = 8;

    function zeichne() {
        bild.style.transform = 'translate(' + vx + 'px,' + vy + 'px) scale(' + skala + ')';
    }
    function grenzen() {
        // Nicht weiter schieben, als Bild über den Rand hinausragt.
        var r = flaeche.getBoundingClientRect();
        var bw = bild.offsetWidth * skala, bh = bild.offsetHeight * skala;
        var maxX = Math.max(0, (bw - r.width) / 2), maxY = Math.max(0, (bh - r.height) / 2);
        vx = Math.min(maxX, Math.max(-maxX, vx));
        vy = Math.min(maxY, Math.max(-maxY, vy));
    }
    function setzeSkala(neu, mx, my) {
        neu = Math.min(MAX, Math.max(MIN, neu));
        var f = neu / skala;
        // Zum Finger-/Mausmittelpunkt hin zoomen, nicht zur Bildmitte.
        if (mx !== undefined) {
            var r = flaeche.getBoundingClientRect();
            var zx = mx - r.left - r.width / 2, zy = my - r.top - r.height / 2;
            vx = zx - (zx - vx) * f;
            vy = zy - (zy - vy) * f;
        }
        skala = neu; grenzen(); zeichne();
    }
    function zurueck() { skala = 1; vx = vy = 0; zeichne(); }

    function auf() {
        buehne.hidden = false;
        document.body.style.overflow = 'hidden';
        zurueck();
        document.getElementById('plan-schliessen').focus();
    }
    function zu() {
        buehne.hidden = true;
        document.body.style.overflow = '';
        oeffnen.focus();
    }

    oeffnen.addEventListener('click', auf);
    document.getElementById('plan-schliessen').addEventListener('click', zu);
    document.getElementById('plan-reset').addEventListener('click', zurueck);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !buehne.hidden) { zu(); }
    });
    buehne.querySelectorAll('[data-zoom]').forEach(function (b) {
        b.addEventListener('click', function () {
            setzeSkala(skala * (b.dataset.zoom === '1' ? 1.5 : 1 / 1.5));
        });
    });

    // --- Finger und Maus -------------------------------------------------
    var zeiger = new Map(), startAbstand = 0, startSkala = 1, letzte = null;

    flaeche.addEventListener('pointerdown', function (e) {
        flaeche.setPointerCapture(e.pointerId);
        zeiger.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (zeiger.size === 2) {
            var p = [...zeiger.values()];
            startAbstand = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
            startSkala = skala;
        } else {
            letzte = { x: e.clientX, y: e.clientY };
        }
    });

    flaeche.addEventListener('pointermove', function (e) {
        if (!zeiger.has(e.pointerId)) { return; }
        zeiger.set(e.pointerId, { x: e.clientX, y: e.clientY });
        e.preventDefault();

        if (zeiger.size === 2) {                     // zwei Finger: zoomen
            var p = [...zeiger.values()];
            var abstand = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
            if (startAbstand > 0) {
                setzeSkala(startSkala * (abstand / startAbstand),
                           (p[0].x + p[1].x) / 2, (p[0].y + p[1].y) / 2);
            }
        } else if (letzte) {                          // ein Finger: schieben
            vx += e.clientX - letzte.x;
            vy += e.clientY - letzte.y;
            letzte = { x: e.clientX, y: e.clientY };
            grenzen(); zeichne();
        }
    }, { passive: false });

    function los(e) {
        zeiger.delete(e.pointerId);
        if (zeiger.size < 2) { startAbstand = 0; }
        if (zeiger.size === 0) { letzte = null; }
        else { var p = [...zeiger.values()][0]; letzte = { x: p.x, y: p.y }; }
    }
    flaeche.addEventListener('pointerup', los);
    flaeche.addEventListener('pointercancel', los);

    // Doppeltipp / Doppelklick: zwischen Übersicht und 3x wechseln
    flaeche.addEventListener('dblclick', function (e) {
        setzeSkala(skala > 1.2 ? 1 : 3, e.clientX, e.clientY);
    });

    // Mausrad am Rechner
    flaeche.addEventListener('wheel', function (e) {
        e.preventDefault();
        setzeSkala(skala * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX, e.clientY);
    }, { passive: false });
})();
</script>
<?php endif; ?>
</body>
</html>
