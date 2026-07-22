<?php
/**
 * Gemeinsamer Header (Navigation)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <header class="main-header">
        <div class="container nav-container">
            <div class="header-logos">
                <a href="<?= $basePath ?>index.html" class="nav-logo">
                    <img src="<?= $basePath ?>assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png" alt="ATSV Marktlauf Kirchseeon Logo" class="logo-img marktlauf-logo">
                </a>
                <a href="https://www.atsv-kirchseeon.de/" target="_blank" class="nav-logo-atsv">
                     <img src="<?= $basePath ?>assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon e.V. Logo" class="logo-img atsv-logo">
                </a>
            </div>

            <button class="mobile-menu-toggle" aria-label="Menü öffnen">
                <span class="burger-icon"></span>
            </button>

            <nav class="nav-links">
                <a href="<?= $basePath ?>index.html#distanzen" class="nav-link" data-i18n="nav.laeufe">Läufe</a>
                <a href="<?= $basePath ?>index.html#ablauf" class="nav-link" data-i18n="nav.zeitplan">Zeitplan</a>
                <a href="<?= $basePath ?>index.html#strecke" class="nav-link" data-i18n="nav.strecke">Strecke</a>
                <a href="<?= $basePath ?>index.html#sponsoren" class="nav-link" data-i18n="nav.sponsoren">Sponsoren</a>
                <a href="<?= $basePath ?>index.html#connect" class="nav-link" data-i18n="nav.kontakt">Newsletter & Kontakt</a>
                <div class="lang-switcher">
                    <button id="lang-toggle" class="lang-btn" title="Sprache wechseln">
                        <img src="<?= $basePath ?>assets/images/allemand.png" alt="Deutsch" id="lang-flag" class="flag-icon">
                    </button>
                </div>
                <a href="<?= $basePath ?>index.html#anmeldung" class="nav-link nav-cta" data-i18n="nav.anmeldung">Jetzt anmelden</a>
            </nav>
        </div>
    </header>
