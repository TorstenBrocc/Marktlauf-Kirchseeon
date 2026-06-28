<?php
/**
 * Orga Dashboard Startseite
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$helferCount = (int) $pdo->query('SELECT COUNT(*) FROM helfer')->fetchColumn();
$helferNeuCount = (int) $pdo->query("SELECT COUNT(*) FROM helfer WHERE status = 'neu'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Marktlauf Orga</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item">
                    <a href="sponsoren.php">Sponsoren</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="dateien.php">Dateien</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="ticker.php">Live-Ticker</a>
                    <span class="badge">Phase 3</span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item">
                    <a href="users.php">Benutzerverwaltung</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="settings.php">Einstellungen</a>
                    <span class="badge">Phase 2</span>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1>Dashboard</h1>
            </header>

            <section class="dashboard-grid">
                <article class="card">
                    <h3>Helfer-Anmeldungen</h3>
                    <p class="card-stat"><?= $helferCount ?></p>
                    <p class="card-label">Anmeldungen eingegangen<?= $helferNeuCount > 0 ? " ({$helferNeuCount} neu)" : '' ?></p>
                    <a href="helfer.php" class="btn btn-small btn-primary" style="margin-top:0.5rem">Zur Übersicht</a>
                </article>

                <article class="card">
                    <h3>Sponsoren</h3>
                    <p class="card-stat">—</p>
                    <p class="card-label">Sponsoren erfasst</p>
                    <p class="card-note">Mini-CRM folgt in Phase 2</p>
                </article>

                <article class="card">
                    <h3>Schnellzugriff</h3>
                    <ul class="quick-links">
                        <li><a href="../helfer-anmeldung.php" target="_blank">Helfer-Formular (öffentlich)</a></li>
                        <li><a href="https://www.raceresult.com/de-de/account/index" target="_blank" rel="noopener">Race Result Account</a></li>
                    </ul>
                </article>

                <?php if ($isAdmin): ?>
                <article class="card card-admin">
                    <h3>Admin-Bereich</h3>
                    <ul class="quick-links">
                        <li><a href="users.php">Benutzer verwalten</a> <span class="badge">Phase 2</span></li>
                        <li><a href="settings.php">Systemeinstellungen</a> <span class="badge">Phase 2</span></li>
                    </ul>
                </article>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
