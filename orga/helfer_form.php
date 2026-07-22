<?php
/**
 * Helfer bearbeiten (Admin + Orga)
 * Stammdaten-Korrektur: Vorname, Nachname, E-Mail, Telefon, Status, Notiz.
 * Slots/Beiträge stammen aus der Anmeldung und werden read-only angezeigt.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$helferId = (int) ($_GET['id'] ?? 0);
if ($helferId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Helfer-ID.';
    header('Location: helfer.php');
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('
    SELECT h.*,
           GROUP_CONCAT(DISTINCT CONCAT(DATE_FORMAT(hs.tag, "%a %d.%m."), " ", COALESCE(CONCAT(hs.aufgabe, " – "), ""), hs.zeitfenster) ORDER BY hs.tag SEPARATOR ", ") AS slots,
           GROUP_CONCAT(DISTINCT CONCAT(hb.typ, COALESCE(CONCAT(": ", hb.freitext), "")) SEPARATOR ", ") AS beitraege
    FROM helfer h
    LEFT JOIN helfer_slots hs ON h.id = hs.helfer_id
    LEFT JOIN helfer_beitrag hb ON h.id = hb.helfer_id
    WHERE h.id = :id
    GROUP BY h.id
');
$stmt->execute(['id' => $helferId]);
$helfer = $stmt->fetch();

if (!$helfer) {
    $_SESSION['flash_error'] = 'Helfer nicht gefunden.';
    header('Location: helfer.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Helfer bearbeiten | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .form-container {
            max-width: 560px;
        }
        .form-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .form-card h2 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1 1 200px;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .readonly-info {
            font-size: 0.8rem;
            color: var(--text-light);
            line-height: 1.6;
        }
        .readonly-info strong {
            color: var(--text);
        }
    </style>
</head>
<body>
<?php $activeNav = 'helfer'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Helfer bearbeiten</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" action="api/helfer_update.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="helfer_id" value="<?= (int) $helfer['id'] ?>">

                    <div class="form-card">
                        <h2>Stammdaten</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="vorname" class="required">Vorname</label>
                                <input type="text" id="vorname" name="vorname" required maxlength="100"
                                       value="<?= htmlspecialchars((string) $helfer['vorname']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="nachname" class="required">Nachname</label>
                                <input type="text" id="nachname" name="nachname" required maxlength="100"
                                       value="<?= htmlspecialchars((string) $helfer['nachname']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">E-Mail</label>
                            <input type="email" id="email" name="email" required maxlength="255"
                                   value="<?= htmlspecialchars((string) $helfer['email']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" id="phone" name="phone" maxlength="30"
                                   value="<?= htmlspecialchars((string) $helfer['phone']) ?>"
                                   placeholder="+49 172 ...">
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="neu" <?= $helfer['status'] === 'neu' ? 'selected' : '' ?>>Neu</option>
                                <option value="bestaetigt" <?= $helfer['status'] === 'bestaetigt' ? 'selected' : '' ?>>Bestätigt</option>
                                <option value="abgelehnt" <?= $helfer['status'] === 'abgelehnt' ? 'selected' : '' ?>>Abgelehnt</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="notiz">Notiz</label>
                            <textarea id="notiz" name="notiz" rows="3" placeholder="Interne Notiz …"><?= htmlspecialchars((string) ($helfer['notiz'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Anmeldung (read-only)</h2>
                        <?php
                        $consentLabel = ($helfer['consent_photo'] ?? 'no') === 'yes' ? 'Ja' : 'Nein';
                        $consentTs = !empty($helfer['consent_ts']) ? date('d.m.Y H:i', strtotime((string) $helfer['consent_ts'])) : '–';
                        ?>
                        <p class="readonly-info">
                            <strong>Angemeldet am:</strong> <?= date('d.m.Y H:i', strtotime((string) $helfer['created_at'])) ?><br>
                            <strong>Aufgaben:</strong> <?= htmlspecialchars((string) ($helfer['slots'] ?? '')) ?: '–' ?><br>
                            <strong>Beiträge:</strong> <?= htmlspecialchars((string) ($helfer['beitraege'] ?? '')) ?: '–' ?>
                        </p>
                        <p class="readonly-info" style="margin-top:0.75rem;">
                            <strong>Fotoeinwilligung:</strong> <?= $consentLabel ?> (erteilt am <?= $consentTs ?>)<br>
                            <strong>Anmeldung für:</strong> <?= ((int) ($helfer['is_minor'] ?? 0) === 1) ? 'minderjährige Person' : 'volljährige Person (selbst)' ?><br>
                            <?php if ((int) ($helfer['is_minor'] ?? 0) === 1): ?>
                            <strong>Erziehungsberechtigte Person:</strong> <?= htmlspecialchars((string) ($helfer['guardian_name'] ?? '')) ?: '–' ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                        <a href="helfer.php" class="btn btn-secondary">Zurück zur Übersicht</a>
                    </div>
                </form>
            </div>
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
