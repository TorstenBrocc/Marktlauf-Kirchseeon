<?php
/**
 * Anhang-Kachel — EINE Kachel je Mail-Seite, die JEDE Datei zeigt, die diese Mail verlässt.
 *
 * Verbindliches Muster (Spec §3.3): feste Anhänge stehen mit 🔒 und ohne Checkbox drin,
 * abwählbare mit Checkbox. Kein Anhang darf als Banner oder Fremdblock an anderer Stelle
 * der Seite stehen — genau das war vorher das Problem (Bedingungen als Hinweis über dem
 * Brief, Plakate in einer Kachel darunter, Abwahl-Liste nochmal in der Sponsorenliste).
 *
 * Vor dem Include zu setzen:
 *   $pdo        PDO     offene Verbindung
 *   $slug       string  Vorlagen-Slug (bestimmt über sponsorAnhangPlan(), was gezeigt wird)
 *   $csrfToken  string  für das Upload-Formular
 *   $anhangAbwahl bool  optional: Abwahl-Checkboxen unterdrücken (default: an, wo der
 *                       Anhang-Plan die Gruppe nicht als 'fest' führt)
 *   $anhangRedirect string optional: Ziel nach dem Upload (default: aktuelle Seite)
 *
 * Gibt nichts aus, wenn an der Vorlage keine Anhänge hängen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/sponsor_anhaenge.php';

$anhangGruppen = sponsorAnhangGruppenAnzeige($pdo, $slug);
if ($anhangGruppen === []) {
    return;
}

// Abwählbar ist, was der Plan nicht als 'fest' führt — das gilt für jede Vorlage, nicht
// nur für die Bestätigung. Der Versand wertet die Abwahl inzwischen überall aus
// (Einzelversand direkt, Mehrfachversand über die Queue, Migration 056).
$anhangAbwahl   = $anhangAbwahl   ?? true;
$anhangRedirect = $anhangRedirect ?? basename($_SERVER['PHP_SELF'] ?? '');

// Upload-Ziel: nur der Plakate-Ordner nimmt Uploads direkt von hier entgegen.
$plakatFolder = null;
foreach (sponsorAnhangPlan($slug) as $g) {
    if ($g['quelle'] === 'plakate' && driveConfigured()) {
        $plakatFolder = drivePlakatFolderId($pdo, driveRennJahr($pdo));
    }
}
?>
<div class="brief-card anhang-kachel">
    <div class="anhang-kachel-head">
        <strong>📎 Anhänge</strong>
        <span class="brief-hint">alles, was mit dieser Mail rausgeht</span>
    </div>

    <?php foreach ($anhangGruppen as $gruppe):
        // Abwählbar ist eine Gruppe nur, wenn der Plan es erlaubt UND die Seite die Abwahl
        // anbietet. Sonst wird sie wie ein fester Anhang markiert — sonst stünde die Datei
        // ohne jeden Marker da und man wüsste nicht, ob sie mitgeht.
        $abwaehlbar = !$gruppe['fest'] && $anhangAbwahl; ?>
        <h4 class="anhang-gruppe-titel">
            <?= $abwaehlbar ? '' : '🔒 ' ?><?= htmlspecialchars($gruppe['titel']) ?>
            <?php if (!$abwaehlbar): ?>
                <span class="brief-hint">fest — geht immer mit</span>
            <?php endif; ?>
        </h4>

        <?php if ($gruppe['hinweis'] !== ''): ?>
            <p class="anhang-warn"><?= htmlspecialchars($gruppe['hinweis']) ?></p>
        <?php else: ?>
            <ul class="plakat-liste">
                <?php foreach ($gruppe['files'] as $f): $kb = round((int) ($f['size'] ?? 0) / 1024); ?>
                    <li class="plakat-item">
                        <?php if ($abwaehlbar): ?>
                            <input type="checkbox" class="anhang-abwahl" data-group="<?= htmlspecialchars($gruppe['id']) ?>"
                                   value="<?= htmlspecialchars((string) $f['id']) ?>" title="An-/Abwählen" checked>
                        <?php else: ?>
                            <span class="anhang-fest" title="Dieser Anhang ist fest und geht immer mit">🔒</span>
                        <?php endif; ?>
                        <span title="<?= htmlspecialchars((string) $f['name']) ?>">
                            📄 <?= htmlspecialchars((string) $f['name']) ?>
                        </span>
                        <small class="brief-hint"><?= $kb ?> KB</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endforeach; ?>

    <p class="plakat-hinweis">
        ⚠️ Die Anhänge kommen live aus den festgelegten Drive-Ordnern — was dort liegt, wird angehängt.
        <?php if ($anhangAbwahl): ?>
            Abgewählte Dateien bleiben abgewählt bis zum nächsten Versand, danach sind wieder alle dabei.
            Die Abwahl gilt nur für dich.
        <?php endif; ?>
        Dateien endgültig entfernen: unter „Dateien".
    </p>

    <?php if ($plakatFolder !== null): ?>
    <form method="post" action="api/file_upload.php" enctype="multipart/form-data" class="plakat-upload-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="tab" value="orga">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($plakatFolder) ?>">
        <input type="hidden" name="redirect_after" value="<?= htmlspecialchars($anhangRedirect) ?>">
        <input type="file" name="datei" accept="application/pdf" required style="font-size:0.9rem;">
        <button type="submit" class="btn btn-primary">Plakat-PDF hochladen</button>
    </form>
    <?php endif; ?>

    <?php
    // Fehlen die Bedingungen, ist der Download der Vorlage der nächste sinnvolle Schritt:
    // herunterladen, in „Orga/Sponsoren/_assets Sponsoren" ablegen, fertig.
    $bedingungenFehlen = false;
    foreach ($anhangGruppen as $g) {
        if ($g['id'] === 'bedingungen' && $g['files'] === []) { $bedingungenFehlen = true; }
    }
    ?>
    <?php if ($bedingungenFehlen): ?>
        <p class="brief-hint">
            <a href="api/sponsor_bedingungen_download.php" target="_blank" rel="noopener">Bedingungen-Vorlage herunterladen</a>
            und im Drive-Ordner <em>Orga/Sponsoren/_assets Sponsoren</em> ablegen.
        </p>
    <?php endif; ?>
</div>

<?php if ($anhangAbwahl): ?>
<script>
// Anhang-Abwahl: browser-seitig, pro Person und pro Vorlage, bis zum nächsten Versand.
// Gespeichert werden nur die ABGEWÄHLTEN Drive-IDs je Gruppe. Der Schlüssel trägt den Slug,
// damit eine Abwahl im Freien Brief nicht still die Bestätigung mitverändert; nach
// erfolgreichem Versand leert die Seite ihn wieder (dann sind alle Anhänge zurück).
(function() {
    var KEY = 'mkl_anhang_abwahl_' + <?= json_encode($slug) ?>;
    function load() {
        try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); return { plakat: s.plakat || [], asset: s.asset || [] }; }
        catch (e) { return { plakat: [], asset: [] }; }
    }
    function save(s) { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {} }
    var boxes = document.querySelectorAll('.anhang-abwahl');
    if (!boxes.length) return;
    var state = load();
    boxes.forEach(function(cb) {
        var g = cb.dataset.group;
        cb.checked = (state[g] || []).indexOf(cb.value) === -1;
        cb.addEventListener('change', function() {
            var s = load();
            var arr = s[g] || [];
            var i = arr.indexOf(cb.value);
            if (cb.checked) { if (i !== -1) arr.splice(i, 1); }
            else if (i === -1) { arr.push(cb.value); }
            s[g] = arr;
            save(s);
        });
    });
})();
</script>
<?php endif; ?>
