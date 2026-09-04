<?php
/**
 * Live-Ticker — Nachrichten für den Renntag veröffentlichen.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';

$user        = getCurrentUserFromGuard();
$isAdmin     = isAdminFromGuard();
$csrfToken   = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();

$posts = $pdo->query(
    'SELECT tp.id, tp.nachricht, tp.typ, tp.aktiv, tp.erstellt_am,
            u.name
     FROM ticker_posts tp
     LEFT JOIN users u ON u.id = tp.erstellt_von
     ORDER BY tp.erstellt_am DESC
     LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC);

$typLabels = ['info' => 'Info', 'warnung' => 'Warnung', 'ergebnis' => 'Ergebnis'];
$typColors = ['info' => '#1a73e8', 'warnung' => '#e67e22', 'ergebnis' => '#009640'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Live-Ticker | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .ticker-form-card { max-width: 640px; }

        .ticker-typ-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .ticker-typ-option { display: none; }
        .ticker-typ-label {
            padding: 0.35rem 0.9rem; border-radius: 20px; cursor: pointer; font-size: 0.85rem;
            border: 2px solid var(--border); background: var(--white); color: var(--text);
            transition: border-color 0.15s, background 0.15s; user-select: none;
        }
        .ticker-typ-option:checked + .ticker-typ-label {
            border-color: var(--primary); background: #e8f5ee; color: var(--primary); font-weight: 600;
        }
        #typ-warnung:checked  + .ticker-typ-label { border-color: #e67e22; background: #fef4eb; color: #e67e22; }
        #typ-ergebnis:checked + .ticker-typ-label { border-color: #009640; background: #e8f5ee; color: #009640; }

        .ticker-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.6rem; }
        .ticker-item {
            display: flex; align-items: flex-start; gap: 0.75rem;
            padding: 0.75rem 1rem; border-radius: 8px;
            background: var(--white); border: 1px solid var(--border);
        }
        .ticker-item.inaktiv { opacity: 0.5; }
        .ticker-typ-pill {
            flex-shrink: 0; margin-top: 2px;
            padding: 0.15rem 0.6rem; border-radius: 12px; font-size: 0.75rem;
            font-weight: 600; color: #fff;
        }
        .ticker-item-text  { flex: 1; font-size: 0.9rem; line-height: 1.45; }
        .ticker-item-meta  { font-size: 0.75rem; color: var(--text-muted, #888); margin-top: 0.2rem; }
        .ticker-item-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }

        .ticker-live-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.8rem; font-weight: 600; color: #e63946;
        }
        .ticker-live-badge::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%;
            background: #e63946; animation: tickerPulse 1.4s ease-in-out infinite;
        }
        @keyframes tickerPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    </style>
</head>
<body>
<?php $activeNav = 'live_ticker'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Live-Ticker</h1>
            <?php
            $aktiv = array_filter($posts, fn($p) => (bool)$p['aktiv']);
            if (!empty($aktiv)):
            ?>
                <span class="ticker-live-badge"><?= count($aktiv) ?> aktiv</span>
            <?php endif; ?>
        </header>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <!-- Neue Nachricht -->
        <div class="kachel ticker-form-card">
            <h2 class="kachel-title">Neue Nachricht</h2>
            <form method="post" action="api/ticker_crud.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <div class="ticker-typ-row">
                        <input type="radio" name="typ" id="typ-info"     value="info"     class="ticker-typ-option" checked>
                        <label for="typ-info"     class="ticker-typ-label">ℹ Info</label>

                        <input type="radio" name="typ" id="typ-warnung"  value="warnung"  class="ticker-typ-option">
                        <label for="typ-warnung"  class="ticker-typ-label">⚠ Warnung</label>

                        <input type="radio" name="typ" id="typ-ergebnis" value="ergebnis" class="ticker-typ-option">
                        <label for="typ-ergebnis" class="ticker-typ-label">🏁 Ergebnis</label>
                    </div>
                </div>

                <div class="form-group">
                    <textarea name="nachricht" rows="3" class="form-control"
                              placeholder="Nachricht eingeben …" required
                              style="resize: vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Veröffentlichen</button>
            </form>
        </div>

        <!-- Bestehende Einträge -->
        <?php if (empty($posts)): ?>
            <p style="color: var(--text-muted, #888); margin-top: 1rem;">Noch keine Einträge.</p>
        <?php else: ?>
            <div class="ticker-list">
                <?php foreach ($posts as $p): ?>
                    <?php
                    $color = $typColors[$p['typ']] ?? '#1a73e8';
                    $label = $typLabels[$p['typ']] ?? $p['typ'];
                    $name  = trim((string) ($p['name'] ?? ''));
                    $dt    = date('d.m. H:i', strtotime($p['erstellt_am']));
                    ?>
                    <div class="ticker-item <?= $p['aktiv'] ? '' : 'inaktiv' ?>">
                        <span class="ticker-typ-pill" style="background:<?= $color ?>;">
                            <?= htmlspecialchars($label) ?>
                        </span>
                        <div class="ticker-item-text">
                            <?= nl2br(htmlspecialchars($p['nachricht'])) ?>
                            <div class="ticker-item-meta">
                                <?= htmlspecialchars($dt) ?>
                                <?php if ($name): ?>— <?= htmlspecialchars($name) ?><?php endif; ?>
                                <?php if (!$p['aktiv']): ?><em> · deaktiviert</em><?php endif; ?>
                            </div>
                        </div>
                        <div class="ticker-item-actions">
                            <form method="post" action="api/ticker_crud.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"
                                        title="<?= $p['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                    <?= $p['aktiv'] ? '⏸' : '▶' ?>
                                </button>
                            </form>
                            <form method="post" action="api/ticker_crud.php" style="display:inline;"
                                  onsubmit="return confirm('Eintrag löschen?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">✕</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div><!-- /.dashboard-layout -->

<script>
document.querySelectorAll('.ticker-item-actions form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const btn = form.querySelector('button');
        if (btn) btn.disabled = true;
    });
});
</script>
</body>
</html>
