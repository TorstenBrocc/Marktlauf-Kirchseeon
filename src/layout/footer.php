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
            <div class="footer-grid" style="justify-content: center;">
                <div class="footer-col">
                    <h4 data-i18n="footer.legal_title">Rechtliches</h4>
                    <div class="footer-links">
                        <a href="<?= $basePath ?>impressum.html" class="footer-link" data-i18n="footer.legal.impressum">Impressum</a>
                        <a href="<?= $basePath ?>datenschutz.html" class="footer-link" data-i18n="footer.legal.privacy">Datenschutz</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p data-i18n="footer.copyright">&copy; 2026 ATSV Kirchseeon e.V. | Alle Rechte vorbehalten.</p>
                <p class="footer-credits" data-i18n="footer.realized_by">Realisiert durch <a href="<?= $basePath ?>humans.txt" rel="author" class="footer-credits-link">Torsten Tyras</a></p>
            </div>
        </div>
    </footer>

    <script src="<?= $basePath ?>js/main.js"></script>
