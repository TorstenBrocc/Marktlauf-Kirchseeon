<?php
/**
 * Gemeinsame Dashboard-Navigation (Mobile-Header + Seitenleiste).
 *
 * Vor dem Include zu setzen:
 *   $user       array  eingeloggter Benutzer (Schlüssel 'name', 'role') — für die Fußzeile
 *   $isAdmin    bool   ob der Admin-Bereich (Benutzer, Einstellungen) angezeigt wird
 *   $activeNav  string aktiver Menüpunkt: 'dashboard'|'helfer'|'sponsoren'|'dateien'|'benutzer'|'einstellungen'|''
 *
 * Öffnet <div class="dashboard-layout"> — die einbindende Seite schließt es selbst.
 */
declare(strict_types=1);

$activeNav = $activeNav ?? '';
?>
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
                <li class="nav-item<?= $activeNav === 'dashboard' ? ' active' : '' ?>">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item<?= $activeNav === 'helfer' ? ' active' : '' ?>">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item<?= $activeNav === 'schichten' ? ' active' : '' ?>">
                    <a href="schichten.php">Einsatzplan</a>
                </li>
                <li class="nav-item<?= $activeNav === 'sponsoren' ? ' active' : '' ?>">
                    <a href="sponsoren.php">Sponsoren</a>
                </li>
                <li class="nav-item<?= $activeNav === 'sponsor_briefe' ? ' active' : '' ?>">
                    <a href="sponsor_briefe.php">Sponsorenbriefe</a>
                </li>
                <li class="nav-item<?= $activeNav === 'social_media' ? ' active' : '' ?>">
                    <a href="social_orchestrator.php">Social Media</a>
                </li>
                <li class="nav-item<?= $activeNav === 'dateien' ? ' active' : '' ?>">
                    <a href="dateien.php">Dateien</a>
                </li>
                <li class="nav-item<?= $activeNav === 'ci' ? ' active' : '' ?>">
                    <a href="ci.php">CI &amp; Design</a>
                </li>
                <li class="nav-item">
                    <span class="nav-disabled">Live-Ticker</span>
                    <span class="badge">Phase 3</span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item<?= $activeNav === 'benutzer' ? ' active' : '' ?>">
                    <a href="benutzer.php">Benutzerverwaltung</a>
                </li>
                <li class="nav-item<?= $activeNav === 'einstellungen' ? ' active' : '' ?>">
                    <a href="einstellungen.php">Einstellungen</a>
                </li>
                <?php endif; ?>
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
