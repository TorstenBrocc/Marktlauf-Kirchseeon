<?php
/**
 * Übersicht "Kuchen & Sonstiges" (Admin + Orga)
 *
 * Trennt die Anmelde-Abfragen, die NICHTS mit dem Helferkontakt zu tun haben,
 * aus der Helferübersicht heraus: gesammelte Ausgabe der Kuchen-/Gebäck-Zusagen
 * und der sonstigen Unterstützung (Freitext). Pro Helfer zeigt ein "i"-Icon per
 * Mouseover, wobei er außerdem im Einsatzplan hilft (umgekehrter Inhalt zum
 * "i"-Tooltip im Einsatzplan).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

$pdo = getDbConnection();

// Kuchen-Zusagen
$kuchen = $pdo->query('
    SELECT h.id, h.vorname, h.nachname, h.email, h.phone, hb.freitext
    FROM helfer_beitrag hb
    JOIN helfer h ON h.id = hb.helfer_id
    WHERE hb.typ = "kuchen"
    ORDER BY h.nachname, h.vorname
')->fetchAll();

// Sonstige Unterstützung
$sonstiges = $pdo->query('
    SELECT h.id, h.vorname, h.nachname, h.email, h.phone, hb.freitext
    FROM helfer_beitrag hb
    JOIN helfer h ON h.id = hb.helfer_id
    WHERE hb.typ = "sonstiges"
    ORDER BY h.nachname, h.vorname
')->fetchAll();

// Einsatzplan-Unterstützung je Helfer (Selbstmeldung) für das "i"-Tooltip.
$einsatzProHelfer = [];
$eStmt = $pdo->query('
    SELECT hs.helfer_id, hs.tag, hs.aufgabe, hs.zeitfenster
    FROM helfer_slots hs
    ORDER BY hs.tag
');
foreach ($eStmt as $row) {
    $tag = !empty($row['tag']) ? date('d.m.', strtotime((string) $row['tag'])) . ' ' : '';
    $einsatzProHelfer[(int) $row['helfer_id']][] = trim($tag . ($row['aufgabe'] ?? '') . ' (' . ($row['zeitfenster'] ?? '') . ')');
}

/** Kuchen-Freitext in Art + Nüsse-Flag zerlegen (Marker aus der Anmeldung: " | enthält Nüsse"). */
function kuchenParts(?string $freitext): array {
    $freitext = (string) $freitext;
    $nuesse = str_contains($freitext, 'enthält Nüsse');
    $art = trim(str_replace(['| enthält Nüsse', 'enthält Nüsse', '|'], '', $freitext));
    return ['art' => $art, 'nuesse' => $nuesse];
}

/** Tooltip: wobei der Helfer außerdem im Einsatzplan hilft. */
function einsatzTooltip(array $einsatzProHelfer, int $helferId): string {
    $list = $einsatzProHelfer[$helferId] ?? [];
    return $list ? implode(' | ', $list) : '';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kuchen &amp; Sonstiges | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .beitrag-section { margin-bottom: 2.5rem; }
        .beitrag-section h2 {
            font-size: 1.1rem; margin-bottom: 0.9rem;
            padding-bottom: 0.4rem; border-bottom: 2px solid var(--primary);
        }
        .beitrag-kachel { display: flex; flex-direction: column; gap: 0.4rem; }
        .bk-name { font-weight: 600; display: flex; align-items: center; }
        .bk-contact a { display: block; font-size: 0.8rem; }
        .bk-inhalt { font-size: 0.9rem; margin-top: 0.15rem; }
        .nuesse-badge {
            display: inline-block; margin-left: 0.4rem; padding: 0.1rem 0.45rem;
            background: #fffbea; border: 1px solid #f59e0b; border-radius: 4px;
            font-size: 0.7rem; font-weight: 600; white-space: nowrap;
        }
        .info-i {
            display: inline-flex; align-items: center; justify-content: center;
            width: 15px; height: 15px; border-radius: 50%;
            background: var(--text-light); color: var(--white);
            font-size: 0.65rem; font-style: italic; font-weight: 700;
            cursor: help; user-select: none; margin-left: 0.4rem;
        }
        .empty-hint { color: var(--text-light); font-size: 0.875rem; }
    </style>
</head>
<body>
<?php $activeNav = 'beitraege'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Kuchen &amp; Sonstiges</h1>
            </header>

            <!-- Kuchen -->
            <section class="beitrag-section">
                <h2>Kuchen / Gebäck <span style="font-weight:400;color:var(--text-light);font-size:0.9rem;">(<?= count($kuchen) ?>)</span></h2>
                <?php if (empty($kuchen)): ?>
                    <p class="empty-hint">Noch keine Kuchen-Zusagen.</p>
                <?php else: ?>
                    <div class="kachel-grid">
                        <?php foreach ($kuchen as $k): ?>
                            <?php $p = kuchenParts($k['freitext']); $tt = einsatzTooltip($einsatzProHelfer, (int) $k['id']); ?>
                            <div class="kachel beitrag-kachel">
                                <div class="bk-name">
                                    <?= htmlspecialchars($k['vorname'] . ' ' . $k['nachname']) ?>
                                    <?php if ($tt !== ''): ?>
                                        <span class="info-i" tabindex="0" title="Hilft außerdem im Einsatzplan — <?= htmlspecialchars($tt) ?>">i</span>
                                    <?php endif; ?>
                                </div>
                                <div class="bk-inhalt">
                                    🍰 <?= $p['art'] !== '' ? htmlspecialchars($p['art']) : '<span class="empty-hint">ohne Angabe</span>' ?>
                                    <?php if ($p['nuesse']): ?><span class="nuesse-badge">⚠️ enthält Nüsse</span><?php endif; ?>
                                </div>
                                <div class="bk-contact">
                                    <a href="mailto:<?= htmlspecialchars($k['email']) ?>"><?= htmlspecialchars($k['email']) ?></a>
                                    <a href="tel:<?= htmlspecialchars($k['phone']) ?>"><?= htmlspecialchars($k['phone']) ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Sonstige Unterstützung -->
            <section class="beitrag-section">
                <h2>Sonstige Unterstützung <span style="font-weight:400;color:var(--text-light);font-size:0.9rem;">(<?= count($sonstiges) ?>)</span></h2>
                <?php if (empty($sonstiges)): ?>
                    <p class="empty-hint">Noch keine sonstige Unterstützung angeboten.</p>
                <?php else: ?>
                    <div class="kachel-grid">
                        <?php foreach ($sonstiges as $s): ?>
                            <?php $tt = einsatzTooltip($einsatzProHelfer, (int) $s['id']); ?>
                            <div class="kachel beitrag-kachel">
                                <div class="bk-name">
                                    <?= htmlspecialchars($s['vorname'] . ' ' . $s['nachname']) ?>
                                    <?php if ($tt !== ''): ?>
                                        <span class="info-i" tabindex="0" title="Hilft außerdem im Einsatzplan — <?= htmlspecialchars($tt) ?>">i</span>
                                    <?php endif; ?>
                                </div>
                                <div class="bk-inhalt"><?= nl2br(htmlspecialchars((string) $s['freitext'])) ?: '<span class="empty-hint">ohne Angabe</span>' ?></div>
                                <div class="bk-contact">
                                    <a href="mailto:<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email']) ?></a>
                                    <a href="tel:<?= htmlspecialchars($s['phone']) ?>"><?= htmlspecialchars($s['phone']) ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
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
