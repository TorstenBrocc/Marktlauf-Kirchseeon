<?php
/**
 * Einstellungen (Admin only)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

if (!$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins haben Zugriff auf die Einstellungen.';
    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();
$config = getConfig();

$settings = [];
try {
    $stmt = $pdo->query('SELECT `key`, `value` FROM einstellungen');
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

$renntagDatum = $settings['renntag_datum'] ?? '';
$veranstaltungsname = $settings['veranstaltungsname'] ?? '';
$kontaktEmail = $settings['kontakt_email'] ?? '';
$raceresultUrl = $settings['raceresult_url'] ?? '';
$trelloUrl = $settings['trello_board_url'] ?? '';
$onedriveUrl = $settings['onedrive_url'] ?? '';

$smtpHost = $config['smtp_host'] ?? '–';
$smtpPort = $config['smtp_port'] ?? '–';
$smtpFrom = $config['smtp_from'] ?? $config['smtp_user'] ?? '–';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Einstellungen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .settings-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .settings-section h2 {
            font-size: 1.1rem;
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row.single {
            grid-template-columns: 1fr;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .form-group input {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .info-block {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1rem;
        }
        .info-block dl {
            margin: 0;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
        }
        .info-block dt {
            font-weight: 500;
            color: var(--text-light);
        }
        .info-block dd {
            margin: 0;
        }
        .info-hint {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.75rem;
        }
        .btn-row {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Marktlauf Orga</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item">
                    <a href="sponsoren.php">Sponsoren</a>
                </li>
                <li class="nav-item">
                    <a href="dateien.php">Dateien</a>
                </li>
                <li class="nav-item">
                    <a href="ticker.php">Live-Ticker</a>
                    <span class="badge">Phase 3</span>
                </li>
                <li class="nav-section">Admin</li>
                <li class="nav-item">
                    <a href="benutzer.php">Benutzerverwaltung</a>
                </li>
                <li class="nav-item active">
                    <a href="einstellungen.php">Einstellungen</a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <a href="benutzer_edit.php" class="btn btn-small btn-secondary" style="margin-bottom:0.5rem">Mein Profil</a>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1>Einstellungen</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <form method="post" action="api/einstellungen_update.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="settings-section">
                    <h2>Eventdaten</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="renntag_datum">Renntag-Datum</label>
                            <input type="date" id="renntag_datum" name="renntag_datum" value="<?= htmlspecialchars($renntagDatum) ?>">
                        </div>
                        <div class="form-group">
                            <label for="veranstaltungsname">Veranstaltungsname</label>
                            <input type="text" id="veranstaltungsname" name="veranstaltungsname" value="<?= htmlspecialchars($veranstaltungsname) ?>" maxlength="200" placeholder="z.B. 10. Kirchseeoner Marktlauf">
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="kontakt_email">Kontakt-E-Mail</label>
                            <input type="email" id="kontakt_email" name="kontakt_email" value="<?= htmlspecialchars($kontaktEmail) ?>" placeholder="info@atsv-kirchseeon-marktlauf.de">
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Externe Links</h2>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="raceresult_url">Race-Result-URL</label>
                            <input type="url" id="raceresult_url" name="raceresult_url" value="<?= htmlspecialchars($raceresultUrl) ?>" placeholder="https://my.raceresult.com/...">
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="trello_board_url">Trello-Board-URL</label>
                            <input type="url" id="trello_board_url" name="trello_board_url" value="<?= htmlspecialchars($trelloUrl) ?>" placeholder="https://trello.com/b/...">
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="onedrive_url">Vereins-Cloud (OneDrive)</label>
                            <input type="url" id="onedrive_url" name="onedrive_url" value="<?= htmlspecialchars($onedriveUrl) ?>" placeholder="https://onedrive.live.com/...">
                        </div>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>

            <div class="settings-section">
                <h2>SMTP-Konfiguration</h2>
                <div class="info-block">
                    <dl>
                        <dt>Host</dt>
                        <dd><?= htmlspecialchars((string) $smtpHost) ?></dd>
                        <dt>Port</dt>
                        <dd><?= htmlspecialchars((string) $smtpPort) ?></dd>
                        <dt>From-Adresse</dt>
                        <dd><?= htmlspecialchars((string) $smtpFrom) ?></dd>
                    </dl>
                </div>
                <p class="info-hint">Änderungen nur über <code>storage/config.php</code></p>
            </div>
        </main>
    </div>
</body>
</html>
