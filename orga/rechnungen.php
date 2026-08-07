<?php
/**
 * Sponsoring-Rechnungen — Kassier-Ansicht.
 * Oben: Entwürfe, die auf die fortlaufende Nummer warten (Inline-Vergabe).
 * Unten: bereits nummerierte Rechnungen. PDF wird deterministisch aus dem
 * gespeicherten Snapshot gerendert (api/rechnung_download.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/rechnung.php';
require_once __DIR__ . '/../src/rechnung_repo.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();
$alle = rechnungenListe($pdo);
$entwuerfe   = array_values(array_filter($alle, static fn ($r) => $r['status'] === 'entwurf'));
$nummeriert  = array_values(array_filter($alle, static fn ($r) => $r['status'] === 'nummeriert'));

$eur = static fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';

// Für nummerierte Rechnungen: mögliche Empfänger-Adressen + Versand-Historie
$versandDaten = [];
foreach ($nummeriert as $r) {
    $rid = (int) $r['id'];
    $versandDaten[$rid] = [
        'emails'   => rechnungSponsorEmails($pdo, (int) $r['sponsor_id']),
        'historie' => rechnungVersandHistorie($pdo, $rid),
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Rechnungen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .rech-intro { font-size: 0.9rem; color: var(--text-light); margin-bottom: 1.25rem; line-height: 1.5; }
        .rech-section-title { font-size: 1.05rem; margin: 1.5rem 0 0.75rem; }
        .rech-card {
            background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card);
            border: 1px solid var(--border); padding: 1rem 1.25rem; margin-bottom: 0.85rem;
        }
        .rech-card.wartet { border-left: 4px solid var(--primary); }
        .rech-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.5rem 1rem; }
        .rech-firma { font-weight: 600; font-size: 1rem; }
        .rech-meta { font-size: 0.82rem; color: var(--text-light); }
        .rech-betrag { margin-left: auto; font-weight: 600; color: var(--primary); white-space: nowrap; }
        .rech-leistung { font-size: 0.85rem; color: var(--text); margin: 0.5rem 0 0; line-height: 1.45; }
        .rech-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-top: 0.85rem; }
        .rech-nr-form { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .rech-nr-form input[type=text] {
            width: 120px; padding: 0.4rem 0.6rem; border: 1px solid var(--border);
            border-radius: 4px; font-size: 0.9rem;
        }
        .rech-nr-badge {
            font-weight: 600; background: var(--bg); border: 1px solid var(--border);
            border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.9rem;
        }
        .rech-empty { color: var(--text-light); font-size: 0.9rem; padding: 0.5rem 0; }
        .rech-hint { font-size: 0.78rem; color: var(--text-light); }
        .rech-nr-form select {
            padding: 0.35rem 0.5rem; border: 1px solid var(--border);
            border-radius: 4px; font-size: 0.85rem; max-width: 260px;
        }
        .rech-hist { margin-top: 0.6rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); }
        .rech-hist-row { font-size: 0.78rem; color: var(--text-light); line-height: 1.5; }
    </style>
</head>
<body>
<?php $activeNav = 'rechnungen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Rechnungen</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <p class="rech-intro">
                Sponsoring-Rechnungen werden aus der <a href="sponsoren.php">Sponsorenliste</a> erzeugt
                (Sponsoren auswählen → „Rechnung erzeugen"). Jede Rechnung wartet hier als Entwurf,
                bis die fortlaufende Rechnungsnummer (Format <strong>NN-JJJJ</strong>, z.&nbsp;B. 05-2026)
                vergeben ist. Danach ist das PDF endgültig.
            </p>

            <h2 class="rech-section-title">Wartet auf Nummer<?= $entwuerfe ? ' (' . count($entwuerfe) . ')' : '' ?></h2>
            <?php if (!$entwuerfe): ?>
                <p class="rech-empty">Keine offenen Entwürfe.</p>
            <?php else: ?>
                <?php foreach ($entwuerfe as $r): ?>
                    <div class="rech-card wartet">
                        <div class="rech-head">
                            <span class="rech-firma"><?= htmlspecialchars($r['empfaenger_firma']) ?></span>
                            <span class="rech-meta">
                                erstellt <?= htmlspecialchars(date('d.m.Y', strtotime((string) $r['erstellt_am']))) ?>
                                <?= !empty($r['erstellt_name']) ? '· ' . htmlspecialchars($r['erstellt_name']) : '' ?>
                            </span>
                            <span class="rech-betrag"><?= $eur($r['brutto']) ?> brutto</span>
                        </div>
                        <p class="rech-leistung"><?= htmlspecialchars($r['leistung']) ?><br>
                            <span class="rech-hint">Leistungszeitraum: <?= htmlspecialchars($r['zeitraum']) ?> ·
                            Netto <?= $eur($r['netto']) ?> + <?= $eur($r['ust_betrag']) ?> USt</span>
                        </p>
                        <div class="rech-actions">
                            <a href="api/rechnung_download.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener"
                               class="btn btn-small btn-secondary">Entwurf ansehen (PDF)</a>
                            <form method="post" action="api/rechnung_crud.php" class="rech-nr-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="assign_number">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <label for="nr-<?= (int) $r['id'] ?>" class="rech-hint">Rechnungs-Nr.:</label>
                                <input type="text" id="nr-<?= (int) $r['id'] ?>" name="rechnungsnummer"
                                       placeholder="05-2026" pattern="\d{1,4}-\d{4}" required
                                       title="Format NN-JJJJ, z. B. 05-2026">
                                <button type="submit" class="btn btn-small btn-primary">Nummer vergeben</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2 class="rech-section-title">Nummeriert<?= $nummeriert ? ' (' . count($nummeriert) . ')' : '' ?></h2>
            <?php if (!$nummeriert): ?>
                <p class="rech-empty">Noch keine nummerierten Rechnungen.</p>
            <?php else: ?>
                <?php foreach ($nummeriert as $r): ?>
                    <div class="rech-card">
                        <div class="rech-head">
                            <span class="rech-nr-badge"><?= htmlspecialchars((string) $r['rechnungsnummer']) ?></span>
                            <span class="rech-firma"><?= htmlspecialchars($r['empfaenger_firma']) ?></span>
                            <span class="rech-meta">
                                Nr. vergeben <?= !empty($r['nummer_am']) ? htmlspecialchars(date('d.m.Y', strtotime((string) $r['nummer_am']))) : '' ?>
                                <?= !empty($r['nummer_name']) ? '· ' . htmlspecialchars($r['nummer_name']) : '' ?>
                            </span>
                            <span class="rech-betrag"><?= $eur($r['brutto']) ?> brutto</span>
                        </div>
                        <?php
                            $rid = (int) $r['id'];
                            $emails = $versandDaten[$rid]['emails'] ?? [];
                            $hist   = $versandDaten[$rid]['historie'] ?? [];
                            $schonGesendet = false;
                            foreach ($hist as $h) { if ($h['ergebnis'] === 'ok') { $schonGesendet = true; break; } }
                        ?>
                        <div class="rech-actions">
                            <a href="api/rechnung_download.php?id=<?= $rid ?>" target="_blank" rel="noopener"
                               class="btn btn-small btn-secondary">Rechnung öffnen (PDF)</a>
                            <?php if ($emails): ?>
                                <form method="post" action="api/rechnung_versand.php" class="rech-nr-form"
                                      onsubmit="return confirmVersand(this, <?= $schonGesendet ? 'true' : 'false' ?>);">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $rid ?>">
                                    <label class="rech-hint" for="empf-<?= $rid ?>">An:</label>
                                    <select id="empf-<?= $rid ?>" name="empfaenger">
                                        <?php foreach ($emails as $em): ?>
                                            <option value="<?= htmlspecialchars($em) ?>"><?= htmlspecialchars($em) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-small btn-primary"><?= $schonGesendet ? 'Erneut senden' : 'An Sponsor senden' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="rech-hint">Keine E-Mail-Adresse hinterlegt — bitte beim Sponsor ergänzen.</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hist): ?>
                            <div class="rech-hist">
                                <?php foreach ($hist as $h): ?>
                                    <div class="rech-hist-row">
                                        <?= $h['ergebnis'] === 'ok' ? '✓ gesendet' : '✗ Fehler' ?>
                                        · <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) $h['versendet_am']))) ?>
                                        · <?= htmlspecialchars($h['empfaenger']) ?>
                                        <?= !empty($h['von_name']) ? ' · ' . htmlspecialchars($h['von_name']) : '' ?>
                                        <?= $h['ergebnis'] !== 'ok' && !empty($h['hinweis']) ? ' · ' . htmlspecialchars($h['hinweis']) : '' ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
    <script>
    function confirmVersand(form, schonGesendet) {
        var sel = form.querySelector('select[name="empfaenger"]');
        var to = sel ? sel.value : '';
        var msg = (schonGesendet
            ? 'Diese Rechnung wurde bereits gesendet. ERNEUT senden'
            : 'Rechnung senden')
            + ' an ' + to + '?\n\nDas PDF wird zusätzlich in Google Drive abgelegt.';
        return window.confirm(msg);
    }
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!burger) return;
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
