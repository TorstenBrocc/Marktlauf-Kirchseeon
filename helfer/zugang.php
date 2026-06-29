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
                SELECT tag, zeitfenster FROM helfer_slots WHERE helfer_id = :id ORDER BY tag, zeitfenster
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
        }
        .zugang-content {
            max-width: 800px;
            margin: 0 auto;
            padding: var(--space-xl) var(--space-md);
        }
        .zugang-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }
        .zugang-header h1 {
            font-size: 2rem;
            margin-bottom: var(--space-sm);
        }
        .zugang-header p {
            color: var(--gray-600);
        }
        .zugang-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        .zugang-section h2 {
            font-size: 1.25rem;
            margin-bottom: var(--space-md);
            padding-bottom: var(--space-sm);
            border-bottom: 2px solid var(--gray-200);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
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
            padding: var(--space-lg);
            color: var(--gray-500);
            background: var(--gray-50);
            border-radius: var(--radius-md);
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
        .contact-grid {
            display: grid;
            gap: var(--space-md);
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        .contact-item a {
            color: var(--color-primary);
            text-decoration: none;
        }
        .contact-item a:hover {
            text-decoration: underline;
        }
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
                            $zeitLabel = $slot['zeitfenster'] === 'vormittag' ? 'Vormittag' : 'Nachmittag';
                            ?>
                            <span class="slot-badge"><?= htmlspecialchars($tagFormatted . ' ' . $zeitLabel) ?></span>
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
                <div class="placeholder-notice">
                    <p>Der Einsatzplan wird noch erstellt.</p>
                    <p>Du erhältst eine Benachrichtigung, sobald dein Einsatzort feststeht.</p>
                </div>
            </section>

            <section class="zugang-section">
                <h2>Dateien</h2>
                <p style="color: var(--gray-600); margin-bottom: var(--space-md);">Wichtige Dokumente und Infomaterial zum Download.</p>
                <span class="btn-disabled">Downloads folgen in Kürze</span>
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
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../src/layout/footer.php'; ?>
</body>
</html>
