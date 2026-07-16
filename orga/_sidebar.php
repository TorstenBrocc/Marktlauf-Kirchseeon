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
$navItems  = require __DIR__ . '/_nav.php';

/**
 * Ein Navigations-Item rendern (Link oder deaktivierter Hinweis inkl. Badge).
 */
$renderNavItem = static function (array $item, string $activeNav): void {
    $isActive = ($item['key'] ?? '') === $activeNav;
    $badge    = isset($item['badge'])
        ? ' <span class="badge">' . htmlspecialchars($item['badge']) . '</span>'
        : '';
    if (empty($item['href'])) {
        echo '<li class="nav-item">'
            . '<span class="nav-disabled">' . htmlspecialchars($item['label']) . '</span>'
            . $badge . '</li>';
        return;
    }
    echo '<li class="nav-item' . ($isActive ? ' active' : '') . '">'
        . '<a href="' . htmlspecialchars($item['href']) . '">' . htmlspecialchars($item['label']) . $badge . '</a>'
        . '</li>';
};
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
                <?php
                // Sektions-Überschriften werden lazy ausgegeben: erst wenn das erste
                // tatsächlich sichtbare Item eines neuen Abschnitts gerendert wird.
                // So entsteht keine leere Überschrift, wenn alle Items eines Abschnitts
                // (z. B. Admin-Werkzeuge für Nicht-Admins) weggefiltert wurden.
                $lastSection = null;
                foreach ($navItems as $item) {
                    if (!empty($item['admin']) && !$isAdmin) {
                        continue; // Admin-Werkzeuge nur für Admins
                    }
                    $section = $item['section'] ?? '';
                    if ($section !== $lastSection) {
                        if ($section !== '') {
                            echo '<li class="nav-section">' . htmlspecialchars($section) . '</li>';
                        }
                        $lastSection = $section;
                    }
                    $renderNavItem($item, $activeNav);
                }
                ?>
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
