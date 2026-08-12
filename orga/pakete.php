<?php
/**
 * Sponsoring-Pakete — die eine Pflegestelle (TT 2026-08-12: „zusammenklickbar an einer Stelle").
 *
 * Oben die Preise je Paket, darunter der Leistungs-Katalog: je Position, ab welcher Paketstufe
 * sie gilt, ob sie in dieser Saison angeboten wird und — bei Startplätzen — die Stückzahl je Stufe.
 * Aus genau diesen Daten entstehen die Highlights im Sponsorenbrief; es gibt keinen zweiten
 * Pflegeort mehr. Ganz unten die erzeugte Vorschau je Paket zum Gegenlesen.
 *
 * Alles speichert inline per AJAX (`api/paket_crud.php`), Muster wie die Leistungs-Matrix.
 * Details: intern/sponsoring-modell-spec.md §d.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_leistungen.php';
require_once __DIR__ . '/../src/rechnung.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$pakete  = sponsoringPakete($pdo);
// Inaktive bewusst mit: eine abgeschaltete Position muss hier wieder einschaltbar sein.
$katalog = sponsorLeistungenKatalog(true);
$katalogAusDb = sponsorLeistungenKatalogAusDb() !== null;

$stufen = ['bronze' => 'Bronze', 'silber' => 'Silber', 'gold' => 'Gold', 'hauptsponsor' => 'Hauptsponsor'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sponsoring-Pakete | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .pk-card { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .pk-card h2 { font-size: 1rem; margin: 0 0 0.35rem; }
        .pk-intro { font-size: 0.88rem; color: var(--text-light); line-height: 1.55; margin: 0 0 1rem; }
        .pk-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .pk-table th, .pk-table td { border-bottom: 1px solid var(--border); padding: 0.45rem 0.5rem; text-align: left; vertical-align: middle; }
        .pk-table thead th { background: var(--bg); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-light); font-weight: 600; }
        .pk-table td.pk-num, .pk-table th.pk-num { text-align: center; width: 5.5rem; }
        .pk-inp { padding: 0.3rem 0.4rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; box-sizing: border-box; width: 100%; }
        .pk-inp.pk-mini { text-align: center; }
        .pk-sel { padding: 0.3rem 0.4rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; background: var(--white); }
        .pk-save { outline: 2px solid var(--primary); outline-offset: 1px; }
        .pk-inaktiv td { opacity: 0.5; }
        .pk-hinweis { font-size: 0.82rem; color: var(--text); background: rgba(255,193,7,0.15); border: 1px solid rgba(255,193,7,0.55); border-radius: 6px; padding: 0.6rem 0.8rem; margin: 0 0 1rem; line-height: 1.5; }
        .pk-vorschau { font-size: 0.85rem; line-height: 1.6; }
        .pk-vorschau dt { font-weight: 600; margin-top: 0.6rem; }
        .pk-vorschau dd { margin: 0.1rem 0 0; color: var(--text-light); }
        .pk-frei { font-size: 0.78rem; color: var(--text-light); font-style: italic; }
    </style>
</head>
<body>
<?php $activeNav = 'pakete'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Sponsoring-Pakete</h1>
            </header>

            <?php if (!$katalogAusDb): ?>
            <p class="pk-hinweis">
                ⚠️ Die Tabelle <code>leistungs_katalog</code> ist noch nicht angelegt — angezeigt wird
                der Code-Stand, Änderungen am Katalog greifen noch nicht. Migration 059 fahren.
            </p>
            <?php endif; ?>

            <div class="pk-card">
                <h2>Preise</h2>
                <p class="pk-intro">
                    Der Preis je Paket, wie er in der Pakettabelle im Sponsorenbrief steht
                    (<code>{{paket_tabelle}}</code>) und wie ihn die Abrechnung vorbelegt. Ganze Euro.
                    „auf Anfrage" beim Hauptsponsor bleibt zulässig — das ist kein abrechenbarer Preis.
                </p>
                <table class="pk-table">
                    <thead><tr><th style="width:12rem">Paket</th><th>Investition</th></tr></thead>
                    <tbody>
                    <?php foreach ($pakete as $key => $p): ?>
                        <tr>
                            <td style="font-weight:600"><?= htmlspecialchars((string) ($p['name'] ?? $key)) ?></td>
                            <td>
                                <input type="text" class="pk-inp" style="max-width:16rem"
                                       data-feld="investition" data-paket="<?= htmlspecialchars((string) $key) ?>"
                                       value="<?= htmlspecialchars((string) ($p['investition'] ?? '')) ?>" maxlength="60">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pk-card">
                <h2>Leistungen je Paket</h2>
                <p class="pk-intro">
                    Hier wird das Paket zusammengeklickt: Ab welcher Stufe gilt eine Leistung? Die
                    Zuordnung ist <strong>kumulativ</strong> — was ab Bronze gilt, ist auch in Silber,
                    Gold und beim Hauptsponsor enthalten. <strong>Angeboten</strong> abwählen nimmt eine
                    Position für diese Saison komplett heraus (Matrix, Brief, Rechnung), ohne sie zu
                    löschen. Die Stückzahlen gelten nur für Startplätze; leer heißt „individuell".
                </p>
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th>Leistung</th>
                            <th style="width:9rem">gilt ab</th>
                            <th class="pk-num">Bronze</th>
                            <th class="pk-num">Silber</th>
                            <th class="pk-num">Gold</th>
                            <th class="pk-num">angeboten</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($katalog as $pos):
                        $aktiv = ($pos['aktiv'] ?? true) !== false;
                        $istMenge = $pos['typ'] === 'startplaetze'; ?>
                        <tr class="<?= $aktiv ? '' : 'pk-inaktiv' ?>">
                            <td>
                                <input type="text" class="pk-inp"
                                       data-feld="label" data-key="<?= htmlspecialchars($pos['key']) ?>"
                                       value="<?= htmlspecialchars($pos['label']) ?>" maxlength="120">
                            </td>
                            <td>
                                <select class="pk-sel" data-feld="min_stufe" data-key="<?= htmlspecialchars($pos['key']) ?>">
                                    <?php foreach ($stufen as $sk => $sl): ?>
                                        <option value="<?= $sk ?>" <?= $pos['min'] === $sk ? 'selected' : '' ?>><?= $sl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php foreach (['bronze', 'silber', 'gold'] as $stufe): ?>
                                <td class="pk-num">
                                    <?php if ($istMenge): ?>
                                        <input type="number" min="0" class="pk-inp pk-mini"
                                               data-feld="menge_<?= $stufe ?>" data-key="<?= htmlspecialchars($pos['key']) ?>"
                                               value="<?= isset($pos['menge'][$stufe]) ? (int) $pos['menge'][$stufe] : '' ?>"
                                               placeholder="–">
                                    <?php else: ?>
                                        <span class="pk-frei">–</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="pk-num">
                                <input type="checkbox" data-feld="aktiv" data-key="<?= htmlspecialchars($pos['key']) ?>"
                                       <?= $aktiv ? 'checked' : '' ?> style="accent-color:var(--primary);width:16px;height:16px;cursor:pointer">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pk-card">
                <h2>So steht es im Brief</h2>
                <p class="pk-intro">
                    Die Highlights-Spalte der Pakettabelle wird aus dem Katalog oben erzeugt — jede
                    Stufe zeigt, was sie <strong>gegenüber der darunter</strong> bringt. Nach einer
                    Änderung neu laden.
                </p>
                <dl class="pk-vorschau">
                    <?php foreach ($pakete as $key => $p): ?>
                        <dt><?= htmlspecialchars((string) ($p['name'] ?? $key)) ?></dt>
                        <dd>
                            <?= htmlspecialchars((string) ($p['highlights'] ?? '')) ?>
                            <?php if (sponsorPaketHighlights((string) $key) === ''): ?>
                                <span class="pk-frei">— individuell, kommt nicht aus dem Katalog</span>
                            <?php endif; ?>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            </div>

        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;

        function save(el, daten) {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('feld', el.dataset.feld);
            Object.keys(daten).forEach(function(k) { body.set(k, daten[k]); });
            el.classList.add('pk-save');
            fetch('api/paket_crud.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    setTimeout(function() { el.classList.remove('pk-save'); }, d && d.ok ? 400 : 0);
                    if (!d || !d.ok) { alert('Speichern fehlgeschlagen.'); }
                })
                .catch(function() { el.classList.remove('pk-save'); alert('Speichern fehlgeschlagen.'); });
        }

        document.querySelectorAll('[data-feld="investition"]').forEach(function(el) {
            el.addEventListener('change', function() { save(el, { paket: el.dataset.paket, wert: el.value }); });
        });
        document.querySelectorAll('[data-key]').forEach(function(el) {
            const ereignis = el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'change';
            el.addEventListener(ereignis, function() {
                const wert = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
                save(el, { key: el.dataset.key, wert: wert });
            });
        });
    })();

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
