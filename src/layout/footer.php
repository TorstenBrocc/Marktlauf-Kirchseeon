<?php
/**
 * Gemeinsamer Footer (Rechtliches + Copyright)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <footer class="main-footer">
        <div class="container">
            <div class="footer-bottom">
                <p class="footer-legal">
                    <a href="<?= $basePath ?>impressum.html" class="footer-link" data-i18n="footer.legal.impressum">Impressum</a>
                    <span class="footer-separator">|</span>
                    <a href="<?= $basePath ?>datenschutz.html" class="footer-link" data-i18n="footer.legal.privacy">Datenschutz</a>
                </p>
                <p data-i18n="footer.copyright">&copy; 2026 ATSV Kirchseeon e.V. | Alle Rechte vorbehalten.</p>
                <p class="footer-credits">Konzeption und Realisierung: <a href="<?= $basePath ?>humans.txt" rel="author" class="footer-credits-link">Torsten Tyras</a></p>
            </div>
        </div>
    </footer>

    <script src="<?= $basePath ?>js/main.js"></script>
