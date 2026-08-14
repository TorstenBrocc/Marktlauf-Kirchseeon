<?php
/**
 * Generische Anschreiben-Seite (Bulk-Vorlagen).
 *
 * Trägt Erstanschreiben, Folgeanschreiben, Freien Brief und „Bedingungen nachreichen":
 * Empfänger-Kopf → Compose → Anhang-Kachel → Versand, alles auf einer Seite. Die vier
 * Seiten sind bewusst dünne Wrapper um diese Datei — vorher lagen sie als Tabs in einer
 * Seite und drifteten gegen den Versandweg, der woanders lag.
 *
 * Die Bestätigung hat eine eigene Seite (`bestaetigungen.php`): sie stellt sponsor-bezogen
 * aus Abschnitts-Bausteinen zusammen, hebt den Status und legt ein Beleg-PDF ab. Sie nutzt
 * dieselben Partials, aber nicht diese Vorlage.
 *
 * Vor dem Include zu setzen:
 *   $slug   string  Vorlagen-Slug (erstanschreiben|folgejahr|frei|bedingungen|rechnung)
 *   $titel  string  Überschrift + <title>
 *   $navKey string  Schlüssel des Sidebar-Eintrags
 *
 * Optional — für Vorlagen, deren Versand nicht hier stattfindet (Rechnungs-Begleitmail: sie geht
 * je Rechnung von der Seite „(Ab-)Rechnungen" raus, mit festen Anhängen und einem Empfänger aus
 * den Sponsor-Adressen). Statt eine zweite Compose-Oberfläche zu pflegen, werden die drei
 * versandbezogenen Blöcke abgeschaltet:
 *   $mitEmpfaenger bool   Empfänger-Kopf + Vorschau-Auswahl (Default true)
 *   $mitAnhaenge   bool   Anhang-Kachel (Default true)
 *   $mitVersand    bool   Versand-Karte (Default true)
 *   $hinweisHtml   string Erklärkasten oben, wo der Versand stattdessen läuft
 *
 * Spec: intern/sponsoren-anschreiben-seiten-spec.md
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_brief.php';
require_once __DIR__ . '/../src/sponsor_anhaenge.php';
require_once __DIR__ . '/../src/channels/mail.php';

$user      = getCurrentUserFromGuard();
$isAdmin   = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
// Reset-Signal: ist ein Versand dieser Vorlage rausgegangen, setzt der Browser die
// Anhang-Abwahl zurück — danach sind wieder alle Anhänge dabei.
$anhangAbwahlReset = ($_SESSION['anhang_abwahl_reset'] ?? '') === $slug;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['anhang_abwahl_reset']);

$mitEmpfaenger = $mitEmpfaenger ?? true;
$mitAnhaenge   = $mitAnhaenge   ?? true;
$mitVersand    = $mitVersand    ?? true;
$hinweisHtml   = $hinweisHtml   ?? '';
// Wird normalerweise von _empfaenger_kopf.php gefüllt; ohne diesen Block bleibt sie leer.
$versandfaehig = [];

$pdo         = getDbConnection();

// Fördergruppen-Umschaltung im Erstanschreiben: ist eine Fördergruppen-Zielgruppe gewählt
// (fg_<gruppe>) und gibt es dafür eine eigene Vorlagen-Variante, bearbeitet die Seite deren
// Text ($textSlug — eigener Master/Entwurf/Default). Der strukturelle $slug (Versand-Typ,
// Anhänge, Anhang-Abwahl) bleibt die Basis. So passt der Editor zum Versand, der die Variante
// ohnehin anhand der Fördergruppe des Empfängers wählt (sponsorBriefEffektiverSlug()).
$textSlug = $slug;
if ($slug === 'erstanschreiben' && SPONSOR_BRIEF_VARIANTEN !== []) {
    $zgWahl = (string) ($_GET['zielgruppe'] ?? '');
    if (str_starts_with($zgWahl, 'fg_')) {
        $variante = sponsorBriefVarianteFuer($slug, substr($zgWahl, 3));
        if ($variante !== '') {
            $textSlug = $variante;
        }
    }
}

$defaults    = sponsorBriefDefaults();
$vorlage     = sponsorBriefLoad($pdo, $textSlug, (int) $user['id']);
$default     = $defaults[$textSlug] ?? $defaults[$slug];
$platzhalter = sponsorBriefPlatzhalterHilfe($slug);
$seite       = basename($_SERVER['PHP_SELF'] ?? '');

// Geteilt vs. persönlich: Erstanschreiben und Nachreich-Mail sind Team-Texte (Master in der
// DB), Folgeanschreiben und Freier Brief gehören dem Verfasser (persönlicher Entwurf).
$isUserScoped = in_array($slug, ['folgejahr', 'frei'], true);
$hasStandardtext = $slug !== 'frei';

$draftHinweis = '';
if ($isUserScoped && $vorlage['draft'] && $vorlage['draft_ts'] !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $vorlage['draft_ts']);
    if ($dt) {
        $draftHinweis = 'Gespeichert am ' . $dt->format('d.m.Y, H:i') . ' Uhr';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($titel) ?> | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .brief-betreff { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; }
        .brief-platzhalter { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.75rem 0; align-items: center; }
        .ph-chip { font-family: monospace; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: var(--bg); border: 1px solid var(--border); cursor: pointer; color: var(--text); }
        .ph-chip:hover { background: var(--border); }
        .brief-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 900px) { .brief-split { grid-template-columns: 1fr; } }
        .brief-split-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .brief-split-head h3 { margin: 0; font-size: 0.9rem; color: var(--text-light); }
        #koerper_md { width: 100%; min-height: 420px; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px; font-family: monospace; font-size: 0.85rem; line-height: 1.5; box-sizing: border-box; resize: vertical; }
        #preview-frame { width: 100%; height: 420px; border: 1px solid var(--border); border-radius: 4px; background: #fff; box-sizing: border-box; }
        .brief-actions { display: flex; gap: 1rem; margin-top: 1.25rem; align-items: center; flex-wrap: wrap; }
        .versand-card { border: 1px solid var(--primary); }
        .versand-warn { font-size: 0.85rem; color: var(--text); background: rgba(255,193,7,0.15); border: 1px solid rgba(255,193,7,0.55); border-radius: 6px; padding: 0.6rem 0.8rem; margin: 0 0 1rem; line-height: 1.5; }
    </style>
</head>
<body>
<?php $activeNav = $navKey; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1><?= htmlspecialchars($titel) ?></h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if ($hinweisHtml !== ''): ?>
                <div class="brief-card" style="border-left:3px solid var(--primary)">
                    <?= $hinweisHtml ?>
                </div>
            <?php endif; ?>

            <?php
            // Der Empfänger-Block wird HIER ausgewertet, aber erst unten in der Versand-Karte
            // ausgegeben (TT, 2026-08-11: Zielgruppe gehört unter die Anhänge, direkt an den
            // Sende-Knopf). Die Auswertung muss oben bleiben, weil die Vorschau $versandfaehig
            // für ihre Empfänger-Auswahl braucht — deshalb Ausgabe puffern statt Include
            // verschieben. Der Partial selbst bleibt unangetastet und wird von der Bestätigung
            // im Einzel-Modus weiter oben auf der Seite genutzt.
            $empfBlockHtml = '';
            if ($mitEmpfaenger) {
                $modus = 'bulk';
                ob_start();
                require __DIR__ . '/_empfaenger_kopf.php';
                $empfBlockHtml = ob_get_clean();
            }
            ?>

            <form method="post" action="api/sponsor_brief_save.php" id="brief-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($textSlug) ?>">

                <div class="brief-card">
                    <label for="betreff"><strong>Betreff</strong></label>
                    <input type="text" id="betreff" name="betreff" class="brief-betreff" maxlength="255"
                           value="<?= htmlspecialchars($vorlage['betreff']) ?>">

                    <div class="brief-platzhalter">
                        <span class="brief-hint">Platzhalter einfügen:</span>
                        <?php foreach ($platzhalter as $ph => $beschreibung): ?>
                            <span class="ph-chip" data-ph="<?= htmlspecialchars($ph) ?>" title="<?= htmlspecialchars($beschreibung) ?>"><?= htmlspecialchars($ph) ?></span>
                        <?php endforeach; ?>
                        <a href="anschreiben_einstellungen.php" class="brief-hint">Was steckt dahinter? →</a>
                    </div>

                    <div class="brief-split">
                        <div>
                            <div class="brief-split-head">
                                <h3>Markdown</h3>
                                <?= sponsorMarkdownLegende() ?>
                            </div>
                            <textarea id="koerper_md" name="koerper_md"><?= htmlspecialchars($vorlage['koerper_md']) ?></textarea>
                        </div>
                        <div>
                            <div class="brief-split-head">
                                <h3>Vorschau</h3>
                                <?php if ($versandfaehig): ?>
                                <select id="preview-sponsor" class="empf-select" style="min-width:12rem;font-size:0.82rem;padding:0.25rem 0.4rem"
                                        title="Vorschau mit den echten Daten dieses Empfängers">
                                    <?php foreach ($versandfaehig as $vf): ?>
                                        <option value="<?= (int) $vf['id'] ?>"><?= htmlspecialchars((string) $vf['firma']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                            <iframe id="preview-frame" sandbox="" title="Vorschau"></iframe>
                        </div>
                    </div>

                    <div class="brief-actions">
                        <?php if ($isUserScoped): ?>
                            <button type="button" class="btn btn-primary" id="btn-save">Speichern</button>
                            <span class="brief-hint">Dein persönlicher Stand — andere sehen ihn nicht.</span>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                            <span class="brief-hint">Gemeinsamer Text — gilt für das ganze Orga-Team.</span>
                        <?php endif; ?>
                        <?php if ($hasStandardtext): ?>
                            <button type="button" class="btn btn-secondary" id="reset-default">Standardtext wiederherstellen</button>
                        <?php endif; ?>
                        <span id="draft-status" class="brief-hint"><?= htmlspecialchars($draftHinweis) ?></span>
                    </div>
                </div>
            </form>

            <?php if ($mitAnhaenge) { require __DIR__ . '/_anhang_kachel.php'; } ?>

            <?php if ($mitVersand): ?>
            <div class="brief-card versand-card">
                <h3 style="font-size:0.95rem;margin:0 0 0.75rem">Empfänger &amp; Versand</h3>
                <?= $empfBlockHtml ?>
                <p id="versand-unsaved" class="versand-warn" hidden>
                    ⚠️ Du hast ungespeicherte Änderungen am Text. Der Versand nimmt den
                    <strong>gespeicherten</strong> Stand — bitte zuerst speichern.
                </p>
                <form method="post" action="api/sponsor_versand.php" id="versand-form" onsubmit="return confirmVersand();">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="anschreiben_typ" value="<?= htmlspecialchars($slug) ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($seite) ?>">
                    <div class="brief-actions" style="margin-top:0">
                        <button type="submit" class="btn btn-primary">Ausgewählte anschreiben</button>
                        <span class="brief-hint">
                            Versand über <strong>info@atsv-kirchseeon-marktlauf.de</strong> ·
                            ab 2 Empfängern über die Warteschlange (15 Sek. Abstand je Mail)
                        </span>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const slug = <?= json_encode($textSlug) ?>;
        const defaultText = <?= json_encode($default['koerper_md']) ?>;
        const defaultBetreff = <?= json_encode($default['betreff']) ?>;
        const ta = document.getElementById('koerper_md');
        const betreff = document.getElementById('betreff');
        const frame = document.getElementById('preview-frame');
        const previewSponsor = document.getElementById('preview-sponsor');
        let timer = null;

        // Gespeicherter Stand beim Laden — Grundlage für die Ungespeichert-Warnung.
        let savedText = ta.value;
        let savedBetreff = betreff.value;

        // Vorschau: mit den ECHTEN Daten des gewählten Empfängers, nicht mit Musterdaten —
        // derselbe Renderer wie der Versand.
        function renderPreview() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('koerper_md', ta.value);
            body.set('slug', slug);
            if (previewSponsor && previewSponsor.value) {
                body.set('sponsor_id', previewSponsor.value);
            }
            fetch('api/sponsor_brief_preview.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.text(); })
                .then(function(html) { frame.srcdoc = html; })
                .catch(function() { /* Vorschau optional */ });
        }
        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(renderPreview, 400);
            markDirty();
        }
        function markDirty() {
            // Die Warnung hängt an der Versand-Karte; ohne Versand auf dieser Seite fehlt sie.
            const warn = document.getElementById('versand-unsaved');
            if (!warn) { return; }
            warn.hidden = !((ta.value !== savedText) || (betreff.value !== savedBetreff));
        }
        ta.addEventListener('input', schedule);
        betreff.addEventListener('input', markDirty);
        if (previewSponsor) { previewSponsor.addEventListener('change', renderPreview); }
        renderPreview();

        // Platzhalter an Cursorposition einfügen
        document.querySelectorAll('.ph-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                const ph = chip.dataset.ph;
                const start = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.slice(0, start) + ph + ta.value.slice(end);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + ph.length;
                schedule();
            });
        });

        <?php if ($hasStandardtext): ?>
        document.getElementById('reset-default').addEventListener('click', function() {
            if (!confirm('Text und Betreff auf die Standardvorlage zurücksetzen? Ungespeicherte Änderungen gehen verloren.')) {
                return;
            }
            ta.value = defaultText;
            betreff.value = defaultBetreff;
            renderPreview();
            markDirty();
        });
        <?php endif; ?>

        <?php if ($isUserScoped): ?>
        document.getElementById('btn-save').addEventListener('click', function() {
            const statusEl = document.getElementById('draft-status');
            statusEl.textContent = 'Speichert…';
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('vorlage_art', 'sponsor');
            body.set('slug', slug);
            body.set('betreff', betreff.value);
            body.set('koerper_md', ta.value);
            fetch('api/draft_save.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        var m = (data.gespeichert_am || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
                        statusEl.textContent = m ? 'Gespeichert am ' + m[3] + '.' + m[2] + '.' + m[1] + ', ' + m[4] + ':' + m[5] + ' Uhr' : 'Gespeichert.';
                        savedText = ta.value;
                        savedBetreff = betreff.value;
                        markDirty();
                    } else {
                        statusEl.textContent = 'Fehler: ' + (data.error || 'Unbekannt');
                    }
                })
                .catch(function() { statusEl.textContent = 'Speichern fehlgeschlagen.'; });
        });
        <?php endif; ?>

        // Vorschau-Höhe beim manuellen Resize der Textarea nachziehen (max 700px)
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function(entries) {
                for (var entry of entries) {
                    frame.style.height = Math.min(entry.target.offsetHeight, 700) + 'px';
                }
            }).observe(ta);
        }

        // Versand-Bestätigung: Anzahl nennen und die Anhang-Abwahl mitschicken.
        window.confirmVersand = function() {
            var gewaehlt = document.querySelectorAll('.empf-check:checked').length;
            if (gewaehlt === 0) {
                alert('Kein Empfänger ausgewählt. Öffne „Prüfen/abwählen" und wähle mindestens einen aus.');
                return false;
            }
            if ((ta.value !== savedText) || (betreff.value !== savedBetreff)) {
                if (!confirm('Der Text ist nicht gespeichert — es geht der zuletzt gespeicherte Stand raus.\n\nTrotzdem senden?')) {
                    return false;
                }
            }
            if (!confirm(gewaehlt + ' Empfänger anschreiben?\n\nAb 2 Empfängern läuft der Versand über die Warteschlange (15 Sek. Abstand je Mail).')) {
                return false;
            }
            // Abwahl aus der Anhang-Kachel mitschicken: ohne diese Felder wären die
            // Checkboxen wirkungslos und es ginge doch jeder Anhang raus.
            var vf = document.getElementById('versand-form');
            vf.querySelectorAll('input[name="exclude_asset_fids[]"], input[name="exclude_plakat_fids[]"]')
                .forEach(function(el) { el.remove(); });
            document.querySelectorAll('.anhang-abwahl:not(:checked)').forEach(function(cb) {
                var hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = (cb.dataset.group === 'asset' ? 'exclude_asset_fids[]' : 'exclude_plakat_fids[]');
                hid.value = cb.value;
                vf.appendChild(hid);
            });
            return true;
        };
    })();

    <?php if ($anhangAbwahlReset): ?>
    try { localStorage.removeItem('mkl_anhang_abwahl_' + <?= json_encode($slug) ?>); } catch (e) {}
    <?php endif; ?>

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
