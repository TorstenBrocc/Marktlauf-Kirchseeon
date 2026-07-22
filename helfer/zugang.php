<?php
/**
 * Persönlicher Helfer-Zugang (öffentlich, UUID-validiert)
 * Keine Session erforderlich – UUID ist der Auth-Token.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

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

$helferDateien = [];
if (!$error) {
    try {
        $pdo = getDbConnection();
        $dateiStmt = $pdo->prepare('SELECT id, originalname, mimetype, groesse, created_at FROM dateien WHERE bereich = :bereich ORDER BY created_at DESC');
        $dateiStmt->execute(['bereich' => 'helfer']);
        $helferDateien = $dateiStmt->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

$einsaetze = [];
if (!$error) {
    try {
        $pdo = getDbConnection();
        $einsatzStmt = $pdo->prepare('
            SELECT sc.titel, sc.beschreibung, sc.ort, sc.tag, sc.von, sc.bis
            FROM schicht_zuteilung sz
            JOIN schichten sc ON sc.id = sz.schicht_id
            WHERE sz.helfer_id = :id
            ORDER BY (sc.tag IS NULL), sc.tag, (sc.von IS NULL), sc.von, sc.titel
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
        $parts[] = date('l, d.m.Y', strtotime($s['tag']));
    }
    if (!empty($s['von'])) {
        $zeit = substr($s['von'], 0, 5);
        if (!empty($s['bis'])) {
            $zeit .= '–' . substr($s['bis'], 0, 5);
        }
        $parts[] = $zeit . ' Uhr';
    }
    return implode(' · ', $parts);
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
                <a href="<?= $basePath ?>index.html" class="btn btn-primary">Zur Startseite</a>
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
                        <?php $zeit = formatEinsatzZeit($e); ?>
                        <li class="einsatz-item">
                            <div class="einsatz-titel"><?= htmlspecialchars($e['titel']) ?></div>
                            <div class="einsatz-meta">
                                <?php if ($zeit !== ''): ?><?= htmlspecialchars($zeit) ?><?php endif; ?>
                                <?php if (!empty($e['ort'])): ?><?= $zeit !== '' ? ' · ' : '' ?><?= htmlspecialchars($e['ort']) ?><?php endif; ?>
                            </div>
                            <?php if (!empty($e['beschreibung'])): ?>
                                <div class="einsatz-desc"><?= nl2br(htmlspecialchars($e['beschreibung'])) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>

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
                                <a href="file_download.php?id=<?= $d['id'] ?>&uuid=<?= urlencode($uuid) ?>" class="file-download">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="zugang-section">
                <h2>Kontakt</h2>
                <div class="contact-grid">
                    <div class="contact-item">
                        <strong>E-Mail:</strong>
                        <a href="mailto:<?= htmlspecialchars($orgaEmail) ?>"><?= htmlspecialchars($orgaEmail) ?></a>
                    </div>
                    <?php if ($orgaPhone): ?>
                    <div class="contact-item">
                        <strong>Telefon Orga:</strong>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $orgaPhone)) ?>"><?= htmlspecialchars($orgaPhone) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ($notfallPhone): ?>
                    <div class="contact-item">
                        <strong>Notfall (nur Veranstaltungstag):</strong>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $notfallPhone)) ?>"><?= htmlspecialchars($notfallPhone) ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../src/layout/footer.php'; ?>
</body>
</html>
