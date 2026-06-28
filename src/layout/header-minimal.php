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
            <div class="header-logos">
                <a href="<?= $basePath ?>index.html" class="nav-logo">
                    <img src="<?= $basePath ?>assets/images/Marktlauf%20Logo%20Schrift.png" alt="ATSV Marktlauf Kirchseeon Logo" class="logo-img marktlauf-logo">
                </a>
                <a href="https://www.atsv-kirchseeon.de/" target="_blank" class="nav-logo-atsv">
                     <img src="<?= $basePath ?>assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon e.V. Logo" class="logo-img atsv-logo">
                </a>
            </div>
            <nav class="nav-links">
                <a href="<?= $basePath ?>index.html" class="nav-link">Zur Startseite</a>
            </nav>
        </div>
    </header>
