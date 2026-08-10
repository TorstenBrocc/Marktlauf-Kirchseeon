<?php
/**
 * Empfänger-Kopf — zwei Zeilen hoch, immer oben, Brief bleibt sofort sichtbar.
 *
 * Löst die frühere Empfängerliste in voller Länge ab: Bei mehr als ein paar Sponsoren wurde
 * sie zur Scroll-Barriere vor dem eigentlichen Brief. Zwei Modi (Spec §3.1):
 *
 *   einzel — Suchfeld + gewählter Empfänger als Chip; Liste eingeklappt.
 *            Auswahl über ?sponsor_id=… (die Seite rendert danach den Brief mit echten Daten).
 *   bulk   — Zielgruppen-Dropdown → „N Empfänger"; Liste eingeklappt zum Prüfen/Abwählen.
 *            Die Checkboxen gehören zum Versand-Formular der Seite (form="versand-form").
 *
 * Vor dem Include zu setzen:
 *   $pdo    PDO     offene Verbindung
 *   $slug   string  Vorlagen-Slug
 *   $modus  string  'einzel' | 'bulk'
 *   $seite  string  Dateiname der einbindenden Seite (für Links)
 *   $empfExtraTags ?callable optional fn(array $kandidat): array<int, array{text:string, warn:bool}>
 *                  — seitenspezifische Zusatz-Marker in der Liste (z. B. „Gutscheincode fehlt"
 *                  bei der Bestätigung). Ohne die Closure bleibt die Liste bei Status + Sperre.
 *
 * Setzt für die einbindende Seite:
 *   $kandidaten  array        alle Kandidaten der aktiven Zielgruppe
 *   $zielgruppe  string       aktive Zielgruppe
 *   $gewaehlt    array|null   nur im Einzel-Modus: der gewählte Sponsor
 *   $sponsorId   int          nur im Einzel-Modus: dessen ID (0 = keiner)
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/sponsor_zielgruppen.php';
require_once __DIR__ . '/../src/sponsor_status.php';

$zielgruppen = sponsorZielgruppen($slug);
$zielgruppe  = (string) ($_GET['zielgruppe'] ?? '');
if (!isset($zielgruppen[$zielgruppe])) {
    $zielgruppe = sponsorZielgruppeDefault($slug);
}
$kandidaten = sponsorZielgruppeKandidaten($pdo, $slug, $zielgruppe);

$versandfaehig = array_values(array_filter($kandidaten, 'sponsorKandidatVersandfaehig'));
$anzahlOffen   = count($versandfaehig);

// Einzel-Modus: gewählten Sponsor auflösen.
$sponsorId = (int) ($_GET['sponsor_id'] ?? 0);
$gewaehlt  = null;
foreach ($kandidaten as $k) {
    if ((int) $k['id'] === $sponsorId) {
        $gewaehlt = $k;
        break;
    }
}
if ($gewaehlt === null) {
    $sponsorId = 0;
}

$hinweis = $zielgruppen[$zielgruppe]['hinweis'] ?? '';

/** Link auf diese Seite mit geänderten Parametern. */
$kopfLink = static function (array $params) use ($seite, $zielgruppe): string {
    $q = array_merge(['zielgruppe' => $zielgruppe], $params);
    return htmlspecialchars($seite . '?' . http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== 0)));
};
?>
<div class="empf-kopf">
    <div class="empf-kopf-zeile">
        <?php if ($modus === 'bulk'): ?>
            <label for="zielgruppe" class="empf-label">Zielgruppe</label>
            <select id="zielgruppe" class="empf-select"
                    onchange="location.href='<?= htmlspecialchars($seite) ?>?zielgruppe=' + encodeURIComponent(this.value)">
                <?php foreach ($zielgruppen as $key => $z): ?>
                    <option value="<?= htmlspecialchars($key) ?>"<?= $key === $zielgruppe ? ' selected' : '' ?>>
                        <?= htmlspecialchars($z['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="empf-zahl"><strong id="empf-count"><?= $anzahlOffen ?></strong> Empfänger</span>
        <?php else: ?>
            <label for="empf-suche" class="empf-label">Empfänger</label>
            <input type="search" id="empf-suche" class="empf-select" placeholder="Firma suchen…"
                   autocomplete="off" aria-controls="empf-liste">
            <?php if ($gewaehlt !== null): ?>
                <span class="empf-chip">
                    <?= htmlspecialchars((string) $gewaehlt['firma']) ?>
                    <a href="<?= $kopfLink(['sponsor_id' => 0]) ?>" title="Auswahl aufheben">✕</a>
                </span>
            <?php endif; ?>
        <?php endif; ?>

        <button type="button" class="empf-toggle" id="empf-toggle" aria-expanded="false" aria-controls="empf-liste">
            <?= $modus === 'bulk' ? 'Prüfen/abwählen' : 'Liste' ?> (<?= count($kandidaten) ?>) ▾
        </button>
    </div>

    <?php if ($hinweis !== ''): ?>
        <p class="empf-hinweis">ℹ&#xFE0E; <?= htmlspecialchars($hinweis) ?></p>
    <?php endif; ?>

    <div class="empf-liste" id="empf-liste" hidden>
        <?php if (!$kandidaten): ?>
            <p class="brief-hint">In dieser Zielgruppe steht gerade niemand — hier ist nichts zu tun.</p>
        <?php else: ?>
            <?php if ($modus === 'bulk'): ?>
                <div class="empf-liste-actions">
                    <button type="button" class="btn btn-secondary btn-small" id="empf-alle">Alle</button>
                    <button type="button" class="btn btn-secondary btn-small" id="empf-keine">Keine</button>
                </div>
            <?php endif; ?>
            <ul class="empf-items">
                <?php foreach ($kandidaten as $k):
                    $sperre = sponsorKandidatSperrgrund($k); ?>
                    <li class="empf-item<?= (int) $k['id'] === $sponsorId ? ' aktiv' : '' ?>"
                        data-firma="<?= htmlspecialchars(mb_strtolower((string) $k['firma'])) ?>">
                        <?php if ($modus === 'bulk'): ?>
                            <input type="checkbox" class="empf-check" name="sponsor_ids[]" form="versand-form"
                                   value="<?= (int) $k['id'] ?>" <?= $sperre === '' ? 'checked' : 'disabled' ?>>
                        <?php endif; ?>
                        <span class="firma"><?= htmlspecialchars((string) $k['firma']) ?></span>
                        <span class="empf-tag"><?= htmlspecialchars(sponsorStatusLabel((string) $k['status'])) ?></span>
                        <?php foreach (isset($empfExtraTags) ? $empfExtraTags($k) : [] as $tag): ?>
                            <span class="empf-tag<?= !empty($tag['warn']) ? ' warn' : '' ?>"><?= htmlspecialchars((string) $tag['text']) ?></span>
                        <?php endforeach; ?>
                        <?php if ($sperre !== ''): ?>
                            <span class="empf-tag stop"><?= htmlspecialchars($sperre) ?></span>
                        <?php endif; ?>
                        <?php if ($modus === 'einzel' && $sperre === ''): ?>
                            <a class="btn btn-secondary btn-small" href="<?= $kopfLink(['sponsor_id' => (int) $k['id']]) ?>#compose">
                                <?= (int) $k['id'] === $sponsorId ? 'ausgewählt' : 'auswählen' ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var toggle = document.getElementById('empf-toggle');
    var liste  = document.getElementById('empf-liste');
    if (toggle && liste) {
        toggle.addEventListener('click', function() {
            var open = liste.hidden;
            liste.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.textContent = toggle.textContent.replace(open ? '▾' : '▴', open ? '▴' : '▾');
        });
    }

    // Einzel-Modus: Suchfeld filtert die Liste und klappt sie beim Tippen auf.
    var suche = document.getElementById('empf-suche');
    if (suche && liste) {
        suche.addEventListener('input', function() {
            var q = suche.value.trim().toLowerCase();
            if (q !== '' && liste.hidden) { toggle.click(); }
            document.querySelectorAll('.empf-item').forEach(function(li) {
                li.hidden = q !== '' && (li.dataset.firma || '').indexOf(q) === -1;
            });
        });
    }

    // Bulk-Modus: Auswahlzähler + Alle/Keine.
    var count = document.getElementById('empf-count');
    if (count) {
        var checks = function() { return document.querySelectorAll('.empf-check:not([disabled])'); };
        var refresh = function() {
            var n = 0;
            checks().forEach(function(cb) { if (cb.checked) n++; });
            count.textContent = String(n);
        };
        checks().forEach(function(cb) { cb.addEventListener('change', refresh); });
        var alle = document.getElementById('empf-alle');
        var keine = document.getElementById('empf-keine');
        if (alle)  { alle.addEventListener('click',  function() { checks().forEach(function(cb) { cb.checked = true;  }); refresh(); }); }
        if (keine) { keine.addEventListener('click', function() { checks().forEach(function(cb) { cb.checked = false; }); refresh(); }); }
        refresh();
    }
})();
</script>
