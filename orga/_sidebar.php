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
$renderNavItem = static function (array $item, string $activeNav, int $indent = 0): void {
    $isActive = ($item['key'] ?? '') === $activeNav;
    $indentClass = $indent > 0 ? ' nav-item-sub' . ($indent > 1 ? ' nav-item-sub-2' : '') : '';
    $liClass  = 'nav-item' . $indentClass . ($isActive ? ' active' : '');
    $badge    = isset($item['badge'])
        ? ' <span class="badge">' . htmlspecialchars($item['badge']) . '</span>'
        : '';
    if (empty($item['href'])) {
        echo '<li class="' . $liClass . '">'
            . '<span class="nav-disabled">' . htmlspecialchars($item['label']) . '</span>'
            . $badge . '</li>';
        return;
    }
    echo '<li class="' . $liClass . '">'
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
                // Gruppen- und Sektions-Überschriften werden lazy ausgegeben: erst wenn das
                // erste tatsächlich sichtbare Item einer neuen Ebene gerendert wird. So
                // entsteht keine leere Überschrift, wenn alle Items darunter (z. B.
                // Admin-Werkzeuge für Nicht-Admins) weggefiltert wurden.
                $lastGroup   = null;
                $lastSection = null;
                foreach ($navItems as $item) {
                    if (!empty($item['admin']) && !$isAdmin) {
                        continue; // Admin-Werkzeuge nur für Admins
                    }
                    $group   = $item['group'] ?? '';
                    $section = $item['section'] ?? '';
                    if ($group !== $lastGroup) {
                        if ($group !== '') {
                            echo '<li class="nav-group">' . htmlspecialchars($group) . '</li>';
                        }
                        $lastGroup   = $group;
                        $lastSection = null; // Abschnitt in der neuen Gruppe neu ausgeben
                    }
                    if ($section !== $lastSection) {
                        if ($section !== '') {
                            echo '<li class="nav-section' . ($group !== '' ? ' nav-section-sub' : '') . '">'
                                . htmlspecialchars($section) . '</li>';
                        }
                        $lastSection = $section;
                    }
                    // Einrückung wächst mit der Tiefe: Items unter einer Abschnitts-Überschrift
                    // hängen optisch darunter, Items in einer Gruppe eine Stufe tiefer
                    // (Dashboard ohne beides bleibt bündig).
                    $renderNavItem($item, $activeNav, ($section !== '' ? 1 : 0) + ($group !== '' ? 1 : 0));
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
