<?php
/**
 * Sponsor anlegen / bearbeiten
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

$sponsorId = (int) ($_GET['id'] ?? 0);
$isEdit = $sponsorId > 0;
$sponsor = null;
$aufgaben = [];

if ($isEdit) {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $stmt->execute(['id' => $sponsorId]);
    $sponsor = $stmt->fetch();

    if (!$sponsor) {
        $_SESSION['flash_error'] = 'Sponsor nicht gefunden.';
        header('Location: sponsoren.php');
        exit;
    }

    $aufgabenStmt = $pdo->prepare('SELECT * FROM sponsor_aufgaben WHERE sponsor_id = :id ORDER BY erledigt ASC, created_at DESC');
    $aufgabenStmt->execute(['id' => $sponsorId]);
    $aufgaben = $aufgabenStmt->fetchAll();
}

$pageTitle = $isEdit ? 'Sponsor bearbeiten' : 'Neuer Sponsor';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .form-container {
            max-width: 800px;
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .checkbox-single {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-single input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .aufgaben-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .aufgaben-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        .aufgaben-list li:last-child {
            border-bottom: none;
        }
        .aufgabe-text {
            flex: 1;
        }
        .aufgabe-erledigt {
            text-decoration: line-through;
            color: var(--text-light);
        }
        .aufgabe-actions {
            display: flex;
            gap: 0.25rem;
        }
        .btn-mini {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            background: var(--border);
            color: var(--text);
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .btn-mini:hover {
            background: #ccc;
        }
        .btn-mini-success {
            background: var(--success-bg);
            color: var(--success);
        }
        .btn-mini-danger {
            background: var(--error-bg);
            color: var(--error);
        }
        .add-aufgabe-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        .add-aufgabe-form input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        .kein-kontakt-notice {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .admin-only {
            font-size: 0.75rem;
            color: var(--text-light);
            font-style: italic;
        }
        .delete-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--error-bg);
        }
        .btn-danger {
            background: var(--error);
            color: white;
        }
        .btn-danger:hover {
            background: #b71c1c;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Marktlauf Orga</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item active">
                    <a href="sponsoren.php">Sponsoren</a>
                </li>
                <li class="nav-item">
                    <a href="dateien.php">Dateien</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="ticker.php">Live-Ticker</a>
                    <span class="badge">Phase 3</span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item">
                    <a href="users.php">Benutzerverwaltung</a>
                    <span class="badge">Phase 2</span>
                </li>
                <li class="nav-item">
                    <a href="settings.php">Einstellungen</a>
                    <span class="badge">Phase 2</span>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1><?= $pageTitle ?></h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if ($isEdit && $sponsor['kein_kontakt']): ?>
                <div class="kein-kontakt-notice">
                    <strong>Kein Kontakt</strong> – Dieser Sponsor ist als "Kein Kontakt" markiert.
                    <?php if ($isAdmin): ?>
                        <form method="post" action="api/sponsor_crud.php" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="kein_kontakt_remove">
                            <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                            <button type="submit" class="btn btn-small btn-secondary">Aufheben</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" action="api/sponsor_crud.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                    <?php endif; ?>

                    <div class="form-card">
                        <h2>Stammdaten</h2>

                        <div class="form-group">
                            <label for="firma" class="required">Firma</label>
                            <input type="text" id="firma" name="firma" required
                                   value="<?= htmlspecialchars($sponsor['firma'] ?? '') ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ansprechpartner">Ansprechpartner</label>
                                <input type="text" id="ansprechpartner" name="ansprechpartner"
                                       value="<?= htmlspecialchars($sponsor['ansprechpartner'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">E-Mail</label>
                                <input type="email" id="email" name="email"
                                       value="<?= htmlspecialchars($sponsor['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Sponsoring</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="paket">Paket</label>
                                <select id="paket" name="paket">
                                    <option value="">– Kein Paket –</option>
                                    <option value="bronze" <?= ($sponsor['paket'] ?? '') === 'bronze' ? 'selected' : '' ?>>Bronze</option>
                                    <option value="silber" <?= ($sponsor['paket'] ?? '') === 'silber' ? 'selected' : '' ?>>Silber</option>
                                    <option value="gold" <?= ($sponsor['paket'] ?? '') === 'gold' ? 'selected' : '' ?>>Gold</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="summe">Summe (€)</label>
                                <input type="number" id="summe" name="summe" step="0.01" min="0"
                                       value="<?= $sponsor['summe'] ?? '' ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="angefragt" <?= ($sponsor['status'] ?? 'angefragt') === 'angefragt' ? 'selected' : '' ?>>Angefragt</option>
                                    <option value="zugesagt" <?= ($sponsor['status'] ?? '') === 'zugesagt' ? 'selected' : '' ?>>Zugesagt</option>
                                    <option value="abgelehnt" <?= ($sponsor['status'] ?? '') === 'abgelehnt' ? 'selected' : '' ?>>Abgelehnt</option>
                                    <option value="bezahlt" <?= ($sponsor['status'] ?? '') === 'bezahlt' ? 'selected' : '' ?>>Bezahlt</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="wiedervorlage">Wiedervorlage</label>
                                <input type="date" id="wiedervorlage" name="wiedervorlage"
                                       value="<?= $sponsor['wiedervorlage'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Sonstiges</h2>

                        <div class="form-group">
                            <label for="notizen">Notizen</label>
                            <textarea id="notizen" name="notizen" rows="4"><?= htmlspecialchars($sponsor['notizen'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-single">
                                <input type="checkbox" id="kein_kontakt" name="kein_kontakt" value="1"
                                       <?= ($sponsor['kein_kontakt'] ?? 0) ? 'checked' : '' ?>
                                       <?= ($sponsor['kein_kontakt'] ?? 0) && !$isAdmin ? 'disabled' : '' ?>>
                                <label for="kein_kontakt">Kein Kontakt</label>
                                <?php if (($sponsor['kein_kontakt'] ?? 0) && !$isAdmin): ?>
                                    <span class="admin-only">(Nur Admin kann dies zurücknehmen)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
                        <a href="sponsoren.php" class="btn btn-secondary">Abbrechen</a>
                    </div>
                </form>

                <?php if ($isEdit): ?>
                <div class="form-card">
                    <h2>Aufgaben</h2>

                    <?php if (empty($aufgaben)): ?>
                        <p style="color: var(--text-light);">Keine Aufgaben vorhanden.</p>
                    <?php else: ?>
                        <ul class="aufgaben-list">
                            <?php foreach ($aufgaben as $a): ?>
                                <li>
                                    <form method="post" action="api/aufgabe_crud.php" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="toggle_erledigt">
                                        <input type="hidden" name="aufgabe_id" value="<?= $a['id'] ?>">
                                        <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                                        <button type="submit" class="btn-mini <?= $a['erledigt'] ? 'btn-mini-success' : '' ?>" title="<?= $a['erledigt'] ? 'Als offen markieren' : 'Als erledigt markieren' ?>">
                                            <?= $a['erledigt'] ? '✓' : '○' ?>
                                        </button>
                                    </form>
                                    <span class="aufgabe-text <?= $a['erledigt'] ? 'aufgabe-erledigt' : '' ?>">
                                        <?= htmlspecialchars($a['titel']) ?>
                                    </span>
                                    <div class="aufgabe-actions">
                                        <form method="post" action="api/aufgabe_crud.php" style="display:inline" onsubmit="return confirm('Aufgabe löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="aufgabe_id" value="<?= $a['id'] ?>">
                                            <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                                            <button type="submit" class="btn-mini btn-mini-danger" title="Löschen">×</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="api/aufgabe_crud.php" class="add-aufgabe-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                        <input type="text" name="titel" placeholder="Neue Aufgabe..." required>
                        <button type="submit" class="btn btn-small btn-primary">Hinzufügen</button>
                    </form>
                </div>

                <?php if ($isAdmin): ?>
                <div class="delete-section">
                    <form method="post" action="api/sponsor_crud.php" onsubmit="return confirm('Sponsor wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                        <button type="submit" class="btn btn-danger">Sponsor löschen</button>
                    </form>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
