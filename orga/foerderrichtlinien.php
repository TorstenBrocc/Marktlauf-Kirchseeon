<?php
/**
 * Förderrichtlinien — read-only Referenz für Admins.
 *
 * Self-contained Zusammenfassung der laufenden Förder-/Programm-Compliance (aktuell:
 * Google Ad Grants). Die kanonische, ausführliche Quelle ist das Runbook im Vereins-Vault
 * (45_verein/foerderung/google-ad-grants.md); diese Seite spiegelt die operativen
 * Essentials dort, wo die Orga arbeitet. Inhalt an Google-Primärquellen gegroundet
 * (Links unten). Vanilla, self-hosted, keine externen Ressourcen.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Förderrichtlinien des ATSV Kirchseeon Marktlauf — Compliance-Regeln laufender Förderprogramme als Admin-Referenz.">
    <title>Förderrichtlinien | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="stylesheet" href="../css/fonts.css?v=<?= @filemtime(__DIR__ . '/../css/fonts.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .fr-intro { color: var(--text-light); max-width: 68ch; margin: 0 0 1.5rem; font-size: 0.9rem; line-height: 1.55; }
        .fr-card {
            background: #fff; border: 1px solid var(--border, #e5e7eb); border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 1.25rem 1.4rem; margin: 0 0 1.25rem;
        }
        .fr-card h2 { margin: 0 0 0.25rem; font-size: 1.15rem; }
        .fr-card h3 { margin: 1.1rem 0 0.4rem; font-size: 0.95rem; color: #007230; }
        .fr-sub { color: var(--text-light); font-size: 0.85rem; margin: 0 0 0.75rem; }
        .fr-card ul { margin: 0.25rem 0 0; padding-left: 1.2rem; }
        .fr-card li { margin: 0.3rem 0; line-height: 1.5; font-size: 0.9rem; }
        .fr-facts { list-style: none; padding: 0; margin: 0; }
        .fr-facts li { display: flex; flex-wrap: wrap; gap: 0.4rem; border-bottom: 1px solid #f0f0f0; padding: 0.4rem 0; }
        .fr-facts li:last-child { border-bottom: 0; }
        .fr-facts .k { flex: 0 0 190px; color: var(--text-light); font-size: 0.85rem; }
        .fr-facts .v { flex: 1 1 220px; font-size: 0.9rem; }
        .fr-table-wrap { overflow-x: auto; }
        table.fr-table { border-collapse: collapse; width: 100%; font-size: 0.85rem; min-width: 520px; }
        table.fr-table th, table.fr-table td { text-align: left; vertical-align: top; padding: 0.5rem 0.6rem; border-bottom: 1px solid #eee; }
        table.fr-table th { color: var(--text-light); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.03em; }
        code { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; background: #eef2f0; padding: 0.1em 0.4em; border-radius: 4px; font-size: 0.85em; }
        .fr-badge { display: inline-block; font-size: 0.72rem; padding: 0.15em 0.55em; border-radius: 999px; background: #fff4e5; color: #9a5b00; border: 1px solid #ffd9a8; }
        .fr-badge.ok { background: #e7f6ec; color: #14663a; border-color: #b7e2c6; }
        .fr-links a { color: #007230; }
        .fr-note { font-size: 0.82rem; color: var(--text-light); margin-top: 0.75rem; }
    </style>
</head>
<body>
<?php $activeNav = 'foerderrichtlinien'; require __DIR__ . '/_sidebar.php'; ?>
        <main class="main-content">
            <header class="content-header">
                <h1>Förderrichtlinien</h1>
            </header>

            <p class="fr-intro">
                Kompakte Admin-Referenz für die Compliance laufender Förderprogramme. Die
                ausführliche, kanonische Quelle liegt im Vereins-Vault
                (<code>45_verein/foerderung/google-ad-grants.md</code>); diese Seite spiegelt die
                operativen Essentials. Alle Regeln sind an den offiziellen Google-Quellen gegroundet
                (Links unten). Stand: 18.08.2026.
            </p>

            <section class="fr-card">
                <h2>Google Ad Grants <span class="fr-badge">Tracking live · Grant-Aktivierung offen</span></h2>
                <p class="fr-sub">
                    Kostenloses Werbebudget für Non-Profits: bis zu <strong>10.000&nbsp;USD/Monat</strong>
                    (~329&nbsp;USD/Tag) für Textanzeigen in der Google-Suche. Kein Geld an den Verein,
                    sondern freigeschaltetes Anzeigenbudget — treibt Suchanfragen auf die Anmeldeseite.
                </p>

                <h3>Unsere Einrichtung</h3>
                <ul class="fr-facts">
                    <li><span class="k">GA4-Property</span><span class="v">„Marktlauf Kirchseeon" (Konto „ATSV Kirchseeon", Zeitzone DE, EUR)</span></li>
                    <li><span class="k">Mess-ID</span><span class="v"><code>G-F04JYXVLT7</code> (in <code>js/consent.js</code>)</span></li>
                    <li><span class="k">Consent</span><span class="v">eigener Banner + Consent Mode v2, strikt Opt-in; IP-Anonymisierung aktiv</span></li>
                    <li><span class="k">Conversions</span><span class="v"><code>anmeldung_start</code>, <code>newsletter_confirmed</code>, <code>contact_sent</code> — in GA4 Realtime bestätigt</span></li>
                </ul>

                <h3>Anforderungen zum Behalten (Verstoß → temporäre Deaktivierung)</h3>
                <div class="fr-table-wrap">
                    <table class="fr-table">
                        <thead><tr><th>Regel</th><th>Genau</th></tr></thead>
                        <tbody>
                            <tr><td>≥ 1 Conversion/Monat</td><td>echte Conversions (Klicks ≉ Conversions)</td></tr>
                            <tr><td>≥ 5&nbsp;% CTR/Monat</td><td>Kontoebene; 2 Monate in Folge darunter → Deaktivierung</td></tr>
                            <tr><td>Keywords</td><td>keine Einzelwort-/„generic" Keywords; Quality Score ≥ 3 (QS 1–2 pausieren)</td></tr>
                            <tr><td>Smart Bidding</td><td>conversion-basiert Pflicht (Maximize conversions / tCPA / tROAS); hebt 2-USD-CPC-Limit auf</td></tr>
                            <tr><td>Struktur</td><td>≥ 2 Anzeigengruppen/Kampagne, ≥ 2 Sitelinks, Geo-Targeting Bayern/Ebersberg</td></tr>
                            <tr><td>Programm-Umfrage</td><td>periodische Google-Umfrage beantworten (Pflicht)</td></tr>
                            <tr><td>Website</td><td>eigene Domain, komplett HTTPS, missionsfokussiert, kein AdSense/Affiliate-Hauptzweck</td></tr>
                        </tbody>
                    </table>
                </div>

                <h3>Aktivierung (Owner-Aktion im Google-Konto)</h3>
                <ul>
                    <li><strong>Kein eigenes Google-Ads-Konto anlegen</strong> — das Grant-Konto stellt Google bei der Aktivierung selbst bereit.</li>
                    <li>Google for Nonprofits → Google Ad Grants → „Get started", Website eintragen (HTTPS ✓).</li>
                    <li>Einführungsvideo ansehen, Bestätigung setzen, „Submit activation request" (Prüfung einige Werktage).</li>
                    <li>Nach Freigabe: GA4-Conversions ins Ads-Konto importieren, regelkonforme Kampagne bauen.</li>
                </ul>

                <h3>Monatliche Checkliste</h3>
                <ul>
                    <li>≥ 1 valide Conversion · CTR ≥ 5&nbsp;%</li>
                    <li>Keyword-Hygiene (keine Einzelwort/„generic", QS-1/2 pausieren)</li>
                    <li>Smart Bidding aktiv · Programm-Umfragen beantworten</li>
                </ul>

                <h3>Offene Punkte</h3>
                <ul>
                    <li><strong>Schlüsselereignisse markieren</strong> — GA4 → Verwaltung → Datenanzeige → Ereignisse → „Letzte Ereignisse" → Stern bei <code>anmeldung_start</code>, <code>contact_sent</code>, <code>newsletter_confirmed</code>. Erst möglich, wenn die Events in der Admin-Liste stehen (GA4-Verarbeitung ≤ 24&nbsp;h).</li>
                    <li><strong>Grant aktivieren</strong> (Schritte oben) und Kampagne aufbauen.</li>
                </ul>

                <p class="fr-links fr-note">
                    <strong>Offizielle Quellen:</strong>
                    <a href="https://support.google.com/nonprofits/answer/9314402" target="_blank" rel="noopener">Policy Compliance Guide</a> ·
                    <a href="https://support.google.com/grants/answer/117827" target="_blank" rel="noopener">Account-Policy</a> ·
                    <a href="https://support.google.com/nonprofits/answer/1332166" target="_blank" rel="noopener">Budget &amp; Bidding</a> ·
                    <a href="https://support.google.com/nonprofits/answer/6077350" target="_blank" rel="noopener">Aktivierung</a> ·
                    <a href="https://www.google.com/grants/faq/" target="_blank" rel="noopener">Programm-FAQ</a>
                </p>
                <p class="fr-note">
                    Kanonische, ausführliche Quelle: Runbook <code>45_verein/foerderung/google-ad-grants.md</code> im Vereins-Vault
                    (<a class="fr-links" href="https://vault.coreone.space" target="_blank" rel="noopener">vault.coreone.space</a>, Zugang vorausgesetzt).
                </p>
            </section>
        </main>
    </div>

    <script>
    (function () {
        // Burger-Menü (identisch zu den anderen Dashboard-Seiten).
        var burger  = document.getElementById('burger-btn');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
        if (burger && sidebar && overlay) {
            burger.addEventListener('click', function () {
                sidebar.classList.add('open');
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
            overlay.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('.nav-item a').forEach(function (link) {
                link.addEventListener('click', closeSidebar);
            });
        }
    })();
    </script>
</body>
</html>
