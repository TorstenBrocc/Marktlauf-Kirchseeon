<?php
/**
 * Benutzer bearbeiten
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$currentUser = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$targetUserId = (int) ($_GET['id'] ?? $currentUser['id']);
$isSelf = $targetUserId === $currentUser['id'];

if (!$isAdmin && !$isSelf) {
    $_SESSION['flash_error'] = 'Keine Berechtigung.';
    header('Location: index.php');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $targetUserId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    $_SESSION['flash_error'] = 'Benutzer nicht gefunden.';
    header('Location: benutzer.php');
    exit;
}

$pageTitle = $isSelf ? 'Mein Profil' : 'Benutzer bearbeiten';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .form-container {
            max-width: 500px;
        }
        .form-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .form-card h2 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            color: #1565c0;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .role-info {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <header class="mobile-header">
        <button class="burger-btn" id="burger-btn" aria-label="Menü öffnen">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <h1>Orga-Dashboard</h1>
        <img src="../assets/images/logo-final.svg" alt="Marktlauf Logo" class="header-logo">
    </header>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="dashboard-layout">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Orga-Dashboard</h2>
                <img src="../assets/images/logo-final.svg" alt="Marktlauf Logo" class="header-logo">
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
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item <?= !$isSelf ? 'active' : '' ?>">
                    <a href="benutzer.php">Benutzerverwaltung</a>
                </li>
                <li class="nav-item">
                    <a href="einstellungen.php">Einstellungen</a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($currentUser['role'])) ?></span>
                </div>
                <a href="benutzer_edit.php" class="btn btn-small btn-secondary" style="margin-bottom:0.5rem">Mein Profil</a>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1><?= $pageTitle ?></h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <?php if ($isSelf): ?>
                <div class="info-box">
                    Du bearbeitest dein eigenes Profil.
                </div>
                <?php endif; ?>

                <form method="post" action="api/user_update.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="user_id" value="<?= $targetUserId ?>">

                    <div class="form-card">
                        <h2>Profildaten</h2>

                        <div class="form-group">
                            <label for="name" class="required">Name</label>
                            <input type="text" id="name" name="name" required
                                   value="<?= htmlspecialchars($targetUser['name']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">E-Mail</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= htmlspecialchars($targetUser['email']) ?>">
                        </div>

                        <?php if ($isAdmin && !$isSelf): ?>
                        <div class="form-group">
                            <label for="role">Rolle</label>
                            <select id="role" name="role">
                                <option value="orga" <?= $targetUser['role'] === 'orga' ? 'selected' : '' ?>>Orga</option>
                                <option value="admin" <?= $targetUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label>Rolle</label>
                            <input type="text" value="<?= ucfirst($targetUser['role']) ?>" disabled>
                            <?php if ($isSelf): ?>
                            <p class="role-info">Die eigene Rolle kann nicht geändert werden.</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                        <?php if ($isAdmin): ?>
                        <a href="benutzer.php" class="btn btn-secondary">Zurück zur Übersicht</a>
                        <?php else: ?>
                        <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    })();
    </script>
</body>
</html>
