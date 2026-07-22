<?php
/**
 * Minimaler Header (nur Logo, kein Menu)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <header class="main-header">
        <div class="container nav-container">
            <a href="<?= $basePath ?>index.html" class="logo-plakette">
                <img src="<?= $basePath ?>assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png" alt="Marktlauf Kirchseeon" class="logo-wordmark">
                <span class="logo-divider"></span>
                <img src="<?= $basePath ?>assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon 1906" class="logo-atsv">
            </a>
            <nav class="nav-links">
                <a href="<?= $basePath ?>index.html" class="nav-link">Zur Startseite</a>
            </nav>
        </div>
    </header>
