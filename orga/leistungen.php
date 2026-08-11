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

// Nach Paket gruppieren: Hauptsponsor → Gold → Silber → Bronze → Sachsponsor. Die Reihenfolge
// kommt aus `sponsorTypRang()` (absteigend) — keine zweite Rangliste, die auseinanderlaufen kann.
// Innerhalb einer Gruppe bleibt die alphabetische Sortierung aus dem SQL erhalten (stabiler Sort).
usort($sponsoren, static fn (array $a, array $b): int
    => sponsorTypRang($b['paket'] ?? null) <=> sponsorTypRang($a['paket'] ?? null));

$spaltenGesamt = count($katalog) + 2; // Firma + Katalog-Spalten + Notiz
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
        /* Firma/Typ linksbündig: die Regel braucht td/th mit, sonst gewinnt das zentrierende
           `.lm-table td` (Spezifität 0,1,1 schlägt eine einzelne Klasse). */
        .lm-table th.lm-firma, .lm-table td.lm-firma {
            text-align: left; font-weight: 600; position: sticky; left: 0;
            background: var(--white); z-index: 1;
            /* Umbruch statt nowrap: lange Firmennamen brechen nach ~25 Zeichen um, statt die
               Tabelle in die Breite zu ziehen. */
            /* Umbruch statt nowrap. Untergrenze 12ch, damit die Spalte beim Schrumpfen nicht zu
               einem Buchstabenturm zerfällt; Obergrenze 25ch, damit lange Namen die Tabelle nicht
               breitziehen. `ch` = Breite der Ziffer „0" — bei proportionaler Schrift eine
               Näherung, 12ch fasst real eher 14–15 Kleinbuchstaben. */
            white-space: normal; min-width: 12ch; max-width: 25ch;
            overflow-wrap: break-word; hyphens: auto;
        }
        .lm-table thead th.lm-firma { z-index: 3; background: var(--bg); white-space: normal; }
        .lm-table tr:hover td.lm-firma { background: #fafafa; }
        /* Zwischenüberschrift je Sponsoring-Paket. Zellenauswahl mit `tr` davor, sonst gewinnt
           wieder das zentrierende `.lm-table td`. */
        .lm-table tr.lm-group td {
            background: var(--bg); text-align: left; padding: 0.35rem 0.5rem;
            font-weight: 700; font-size: 0.72rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text);
            border-top: 2px solid var(--primary);
        }
        /* Die Zelle ist so breit wie die ganze Tabelle — ohne sticky wäre der Titel beim
           seitlichen Scrollen weg. */
        .lm-table tr.lm-group td span { position: sticky; left: 0.5rem; display: inline-block; }
        /* Reine Haken-Spalten: so schmal wie der senkrechte Kopf es zulässt. */
        .lm-col { min-width: 34px; }
        /* Nur 30 % des früheren Zellrahmens (war 0.4rem/0.5rem) — die Haken sollen eng sitzen. */
        .lm-table th.lm-col, .lm-table td.lm-col { padding: 0.12rem 0.15rem; }
        /* Spalten mit Schreibfeld brauchen Platz für Haken + Feld nebeneinander. */
        .lm-col--text { min-width: 118px; }
        /* Senkrechte Spaltenköpfe. `nowrap` + `max-height` hat lange Titel oben abgeschnitten
           („Banner im Start-/Zie…"). Jetzt feste Textlänge (in vertikaler Schreibrichtung ist das
           `height`) und Umbruch erlaubt: zu lange Titel laufen in eine zweite Zeile statt aus dem
           Kopf heraus. Feste statt maximaler Höhe = einheitlich hohes Kopfband. */
        .lm-col .lm-vert {
            writing-mode: vertical-rl; transform: rotate(180deg);
            white-space: normal; height: 150px; line-height: 1.15;
            overflow-wrap: break-word; hyphens: auto; margin: 0 auto;
        }
        /* Haken, Stückzahl und Schreibfeld in EINER Zeile — das Feld steht hinter dem Haken. */
        .lm-cell { display: flex; align-items: center; justify-content: center; gap: 0.2rem; }
        .lm-check { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; flex-shrink: 0; margin: 0; }
        .lm-check.dim { opacity: 0.35; }
        .lm-text { width: 92px; padding: 0.2rem 0.3rem; border: 1px solid var(--border); border-radius: 3px; font-size: 0.72rem; }
        .lm-cell .lm-text { width: 76px; flex: 0 1 auto; min-width: 0; }
        .lm-notiz-col { min-width: 200px; }
        .lm-notiz { width: 190px; }
        .lm-qty { font-weight: 600; flex-shrink: 0; }
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
                für Details (Banner-Inhalt, Gutscheincode). Der Haken bedeutet „vereinbart", nicht „erledigt".
            </p>

            <?php if (!$sponsoren): ?>
                <p class="lm-empty">Keine zugesagten oder bestätigten Sponsoren mit Typ.</p>
            <?php else: ?>
                <div class="lm-wrap">
                    <table class="lm-table">
                        <thead>
                            <tr>
                                <th class="lm-firma">Firma</th>
                                <?php foreach ($katalog as $pos): ?>
                                    <th class="lm-col<?= in_array($pos['typ'], ['startplaetze', 'haken_text'], true) ? ' lm-col--text' : '' ?>"><div class="lm-vert"><?= htmlspecialchars($pos['label']) ?></div></th>
                                <?php endforeach; ?>
                                <th class="lm-notiz-col">Notiz</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $letzterTyp = null; ?>
                            <?php foreach ($sponsoren as $s): ?>
                                <?php
                                    $sid = (int) $s['id'];
                                    $typ = (string) $s['paket'];
                                    $state = sponsorLeistungenState($pdo, $sid);
                                ?>
                                <?php if ($typ !== $letzterTyp): $letzterTyp = $typ; ?>
                                    <tr class="lm-group">
                                        <td colspan="<?= $spaltenGesamt ?>">
                                            <span><?= htmlspecialchars($typLabel($typ)) ?></span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="lm-firma"><a href="sponsor_form.php?id=<?= $sid ?>"><?= htmlspecialchars($s['firma']) ?></a></td>
                                    <?php foreach ($katalog as $pos): ?>
                                        <?php
                                            $key      = $pos['key'];
                                            $gilt     = sponsorLeistungGilt($pos, $typ);
                                            $row      = $state[$key] ?? null;
                                            $checked  = $row ? $row['vereinbart'] : $gilt;
                                            $freitext = $row['freitext'] ?? '';
                                        ?>
                                        <?php
                                            $hatText = in_array($pos['typ'], ['startplaetze', 'haken_text'], true);
                                            // Haken einmal bauen — bei den Startplätzen steht er hinten
                                            // (Stückzahl → Code → Haken), sonst vorn.
                                            $checkbox = sprintf(
                                                '<input type="checkbox" class="lm-check%s" data-sponsor="%d" data-position="%s"%s title="%s">',
                                                $gilt ? '' : ' dim',
                                                $sid,
                                                htmlspecialchars($key),
                                                $checked ? ' checked' : '',
                                                $gilt ? 'laut Paket enthalten' : 'nicht im Paket – als Extra ankreuzbar'
                                            );
                                            $textfeld = static fn (string $ph): string => sprintf(
                                                '<input type="text" class="lm-text" data-sponsor="%d" data-position="%s" data-field="freitext" value="%s" placeholder="%s">',
                                                $sid,
                                                htmlspecialchars($key),
                                                htmlspecialchars($freitext),
                                                $ph
                                            );
                                        ?>
                                        <td class="lm-col<?= $hatText ? ' lm-col--text' : '' ?>">
                                            <div class="lm-cell">
                                            <?php if ($pos['typ'] === 'startplaetze'): ?>
                                                <?php
                                                    $menge = sponsorStartplaetzeMenge($pos, $typ);
                                                    $qty = $gilt ? ($menge !== null ? (string) (int) $menge : 'indiv.') : '';
                                                ?>
                                                <?php if ($qty !== ''): ?><span class="lm-qty"><?= htmlspecialchars($qty) ?></span><?php endif; ?>
                                                <?= $textfeld('Gutschein-Code') ?>
                                                <?= $checkbox ?>
                                            <?php elseif ($pos['typ'] === 'haken_text'): ?>
                                                <?= $checkbox ?>
                                                <?= $textfeld('Details') ?>
                                            <?php else: ?>
                                                <?= $checkbox ?>
                                            <?php endif; ?>
                                            </div>
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
