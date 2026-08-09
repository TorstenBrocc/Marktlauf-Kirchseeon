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

// Abzurechnen: zugesagte bzw. bereits bestätigte Sponsoren mit Paket UND hinterlegtem Betrag. Ohne
// Betrag (summe) ist ein Sponsor nicht abzurechnen und erscheint nicht — steuerbar über das
// Betrag-Feld am Sponsor.
$abzurechnen = [];
try {
    $stmt = $pdo->query("SELECT id, firma, paket, summe FROM sponsors WHERE status IN ('zugesagt','bestaetigt') AND paket IS NOT NULL AND paket <> '' AND summe > 0 ORDER BY firma");
    $abzurechnen = $stmt->fetchAll();
} catch (PDOException $e) {
    // ignore
}
$paketLabel = static function (?string $p): string {
    return match ($p) {
        'hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber', 'bronze' => 'Bronze',
        default => '— kein Paket —',
    };
};
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
        .rech-section-title { font-size: 1.05rem; margin: 1.75rem 0 0.6rem; }
        .rech-empty { color: var(--text-light); font-size: 0.9rem; padding: 0.25rem 0 0.75rem; }
        .rech-hint { font-size: 0.78rem; color: var(--text-light); }
        /* Tabellen — scoped Kopie des data-table-Systems aus sponsoren.php.
           Bewusste Übergangs-Duplizierung (Design-System-Branch läuft parallel);
           beim Merge nach orga.css zusammenführen. */
        .table-wrap { overflow-x: auto; border-radius: 8px; box-shadow: var(--shadow-card); margin-bottom: 0.5rem; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--white); border-radius: 8px; overflow: hidden; }
        .data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { background: var(--bg); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); }
        .data-table tr:hover { background: #fafafa; }
        .data-table td { font-size: 0.875rem; vertical-align: middle; }
        .rech-firma { font-weight: 600; }
        .rech-betrag { font-weight: 600; color: var(--primary); white-space: nowrap; }
        .rech-nr-badge { font-weight: 600; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 0.2rem 0.5rem; font-size: 0.85rem; white-space: nowrap; }
        .rech-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .rech-nr-form { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
        .rech-nr-input { width: 68px; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.9rem; text-align: right; }
        .rech-nr-suffix { font-size: 0.9rem; color: var(--text-light); }
        .rech-nr-form select { padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; max-width: 220px; }
        .rech-versand-ok { color: var(--primary); white-space: nowrap; }
        .rech-versand-none { color: var(--text-light); }
        .rech-hist { margin-top: 0.4rem; }
        .rech-hist summary { cursor: pointer; font-size: 0.78rem; color: var(--text-light); }
        .rech-hist-row { font-size: 0.78rem; color: var(--text-light); line-height: 1.5; margin-top: 0.2rem; }
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
                Ablauf: unten <strong>Abzurechnen</strong> stehen alle zugesagten und bestätigten
                Sponsoren mit Paket und hinterlegtem Betrag — hier <strong>Entwurf erzeugen</strong>.
                Sponsoren ohne Betrag
                erscheinen nicht; den Betrag pflegst du am Sponsor. Die Rechnung wandert dann als Entwurf nach
                <strong>Wartet auf Nummer</strong>: Dort trägt der Kassier die fortlaufende
                Rechnungs-<strong>Nummer</strong> ein (nur die laufende Zahl, das Jahr <?= date('Y') ?>
                ergänzt das System automatisch). Nach der Nummernvergabe kann die Rechnung unter
                <strong>Nummeriert</strong> an den Sponsor gesendet werden. Beim Erzeugen wird der
                Sponsor automatisch auf Status „Abgerechnet" gesetzt.
            </p>

            <h2 class="rech-section-title">Abzurechnen<?= $abzurechnen ? ' (' . count($abzurechnen) . ')' : '' ?></h2>
            <?php if (!$abzurechnen): ?>
                <p class="rech-empty">Keine zugesagten oder bestätigten Sponsoren mit Paket und Betrag offen.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Firma</th>
                                <th>Paket</th>
                                <th>Betrag</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abzurechnen as $s): ?>
                                <tr>
                                    <td class="rech-firma"><?= htmlspecialchars($s['firma']) ?></td>
                                    <td><?= htmlspecialchars($paketLabel($s['paket'])) ?></td>
                                    <td class="rech-betrag"><?= $eur($s['summe']) ?></td>
                                    <td>
                                        <div class="rech-actions">
                                            <a href="sponsor_form.php?id=<?= (int) $s['id'] ?>" class="btn btn-small btn-secondary">Sponsor öffnen</a>
                                            <form method="post" action="api/rechnung_crud.php"
                                                  onsubmit="return confirm('Entwurf für <?= htmlspecialchars(addslashes($s['firma']), ENT_QUOTES) ?> erzeugen?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="generate">
                                                <input type="hidden" name="sponsor_ids[]" value="<?= (int) $s['id'] ?>">
                                                <button type="submit" class="btn btn-small btn-primary">Entwurf erzeugen</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h2 class="rech-section-title">Wartet auf Nummer<?= $entwuerfe ? ' (' . count($entwuerfe) . ')' : '' ?></h2>
            <?php if (!$entwuerfe): ?>
                <p class="rech-empty">Keine offenen Entwürfe.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Firma</th>
                                <th>Erstellt</th>
                                <th>Betrag</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entwuerfe as $r): ?>
                                <tr>
                                    <td class="rech-firma"><?= htmlspecialchars($r['empfaenger_firma']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(date('d.m.Y', strtotime((string) $r['erstellt_am']))) ?>
                                        <?php if (!empty($r['erstellt_name'])): ?>
                                            <div class="rech-hint"><?= htmlspecialchars($r['erstellt_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="rech-betrag"><?= $eur($r['brutto']) ?> <span class="rech-hint">brutto</span></td>
                                    <td>
                                        <div class="rech-actions">
                                            <a href="api/rechnung_download.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener"
                                               class="btn btn-small btn-secondary">Entwurf ansehen (PDF)</a>
                                            <form method="post" action="api/rechnung_crud.php" class="rech-nr-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="assign_number">
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <label for="nr-<?= (int) $r['id'] ?>" class="rech-hint">Nr.:</label>
                                                <input type="text" id="nr-<?= (int) $r['id'] ?>" name="nn" class="rech-nr-input"
                                                       placeholder="05" pattern="\d{1,4}" inputmode="numeric" required
                                                       title="Laufende Nummer, z. B. 05">
                                                <span class="rech-nr-suffix">-<?= date('Y') ?></span>
                                                <button type="submit" class="btn btn-small btn-primary">Nummer vergeben</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h2 class="rech-section-title">Nummeriert<?= $nummeriert ? ' (' . count($nummeriert) . ')' : '' ?></h2>
            <?php if (!$nummeriert): ?>
                <p class="rech-empty">Noch keine nummerierten Rechnungen.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Firma</th>
                                <th>Betrag</th>
                                <th>Versand</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nummeriert as $r): ?>
                                <?php
                                    $rid = (int) $r['id'];
                                    $emails = $versandDaten[$rid]['emails'] ?? [];
                                    $hist   = $versandDaten[$rid]['historie'] ?? [];
                                    $schonGesendet = false;
                                    $letzterVersand = '';
                                    foreach ($hist as $h) {
                                        if ($h['ergebnis'] === 'ok') {
                                            $schonGesendet = true;
                                            $letzterVersand = date('d.m.Y', strtotime((string) $h['versendet_am']));
                                            break; // Historie ist absteigend sortiert → erster ok = letzter Versand
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><span class="rech-nr-badge"><?= htmlspecialchars((string) $r['rechnungsnummer']) ?></span></td>
                                    <td class="rech-firma">
                                        <?= htmlspecialchars($r['empfaenger_firma']) ?>
                                        <div class="rech-hint">
                                            Nr. vergeben <?= !empty($r['nummer_am']) ? htmlspecialchars(date('d.m.Y', strtotime((string) $r['nummer_am']))) : '' ?>
                                            <?= !empty($r['nummer_name']) ? '· ' . htmlspecialchars($r['nummer_name']) : '' ?>
                                        </div>
                                    </td>
                                    <td class="rech-betrag"><?= $eur($r['brutto']) ?> <span class="rech-hint">brutto</span></td>
                                    <td>
                                        <?php if ($schonGesendet): ?>
                                            <span class="rech-versand-ok">✓ <?= htmlspecialchars($letzterVersand) ?></span>
                                        <?php else: ?>
                                            <span class="rech-versand-none">—</span>
                                        <?php endif; ?>
                                        <?php if ($hist): ?>
                                            <details class="rech-hist">
                                                <summary>Verlauf</summary>
                                                <?php foreach ($hist as $h): ?>
                                                    <div class="rech-hist-row">
                                                        <?= $h['ergebnis'] === 'ok' ? '✓ gesendet' : '✗ Fehler' ?>
                                                        · <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) $h['versendet_am']))) ?>
                                                        · <?= htmlspecialchars($h['empfaenger']) ?>
                                                        <?= !empty($h['von_name']) ? ' · ' . htmlspecialchars($h['von_name']) : '' ?>
                                                        <?= $h['ergebnis'] !== 'ok' && !empty($h['hinweis']) ? ' · ' . htmlspecialchars($h['hinweis']) : '' ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="rech-actions">
                                            <a href="api/rechnung_download.php?id=<?= $rid ?>" target="_blank" rel="noopener"
                                               class="btn btn-small btn-secondary">PDF öffnen</a>
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
                                                    <span class="rech-hint" title="wird der Rechnungsmail automatisch angehängt">📎 inkl. Sponsoring-Bedingungen</span>
                                                </form>
                                            <?php else: ?>
                                                <span class="rech-hint">Keine E-Mail-Adresse hinterlegt — bitte beim Sponsor ergänzen.</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
            + ' an ' + to + '?\n\nAngehängt: Rechnung + Sponsoring-Bedingungen. Das Rechnungs-PDF wird zusätzlich in Google Drive abgelegt.';
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
