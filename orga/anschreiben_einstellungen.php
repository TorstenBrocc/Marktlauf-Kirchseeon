<?php
/**
 * Anschreiben-Einstellungen — die Werte hinter den Platzhaltern, an einem Ort.
 *
 * Zwei Teile (Spec §5):
 *   1. Einstellbar: Termine (Event-Datum, Rückmeldefrist) + Sponsoring-Pakete.
 *      Lag vorher unten in `sponsor_briefe.php`, obwohl es global für ALLE Vorlagen gilt —
 *      man hätte künftig das Erstanschreiben öffnen müssen, um Werte für die Bestätigung
 *      zu pflegen.
 *   2. Aufschlüsselung: was jeder Platzhalter einsetzt und woher der Wert kommt. Die
 *      Beschreibungen stecken heute nur im title-Attribut der Chips und sind auf dem Handy
 *      gar nicht erreichbar; hier stehen sie vollständig.
 *
 * Die Texte werden aus sponsorBriefPlatzhalterHilfe() gerendert, nicht abgetippt — eine
 * Quelle für Chips und Tabelle.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_brief.php';

$user      = getCurrentUserFromGuard();
$isAdmin   = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();

$briefSettings = [];
try {
    // `sponsoring_pakete` wird hier NICHT mehr geladen — die Pakete kommen über sponsorBriefPakete().
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('sponsor_brief_event_datum','sponsor_brief_antwort_bis')");
    while ($row = $stmt->fetch()) { $briefSettings[$row['key']] = $row['value']; }
} catch (PDOException $e) {}

$briefEventDatum = $briefSettings['sponsor_brief_event_datum'] ?? '';
$briefAntwortBis = $briefSettings['sponsor_brief_antwort_bis'] ?? '';

// Beide Platzhalter-Sätze: der allgemeine (alle Anschreiben) und der der Rechnungs-Begleitmail.
$phAllgemein = sponsorBriefPlatzhalterHilfe();
$phRechnung  = array_diff_key(sponsorBriefPlatzhalterHilfe('rechnung'), $phAllgemein);
$phQuellen   = sponsorBriefPlatzhalterQuelle();

/** Eine Platzhalter-Zeile rendern (Beschreibung bleibt mehrzeilig lesbar). */
$phZeile = static function (string $ph, string $beschreibung) use ($phQuellen): void {
    $q = $phQuellen[$ph] ?? ['quelle' => '—', 'ziel' => ''];
    echo '<tr>';
    echo '<td class="ph-name"><code>' . htmlspecialchars($ph) . '</code></td>';
    echo '<td class="ph-text">' . nl2br(htmlspecialchars($beschreibung)) . '</td>';
    echo '<td class="ph-quelle">';
    echo $q['ziel'] !== ''
        ? '<a href="' . htmlspecialchars($q['ziel']) . '">' . htmlspecialchars($q['quelle']) . ' →</a>'
        : htmlspecialchars($q['quelle']);
    echo '</td>';
    echo '</tr>';
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Einstellungen (Anschreiben) | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .einst-intro { font-size: 0.9rem; line-height: 1.6; margin: 0 0 1rem; }
        .einst-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
        @media (max-width: 700px) { .einst-grid { grid-template-columns: 1fr; } }
        .einst-grid label { display: block; font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.35rem; }
        .einst-grid input[type=date] { padding: 0.4rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; width: 100%; box-sizing: border-box; }
        .paket-tabelle { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-bottom: 1rem; }
        .paket-tabelle th { text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--border); background: var(--bg); }
        .paket-tabelle td { padding: 0.4rem 0.5rem; vertical-align: top; }
        .paket-tabelle input { width: 100%; padding: 0.35rem; border: 1px solid var(--border); border-radius: 4px; box-sizing: border-box; }
        .ph-tabelle { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .ph-tabelle th { text-align: left; padding: 0.5rem; border-bottom: 1px solid var(--border); background: var(--bg); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-light); }
        .ph-tabelle td { padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--border); vertical-align: top; line-height: 1.55; }
        .ph-tabelle tr:last-child td { border-bottom: none; }
        .ph-name { white-space: nowrap; }
        .ph-name code { font-size: 0.8rem; background: var(--bg); padding: 0.15rem 0.4rem; border-radius: 4px; }
        .ph-quelle { color: var(--text-light); min-width: 11rem; }
        /* Vertikal lesbar halten: bei schmalen Schirmen bricht die Tabelle in Blöcke um,
           statt horizontal zu scrollen. */
        @media (max-width: 760px) {
            .ph-tabelle thead { display: none; }
            .ph-tabelle tr { display: block; border-bottom: 1px solid var(--border); padding: 0.6rem 0; }
            .ph-tabelle td { display: block; border: none; padding: 0.15rem 0; }
            .ph-quelle::before { content: "Quelle: "; font-weight: 600; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'anschreiben_einstellungen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Einstellungen (Anschreiben)</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <form method="post" action="api/sponsor_brief_settings_save.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="brief-card">
                    <h2 style="font-size:1rem;margin:0 0 0.5rem">Termine</h2>
                    <p class="einst-intro">
                        Diese beiden Daten setzen die Platzhalter <code>{{event_datum}}</code> und
                        <code>{{antwort_bis}}</code> — in <strong>allen</strong> Anschreiben.
                    </p>
                    <div class="einst-grid">
                        <div>
                            <label for="event_datum">Event-Datum <code>{{event_datum}}</code></label>
                            <input type="date" id="event_datum" name="sponsor_brief_event_datum"
                                   value="<?= htmlspecialchars($briefEventDatum) ?>">
                        </div>
                        <div>
                            <label for="antwort_bis">Rückmeldefrist <code>{{antwort_bis}}</code></label>
                            <input type="date" id="antwort_bis" name="sponsor_brief_antwort_bis"
                                   value="<?= htmlspecialchars($briefAntwortBis) ?>">
                        </div>
                    </div>
                </div>

                <div class="brief-card">
                    <h2 style="font-size:1rem;margin:0 0 0.5rem">
                        Sponsoring-Pakete <code style="font-size:0.8rem">{{paket_tabelle}}</code>
                    </h2>
                    <p class="einst-intro">
                        Preise und Leistungen der Pakete werden seit 2026-08-12 an einer Stelle
                        gepflegt: <a href="pakete.php">Sponsoring-Pakete</a>. Von dort speist sich
                        <code>{{paket_tabelle}}</code> im Brief — und dieselben Daten treiben die
                        Leistungs-Matrix und die Abrechnung. Zwei Pflegeorte für dieselbe Zahl gibt
                        es hier bewusst nicht mehr.
                    </p>

                    <div class="brief-actions" style="margin-top:0">
                        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
                    </div>
                </div>
            </form>

            <div class="brief-card">
                <h2 style="font-size:1rem;margin:0 0 0.5rem">Was steckt hinter den Platzhaltern?</h2>
                <p class="einst-intro">
                    Ein Platzhalter wird beim Versand durch einen echten Wert ersetzt. Bleibt einer leer,
                    fehlt der Wert an seiner Quelle — die Spalte rechts führt direkt dorthin.
                </p>
                <table class="ph-tabelle">
                    <thead>
                        <tr>
                            <th>Platzhalter</th>
                            <th>Was er einsetzt</th>
                            <th>Woher der Wert kommt</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($phAllgemein as $ph => $beschreibung) { $phZeile($ph, $beschreibung); } ?>
                    </tbody>
                </table>
            </div>

            <?php if ($phRechnung): ?>
            <div class="brief-card">
                <h2 style="font-size:1rem;margin:0 0 0.5rem">Nur in der Rechnungs-Begleitmail</h2>
                <p class="einst-intro">
                    Diese Platzhalter kommen aus dem Rechnungsdatensatz und stehen deshalb nur in der
                    Begleitmail unter <a href="rechnungen.php">(Ab-)Rechnungen</a> zur Verfügung.
                </p>
                <table class="ph-tabelle">
                    <thead>
                        <tr>
                            <th>Platzhalter</th>
                            <th>Was er einsetzt</th>
                            <th>Woher der Wert kommt</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($phRechnung as $ph => $beschreibung) { $phZeile($ph, $beschreibung); } ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

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
