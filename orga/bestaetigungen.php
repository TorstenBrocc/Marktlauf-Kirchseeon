<?php
/**
 * Sponsoring-Bestätigungen — sponsor-bezogen zusammenstellen statt blind versenden.
 *
 * Links die zugesagten Sponsoren, rechts der Compose-Bereich für den gewählten: Abschnitts-
 * Bausteine an-/abwählen, Live-Vorschau mit den ECHTEN Daten dieses Sponsors (Anrede, Paket,
 * Startplätze, Gutscheincode), dann senden.
 *
 * Bewusst KEIN eigener Versandweg: gesendet wird über `api/sponsor_versand.php` — dort hängen
 * Anhänge, Beleg-PDF im Drive und Fehlerbehandlung schon dran. Der zusammengestellte Text geht
 * als persönlicher Entwurf (`api/draft_save.php`) rein; Versand und Beleg laden beide denselben
 * Entwurf über sponsorBriefLoad() und sind damit zeichengleich.
 *
 * Details: intern/sponsoring-modell-spec.md §e.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_brief.php';
require_once __DIR__ . '/../src/sponsor_leistungen.php';
require_once __DIR__ . '/../src/sponsor_status.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();

// Kandidaten: zugesagte Sponsoren. Nach dem Versand rutscht der Sponsor auf 'bestätigt' und
// verschwindet damit aus dieser Liste — die Liste ist die offene Arbeit, nicht das Archiv.
$kandidaten = [];
try {
    $stmt = $pdo->query("
        SELECT s.id, s.firma, s.paket, s.kein_kontakt,
               (SELECT COUNT(*) FROM sponsor_ansprechpartner a
                 WHERE a.sponsor_id = s.id AND a.email <> '' AND a.im_anschreiben = 1) AS empfaenger
        FROM sponsors s
        WHERE s.status = 'zugesagt'
        ORDER BY s.firma
    ");
    $kandidaten = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabelle evtl. noch nicht da — Seite bleibt bedienbar.
}

// Je Kandidat die beiden Gutschein-Werte vorberechnen: Basis für die weiche Prüfung beim Senden
// und für die Ampel in der Liste.
foreach ($kandidaten as $i => $k) {
    $typ    = (string) ($k['paket'] ?? '') !== '' ? (string) $k['paket'] : null;
    $anzahl = sponsorStartplaetzeAnzahl($typ);
    $code   = sponsorGutscheincode($pdo, (int) $k['id']);
    $kandidaten[$i]['startplaetze'] = $anzahl;
    $kandidaten[$i]['code']         = $code;
    // Warnfall: Startplätze sind vereinbart, aber es liegt kein Code in der Matrix. Bewusst an
    // „vereinbart" gehängt und nicht an der Stückzahl — sonst bliebe der Hauptsponsor (Menge
    // individuell, also null) ohne Warnung, obwohl er Startplätze bekommt.
    $kandidaten[$i]['code_fehlt']   = sponsorStartplaetzeVereinbart($pdo, (int) $k['id'], $typ) && $code === '';
}

$sponsorId = (int) ($_GET['sponsor_id'] ?? 0);
$gewaehlt = null;
foreach ($kandidaten as $k) {
    if ((int) $k['id'] === $sponsorId) {
        $gewaehlt = $k;
        break;
    }
}
if ($gewaehlt === null) {
    $sponsorId = 0;
}

$vorlage = sponsorBriefLoad($pdo, 'bestaetigung', (int) $user['id']);
$default = sponsorBriefDefaults()['bestaetigung'];
$platzhalter = sponsorBriefPlatzhalterHilfe('bestaetigung');

$typLabel = static fn (?string $p): string => match ($p) {
    'hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber',
    'bronze' => 'Bronze', 'sachsponsor' => 'Sachsponsor', default => '– kein Typ –',
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Bestätigungen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .best-card { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.5rem; margin-bottom: 1.25rem; }
        .best-intro { font-size: 0.9rem; color: var(--text); line-height: 1.6; margin: 0 0 1rem; }
        .best-empty { font-size: 0.9rem; color: var(--text-light); }
        .best-liste { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
        .best-item {
            display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
            padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 6px;
            background: var(--bg); font-size: 0.9rem;
        }
        .best-item.aktiv { border-color: var(--primary); background: rgba(0, 150, 64, 0.07); }
        .best-item .firma { font-weight: 600; flex: 1; min-width: 12rem; word-break: break-word; }
        .best-tag { font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 12px; background: var(--white); border: 1px solid var(--border); color: var(--text-light); white-space: nowrap; }
        .best-tag.warn { background: rgba(255, 193, 7, 0.18); border-color: rgba(255, 193, 7, 0.6); color: var(--text); }
        .best-tag.stop { background: var(--error-bg); border-color: var(--error); color: var(--error); }
        .best-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 900px) { .best-split { grid-template-columns: 1fr; } }
        .best-split h3 { font-size: 0.9rem; color: var(--text-light); margin: 0 0 0.5rem; }
        #koerper_md {
            width: 100%; min-height: 420px; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 4px; font-family: monospace; font-size: 0.85rem; line-height: 1.5;
            box-sizing: border-box; resize: vertical;
        }
        #preview-frame { width: 100%; height: 420px; border: 1px solid var(--border); border-radius: 4px; background: #fff; box-sizing: border-box; }
        .baustein-panel { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 0.85rem 1rem; margin-bottom: 1rem; }
        .baustein-panel > h4 { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-light); margin: 0 0 0.4rem; }
        .baustein-item { border-bottom: 1px solid var(--border); }
        .baustein-item:last-of-type { border-bottom: none; }
        .baustein-header { display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0; }
        .baustein-header label { font-size: 0.875rem; cursor: pointer; flex: 1; user-select: none; }
        .baustein-header input[type=checkbox] { accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
        .baustein-expand-btn { font-size: 0.72rem; background: none; border: 1px solid var(--border); border-radius: 3px; padding: 0.1rem 0.4rem; cursor: pointer; color: var(--text-light); white-space: nowrap; line-height: 1.5; }
        .baustein-body { padding: 0.3rem 0 0.6rem 1.4rem; }
        .baustein-text { width: 100%; min-height: 72px; padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-family: monospace; font-size: 0.78rem; line-height: 1.4; box-sizing: border-box; resize: vertical; background: var(--white); }
        .best-actions { display: flex; gap: 1rem; margin-top: 1.25rem; align-items: center; flex-wrap: wrap; }
        .best-hint { font-size: 0.8rem; color: var(--text-light); }
        .best-warn { font-size: 0.85rem; color: var(--text); background: rgba(255,193,7,0.15); border: 1px solid rgba(255,193,7,0.55); border-radius: 6px; padding: 0.6rem 0.8rem; margin: 0 0 1rem; line-height: 1.5; }
        .best-ph { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.75rem 0; align-items: center; }
        .ph-chip { font-family: monospace; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: var(--bg); border: 1px solid var(--border); cursor: pointer; color: var(--text); }
    </style>
</head>
<body>
<?php $activeNav = 'bestaetigungen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Bestätigungen</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="best-card">
                <p class="best-intro">
                    Hier stehen alle <strong>zugesagten</strong> Sponsoren. Einen auswählen, die
                    Bestätigung mit dessen echten Daten zusammenstellen, in der Vorschau prüfen und
                    senden. Nach dem Versand wandert der Sponsor auf Status
                    <strong>Bestätigt</strong> und verschwindet aus dieser Liste; die Bestätigung
                    wird zusätzlich als Beleg-PDF im Drive-Ordner des Sponsors abgelegt.
                </p>

                <?php if (!$kandidaten): ?>
                    <p class="best-empty">Keine zugesagten Sponsoren offen — hier ist gerade nichts zu tun.</p>
                <?php else: ?>
                <ul class="best-liste">
                    <?php foreach ($kandidaten as $k): ?>
                        <li class="best-item<?= (int) $k['id'] === $sponsorId ? ' aktiv' : '' ?>">
                            <span class="firma"><?= htmlspecialchars($k['firma']) ?></span>
                            <span class="best-tag"><?= htmlspecialchars($typLabel($k['paket'])) ?></span>
                            <?php if ($k['startplaetze'] !== null): ?>
                                <span class="best-tag"><?= (int) $k['startplaetze'] ?>× Startplatz</span>
                            <?php endif; ?>
                            <?php if ($k['code_fehlt']): ?>
                                <span class="best-tag warn" title="Das Paket sieht Startplätze vor, in der Leistungs-Matrix steht aber kein Gutscheincode.">Gutscheincode fehlt</span>
                            <?php endif; ?>
                            <?php if ((int) $k['kein_kontakt'] === 1): ?>
                                <span class="best-tag stop">Kein Kontakt</span>
                            <?php elseif ((int) $k['empfaenger'] === 0): ?>
                                <span class="best-tag stop">Kein Empfänger</span>
                            <?php endif; ?>
                            <a class="btn btn-secondary btn-small" href="bestaetigungen.php?sponsor_id=<?= (int) $k['id'] ?>#compose">
                                <?= (int) $k['id'] === $sponsorId ? 'ausgewählt' : 'auswählen' ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <?php if ($gewaehlt !== null): ?>
            <div class="best-card" id="compose">
                <h2 style="font-size:1.05rem;margin:0 0 0.25rem">
                    Bestätigung für <?= htmlspecialchars($gewaehlt['firma']) ?>
                </h2>
                <p class="best-hint" style="margin:0 0 1rem">
                    <?= htmlspecialchars($typLabel($gewaehlt['paket'])) ?>
                    <?php if ($gewaehlt['startplaetze'] !== null): ?>
                        · <?= (int) $gewaehlt['startplaetze'] ?> freie Startplätze
                    <?php endif; ?>
                    <?php if ($gewaehlt['code'] !== ''): ?>
                        · Gutscheincode <code><?= htmlspecialchars($gewaehlt['code']) ?></code>
                    <?php endif; ?>
                    · <?= (int) $gewaehlt['empfaenger'] ?> Empfänger
                </p>

                <?php if ($gewaehlt['code_fehlt']): ?>
                <p class="best-warn">
                    ⚠️ Das Paket sieht <?= (int) $gewaehlt['startplaetze'] ?> freie Startplätze vor, in der
                    <a href="leistungen.php">Leistungs-Matrix</a> steht aber kein Gutscheincode. Der
                    Platzhalter <code>{{gutscheincode}}</code> bleibt dann leer. Senden ist trotzdem
                    möglich — du wirst vorher noch einmal gefragt.
                </p>
                <?php endif; ?>

                <div class="baustein-panel">
                    <h4>Abschnitte</h4>
                    <?php foreach (sponsorBestaetigungSektionen() as $sek): ?>
                    <div class="baustein-item">
                        <div class="baustein-header">
                            <input type="checkbox" id="baustein-<?= $sek['id'] ?>" class="baustein-cb"
                                   data-id="<?= htmlspecialchars($sek['id']) ?>" <?= $sek['checked'] ? 'checked' : '' ?>>
                            <label for="baustein-<?= $sek['id'] ?>"><?= htmlspecialchars($sek['titel']) ?></label>
                            <button type="button" class="baustein-expand-btn" data-id="<?= $sek['id'] ?>">Text ▾</button>
                        </div>
                        <div class="baustein-body" id="baustein-body-<?= $sek['id'] ?>" hidden>
                            <textarea class="baustein-text" data-id="<?= htmlspecialchars($sek['id']) ?>"><?= htmlspecialchars($sek['text']) ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:0.65rem">
                        <button type="button" class="btn btn-secondary btn-small" id="btn-zusammenstellen">Zusammenstellen →</button>
                    </div>
                </div>

                <label for="betreff"><strong>Betreff</strong></label>
                <input type="text" id="betreff" maxlength="255" value="<?= htmlspecialchars($vorlage['betreff']) ?>"
                       style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:4px;font-size:0.95rem;box-sizing:border-box">

                <div class="best-ph">
                    <span class="best-hint">Platzhalter einfügen:</span>
                    <?php foreach ($platzhalter as $ph => $beschreibung): ?>
                        <span class="ph-chip" data-ph="<?= htmlspecialchars($ph) ?>" title="<?= htmlspecialchars($beschreibung) ?>"><?= htmlspecialchars($ph) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="best-split">
                    <div>
                        <h3>Markdown</h3>
                        <textarea id="koerper_md"><?= htmlspecialchars($vorlage['koerper_md']) ?></textarea>
                    </div>
                    <div>
                        <h3>Vorschau — echte Daten von <?= htmlspecialchars($gewaehlt['firma']) ?></h3>
                        <iframe id="preview-frame" sandbox="" title="Vorschau"></iframe>
                    </div>
                </div>

                <form method="post" action="api/sponsor_versand.php" id="send-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="anschreiben_typ" value="bestaetigung">
                    <input type="hidden" name="sponsor_id" value="<?= (int) $gewaehlt['id'] ?>">
                    <input type="hidden" name="redirect_to" value="bestaetigungen.php">
                    <div class="best-actions">
                        <button type="button" class="btn btn-primary" id="btn-senden"
                                <?= ((int) $gewaehlt['kein_kontakt'] === 1 || (int) $gewaehlt['empfaenger'] === 0) ? 'disabled' : '' ?>>
                            Bestätigung senden
                        </button>
                        <span id="send-status" class="best-hint"></span>
                    </div>
                </form>
                <?php if ((int) $gewaehlt['kein_kontakt'] === 1): ?>
                    <p class="best-hint">Dieser Sponsor ist als „Kein Kontakt" markiert — Versand ist gesperrt.</p>
                <?php elseif ((int) $gewaehlt['empfaenger'] === 0): ?>
                    <p class="best-hint">Kein Ansprechpartner mit E-Mail und Haken „im Anschreiben" — bitte in der <a href="sponsor_form.php?id=<?= (int) $gewaehlt['id'] ?>">Sponsor-Maske</a> ergänzen.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
    <script>
    <?php if ($gewaehlt !== null): ?>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const sponsorId = <?= (int) $gewaehlt['id'] ?>;
        const codeFehlt = <?= $gewaehlt['code_fehlt'] ? 'true' : 'false' ?>;
        const firma = <?= json_encode($gewaehlt['firma'], JSON_UNESCAPED_UNICODE) ?>;
        const INTRO = <?= json_encode(
            "{{anrede}}\n\nherzlichen Dank, dass Sie den Marktlauf Kirchseeon am **{{event_datum}}** als **{{paket_text}}** unterstützen. Wir freuen uns sehr über Ihre Zusage und die Zusammenarbeit!\n\nDamit wir Ihren Markenauftritt optimal vorbereiten können, bitten wir Sie, uns folgende Unterlagen und Informationen zukommen zu lassen:",
            JSON_UNESCAPED_UNICODE
        ) ?>;
        const OUTRO = <?= json_encode(
            "Sollte Ihnen etwas fehlen oder Sie noch Fragen haben, kommen Sie jederzeit gerne auf mich zu.\n\nVielen Dank für Ihre Unterstützung und Ihr Vertrauen – gemeinsam machen wir den Marktlauf Kirchseeon zu einem unvergesslichen Erlebnis!\n\n{{signatur}}",
            JSON_UNESCAPED_UNICODE
        ) ?>;

        const ta = document.getElementById('koerper_md');
        const betreff = document.getElementById('betreff');
        const frame = document.getElementById('preview-frame');
        let timer = null;

        // Vorschau mit den echten Daten dieses Sponsors — derselbe Renderer wie der Versand.
        function renderPreview() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('koerper_md', ta.value);
            body.set('slug', 'bestaetigung');
            body.set('sponsor_id', String(sponsorId));
            fetch('api/sponsor_brief_preview.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                .then(function(r) { return r.text(); })
                .then(function(html) { frame.srcdoc = html; })
                .catch(function() { /* Vorschau optional */ });
        }
        function schedule() { clearTimeout(timer); timer = setTimeout(renderPreview, 400); }
        ta.addEventListener('input', schedule);
        renderPreview();

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

        document.querySelectorAll('.baustein-expand-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const body = document.getElementById('baustein-body-' + btn.dataset.id);
                if (!body) return;
                const open = body.hidden;
                body.hidden = !open;
                btn.textContent = open ? 'Text ▴' : 'Text ▾';
            });
        });

        document.getElementById('btn-zusammenstellen').addEventListener('click', function() {
            if (ta.value.trim() !== '' && !confirm('Den aktuellen Text überschreiben und neu zusammenstellen?')) return;
            const parts = [INTRO];
            document.querySelectorAll('.baustein-cb').forEach(function(cb) {
                if (!cb.checked) return;
                const t = document.querySelector('.baustein-text[data-id="' + cb.dataset.id + '"]');
                if (t && t.value.trim() !== '') parts.push(t.value);
            });
            parts.push(OUTRO);
            ta.value = parts.join('\n\n');
            renderPreview();
        });

        // Senden: weiche Prüfung → Text als Entwurf sichern → erst dann abschicken.
        // Die Reihenfolge ist zwingend: Versand UND Beleg-PDF lesen den Entwurf.
        const sendBtn = document.getElementById('btn-senden');
        const statusEl = document.getElementById('send-status');
        if (sendBtn) {
            sendBtn.addEventListener('click', function() {
                if (codeFehlt && !confirm(
                    'Für ' + firma + ' ist kein Gutscheincode hinterlegt — die Stelle im Brief bleibt leer.\n\n' +
                    'OK = trotzdem senden\nAbbrechen = zurück, Code in der Leistungs-Matrix nachtragen'
                )) return;
                if (!confirm('Bestätigung jetzt an ' + firma + ' senden?')) return;

                sendBtn.disabled = true;
                statusEl.textContent = 'Text wird gesichert…';
                const body = new URLSearchParams();
                body.set('csrf_token', csrf);
                body.set('vorlage_art', 'sponsor');
                body.set('slug', 'bestaetigung');
                body.set('betreff', betreff.value);
                body.set('koerper_md', ta.value);
                fetch('api/draft_save.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.ok) throw new Error(data.error || 'Unbekannt');
                        statusEl.textContent = 'Wird gesendet…';
                        document.getElementById('send-form').submit();
                    })
                    .catch(function(e) {
                        sendBtn.disabled = false;
                        statusEl.textContent = 'Nicht gesendet — Text konnte nicht gesichert werden (' + e.message + ').';
                    });
            });
        }
    })();
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
