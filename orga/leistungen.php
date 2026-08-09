<?php
/**
 * Leistungs-Matrix — welche Leistungen sind je Sponsor vereinbart und zu erbringen.
 * Zeilen: zugesagte/bestätigte/abgerechnete Sponsoren mit Typ. Spalten: Katalog-Positionen (kumulativ
 * aus dem Typ vorbelegt, pro Sponsor an-/abwählbar). Haken = vereinbart (nicht „erledigt").
 * Details: intern/sponsoring-modell-spec.md §c.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_leistungen.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$katalog = sponsorLeistungenKatalog();

$sponsoren = [];
try {
    $stmt = $pdo->query("SELECT id, firma, paket FROM sponsors
        WHERE paket IS NOT NULL AND paket <> '' AND status IN ('zugesagt','bestaetigt','abgerechnet','bezahlt')
        ORDER BY firma");
    $sponsoren = $stmt->fetchAll();
} catch (PDOException $e) {
    // ignore
}

$typLabel = static fn (?string $p): string => match ($p) {
    'hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber',
    'bronze' => 'Bronze', 'sachsponsor' => 'Sachsponsor', default => '–',
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Leistungen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .lm-intro { font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem; line-height: 1.5; }
        .lm-wrap { overflow-x: auto; border-radius: 8px; box-shadow: var(--shadow-card); margin-bottom: 1.5rem; }
        .lm-table { border-collapse: collapse; background: var(--white); font-size: 0.8rem; }
        .lm-table th, .lm-table td { border-bottom: 1px solid var(--border); border-right: 1px solid var(--border); padding: 0.4rem 0.5rem; text-align: center; vertical-align: middle; }
        .lm-table thead th { background: var(--bg); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-light); font-weight: 600; white-space: nowrap; position: sticky; top: 0; z-index: 2; }
        .lm-firma { text-align: left; white-space: nowrap; font-weight: 600; position: sticky; left: 0; background: var(--white); z-index: 1; }
        .lm-table thead .lm-firma { z-index: 3; background: var(--bg); }
        .lm-table tr:hover .lm-firma { background: #fafafa; }
        .lm-typ { text-align: left; color: var(--text-light); white-space: nowrap; }
        .lm-col { min-width: 84px; }
        .lm-col .lm-vert { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; max-height: 130px; }
        .lm-check { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
        .lm-check.dim { opacity: 0.35; }
        .lm-text { width: 92px; padding: 0.2rem 0.3rem; border: 1px solid var(--border); border-radius: 3px; font-size: 0.72rem; margin-top: 0.2rem; }
        .lm-notiz-col { min-width: 200px; }
        .lm-notiz { width: 190px; }
        .lm-qty { display: inline-block; font-weight: 600; margin-left: 0.25rem; }
        .lm-cell-save { outline: 2px solid var(--primary); outline-offset: 1px; }
        .lm-ref { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1rem 1.25rem; }
        .lm-ref h3 { font-size: 0.95rem; margin: 0 0 0.5rem; }
        .lm-ref .lm-pak { margin: 0.5rem 0; }
        .lm-ref .lm-pak strong { display: inline-block; min-width: 110px; }
        .lm-ref ul { margin: 0.25rem 0 0.75rem 1.2rem; padding: 0; font-size: 0.85rem; color: var(--text); }
        .lm-empty { color: var(--text-light); font-size: 0.9rem; }
    </style>
</head>
<body>
<?php $activeNav = 'leistungen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Leistungen</h1>
            </header>

            <p class="lm-intro">
                Welche Leistungen sind je Sponsor <strong>vereinbart und zu erbringen</strong>? Der Haken ist
                aus dem Paket vorbelegt (kumulativ) und pro Sponsor abwählbar (Haken weg = fällt weg). Textfelder
                für Details (Banner-/Startertüten-Inhalt, Gutscheincode). Der Haken bedeutet „vereinbart", nicht „erledigt".
            </p>

            <?php if (!$sponsoren): ?>
                <p class="lm-empty">Keine zugesagten oder bestätigten Sponsoren mit Typ.</p>
            <?php else: ?>
                <div class="lm-wrap">
                    <table class="lm-table">
                        <thead>
                            <tr>
                                <th class="lm-firma">Firma</th>
                                <th class="lm-typ">Typ</th>
                                <?php foreach ($katalog as $pos): ?>
                                    <th class="lm-col"><div class="lm-vert"><?= htmlspecialchars($pos['label']) ?></div></th>
                                <?php endforeach; ?>
                                <th class="lm-notiz-col">Notiz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sponsoren as $s): ?>
                                <?php
                                    $sid = (int) $s['id'];
                                    $typ = (string) $s['paket'];
                                    $state = sponsorLeistungenState($pdo, $sid);
                                ?>
                                <tr>
                                    <td class="lm-firma"><a href="sponsor_form.php?id=<?= $sid ?>"><?= htmlspecialchars($s['firma']) ?></a></td>
                                    <td class="lm-typ"><?= htmlspecialchars($typLabel($typ)) ?></td>
                                    <?php foreach ($katalog as $pos): ?>
                                        <?php
                                            $key      = $pos['key'];
                                            $gilt     = sponsorLeistungGilt($pos, $typ);
                                            $row      = $state[$key] ?? null;
                                            $checked  = $row ? $row['vereinbart'] : $gilt;
                                            $freitext = $row['freitext'] ?? '';
                                        ?>
                                        <td>
                                            <input type="checkbox" class="lm-check<?= $gilt ? '' : ' dim' ?>"
                                                   data-sponsor="<?= $sid ?>" data-position="<?= htmlspecialchars($key) ?>"
                                                   <?= $checked ? 'checked' : '' ?>
                                                   title="<?= $gilt ? 'laut Paket enthalten' : 'nicht im Paket – als Extra ankreuzbar' ?>">
                                            <?php if ($pos['typ'] === 'startplaetze'): ?>
                                                <?php $menge = sponsorStartplaetzeMenge($pos, $typ); ?>
                                                <span class="lm-qty"><?= $gilt ? ($menge !== null ? (int) $menge : 'indiv.') : '' ?></span>
                                                <br>
                                                <input type="text" class="lm-text" data-sponsor="<?= $sid ?>"
                                                       data-position="<?= htmlspecialchars($key) ?>" data-field="freitext"
                                                       value="<?= htmlspecialchars($freitext) ?>" placeholder="Gutschein-Code">
                                            <?php elseif ($pos['typ'] === 'haken_text'): ?>
                                                <br>
                                                <input type="text" class="lm-text" data-sponsor="<?= $sid ?>"
                                                       data-position="<?= htmlspecialchars($key) ?>" data-field="freitext"
                                                       value="<?= htmlspecialchars($freitext) ?>" placeholder="Details">
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="lm-notiz-col">
                                        <input type="text" class="lm-text lm-notiz" data-sponsor="<?= $sid ?>"
                                               data-position="_notiz" data-field="freitext"
                                               value="<?= htmlspecialchars($state['_notiz']['freitext'] ?? '') ?>" placeholder="Notiz">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="lm-ref">
                    <h3>Pakete &amp; Leistungen (zum Abgleich)</h3>
                    <?php foreach (['bronze' => 'Bronze', 'silber' => 'Silber', 'gold' => 'Gold', 'hauptsponsor' => 'Hauptsponsor'] as $pk => $pl): ?>
                        <div class="lm-pak">
                            <strong><?= $pl ?>:</strong>
                            <ul>
                                <?php foreach ($katalog as $pos): ?>
                                    <?php if (sponsorLeistungGilt($pos, $pk)): ?>
                                        <li><?= htmlspecialchars($pos['label']) ?><?php
                                            if ($pos['typ'] === 'startplaetze') {
                                                $m = sponsorStartplaetzeMenge($pos, $pk);
                                                echo $m !== null ? ' (' . (int) $m . ')' : ' (individuell)';
                                            }
                                        ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($pk === 'hauptsponsor'): ?><li><em>individuell – alle Leistungen + Sonderabsprachen</em></li><?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    <p class="lm-empty">Sachsponsor: kein Paket-Leistungsumfang – Sachspende in der Notiz festhalten.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        function save(el, extra) {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('sponsor_id', el.dataset.sponsor);
            body.set('position', el.dataset.position);
            Object.keys(extra).forEach(function(k) { body.set(k, extra[k]); });
            el.classList.add('lm-cell-save');
            fetch('api/leistung_crud.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.json(); })
                .then(function(d) { setTimeout(function() { el.classList.remove('lm-cell-save'); }, d && d.ok ? 400 : 0); if (!d || !d.ok) { alert('Speichern fehlgeschlagen.'); } })
                .catch(function() { el.classList.remove('lm-cell-save'); alert('Speichern fehlgeschlagen.'); });
        }
        document.querySelectorAll('.lm-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                cb.classList.remove('dim');
                save(cb, { vereinbart: cb.checked ? '1' : '0' });
            });
        });
        document.querySelectorAll('.lm-text').forEach(function(t) {
            t.addEventListener('change', function() { save(t, { freitext: t.value }); });
        });
    })();
    </script>
</body>
</html>
