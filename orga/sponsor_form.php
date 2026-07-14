<?php
/**
 * Sponsor anlegen / bearbeiten
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_status.php';

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
$ansprechpartner = [];

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

    try {
        $apStmt = $pdo->prepare('SELECT * FROM sponsor_ansprechpartner WHERE sponsor_id = :id ORDER BY id ASC');
        $apStmt->execute(['id' => $sponsorId]);
        $ansprechpartner = $apStmt->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet
    }
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
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
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
            margin-bottom: 1.5rem;
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
        .ap-row {
            display: grid;
            grid-template-columns: 100px minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 40px;
            gap: 0.5rem;
            align-items: end;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .ap-row:last-of-type {
            border-bottom: none;
        }
        .ap-row > div {
            min-width: 0;
        }
        .ap-row input, .ap-row select {
            width: 100%;
            box-sizing: border-box;
            min-width: 0;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .ap-row label {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
            display: block;
        }
        .ap-remove {
            background: var(--error-bg);
            color: var(--error);
            border: none;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .ap-remove:hover {
            background: var(--error);
            color: white;
        }
        .ap-remove:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .btn-add-ap {
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            background: var(--bg);
            border: 1px dashed var(--border);
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-light);
        }
        .btn-add-ap:hover {
            background: var(--border);
            color: var(--text);
        }
        .kein-kontakt-details {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #fff8f8;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        .kein-kontakt-details.visible {
            display: block;
        }
        @media (max-width: 900px) {
            .ap-row {
                grid-template-columns: 1fr 1fr;
            }
            .ap-row > div:nth-child(6) {
                grid-column: 1 / -1;
            }
            .ap-row > button {
                grid-column: 2;
                justify-self: end;
            }
        }
    </style>
</head>
<body>
<?php $activeNav = 'sponsoren'; require __DIR__ . '/_sidebar.php'; ?>

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
                    <strong>Kein Kontakt mehr erwünscht</strong>
                    <?php if ($sponsor['kein_kontakt_datum']): ?>
                        – <?= date('d.m.Y', strtotime($sponsor['kein_kontakt_datum'])) ?>
                    <?php endif; ?>
                    <?php if ($sponsor['kein_kontakt_wer']): ?>
                        (<?= htmlspecialchars($sponsor['kein_kontakt_wer']) ?>)
                    <?php endif; ?>
                    <?php if ($sponsor['kein_kontakt_grund']): ?>
                        <br><small><?= nl2br(htmlspecialchars($sponsor['kein_kontakt_grund'])) ?></small>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                        <form method="post" action="api/sponsor_crud.php" style="display:inline; margin-left:1rem;">
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
                    </div>

                    <div class="form-card">
                        <h2>Ansprechpartner</h2>

                        <div id="ap-container">
                            <?php if (empty($ansprechpartner)): ?>
                            <div class="ap-row" data-ap-row>
                                <div>
                                    <label>Anrede</label>
                                    <select name="ap_anrede[]">
                                        <option value="">–</option>
                                        <option value="Herr">Herr</option>
                                        <option value="Frau">Frau</option>
                                        <option value="Divers">Divers</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Vorname</label>
                                    <input type="text" name="ap_vorname[]">
                                </div>
                                <div>
                                    <label>Nachname</label>
                                    <input type="text" name="ap_nachname[]">
                                </div>
                                <div>
                                    <label>Funktion</label>
                                    <input type="text" name="ap_funktion[]">
                                </div>
                                <div>
                                    <label>Telefon</label>
                                    <input type="tel" name="ap_telefon[]">
                                </div>
                                <div>
                                    <label>E-Mail</label>
                                    <input type="email" name="ap_email[]">
                                </div>
                                <button type="button" class="ap-remove" onclick="removeApRow(this)" disabled title="Löschen">×</button>
                            </div>
                            <?php else: ?>
                                <?php foreach ($ansprechpartner as $i => $ap): ?>
                                <div class="ap-row" data-ap-row>
                                    <div>
                                        <label>Anrede</label>
                                        <select name="ap_anrede[]">
                                            <option value="">–</option>
                                            <option value="Herr" <?= $ap['anrede'] === 'Herr' ? 'selected' : '' ?>>Herr</option>
                                            <option value="Frau" <?= $ap['anrede'] === 'Frau' ? 'selected' : '' ?>>Frau</option>
                                            <option value="Divers" <?= $ap['anrede'] === 'Divers' ? 'selected' : '' ?>>Divers</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Vorname</label>
                                        <input type="text" name="ap_vorname[]" value="<?= htmlspecialchars($ap['vorname']) ?>">
                                    </div>
                                    <div>
                                        <label>Nachname</label>
                                        <input type="text" name="ap_nachname[]" value="<?= htmlspecialchars($ap['nachname']) ?>">
                                    </div>
                                    <div>
                                        <label>Funktion</label>
                                        <input type="text" name="ap_funktion[]" value="<?= htmlspecialchars($ap['funktion']) ?>">
                                    </div>
                                    <div>
                                        <label>Telefon</label>
                                        <input type="tel" name="ap_telefon[]" value="<?= htmlspecialchars($ap['telefon'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label>E-Mail</label>
                                        <input type="email" name="ap_email[]" value="<?= htmlspecialchars($ap['email']) ?>">
                                    </div>
                                    <button type="button" class="ap-remove" onclick="removeApRow(this)" <?= count($ansprechpartner) <= 1 ? 'disabled' : '' ?> title="Löschen">×</button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-add-ap" onclick="addApRow()">+ Weiteren Ansprechpartner hinzufügen</button>
                    </div>

                    <div class="form-card">
                        <h2>Sponsoring</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="paket">Paket</label>
                                <select id="paket" name="paket">
                                    <option value="hauptsponsor" <?= ($sponsor['paket'] ?? '') === 'hauptsponsor' ? 'selected' : '' ?>>Hauptsponsor</option>
                                    <option value="gold" <?= ($sponsor['paket'] ?? '') === 'gold' ? 'selected' : '' ?>>Gold</option>
                                    <option value="silber" <?= ($sponsor['paket'] ?? '') === 'silber' ? 'selected' : '' ?>>Silber</option>
                                    <option value="bronze" <?= ($sponsor['paket'] ?? '') === 'bronze' ? 'selected' : '' ?>>Bronze</option>
                                    <option value="" <?= ($sponsor['paket'] ?? '') === '' || ($sponsor['paket'] ?? null) === null ? 'selected' : '' ?>>– Kein Paket –</option>
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
                                <label for="prioritaet">Priorität</label>
                                <select id="prioritaet" name="prioritaet">
                                    <option value="" <?= ($sponsor['prioritaet'] ?? null) === null ? 'selected' : '' ?>>– Keine –</option>
                                    <option value="1" <?= (string) ($sponsor['prioritaet'] ?? '') === '1' ? 'selected' : '' ?>>Hoch</option>
                                    <option value="2" <?= (string) ($sponsor['prioritaet'] ?? '') === '2' ? 'selected' : '' ?>>Mittel</option>
                                    <option value="3" <?= (string) ($sponsor['prioritaet'] ?? '') === '3' ? 'selected' : '' ?>>Niedrig</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ort">Ort</label>
                                <input type="text" id="ort" name="ort" maxlength="120"
                                       value="<?= htmlspecialchars($sponsor['ort'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <?php $currentStatus = $sponsor['status'] ?? 'neu'; ?>
                                    <?php foreach (SPONSOR_STATUS as $key => $meta): ?>
                                        <option value="<?= $key ?>" <?= $currentStatus === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="wiedervorlage">Wiedervorlage</label>
                                <input type="date" id="wiedervorlage" name="wiedervorlage"
                                       value="<?= $sponsor['wiedervorlage'] ?? '' ?>">
                            </div>
                        </div>

                        <?php if ($isEdit && !empty($sponsor['gesendet_am'])): ?>
                        <p style="font-size:0.8rem; color: var(--text-light); margin-top:0.5rem;">
                            Zuletzt angeschrieben:
                            <?= date('d.m.Y H:i', strtotime($sponsor['gesendet_am'])) ?>
                            <?php if (!empty($sponsor['anschreiben_typ'])): ?>
                                (<?= $sponsor['anschreiben_typ'] === 'folgejahr' ? 'Folgejahr' : 'Erstanschreiben' ?>)
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
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
                                       <?= ($sponsor['kein_kontakt'] ?? 0) && !$isAdmin ? 'disabled' : '' ?>
                                       onchange="toggleKeinKontaktDetails()">
                                <label for="kein_kontakt">Kein Kontakt mehr erwünscht</label>
                                <?php if (($sponsor['kein_kontakt'] ?? 0) && !$isAdmin): ?>
                                    <span class="admin-only">(Nur Admin kann dies zurücknehmen)</span>
                                <?php endif; ?>
                            </div>
                            <div id="kein-kontakt-details" class="kein-kontakt-details <?= ($sponsor['kein_kontakt'] ?? 0) ? 'visible' : '' ?>">
                                <div class="form-group">
                                    <label for="kein_kontakt_grund">Grund</label>
                                    <textarea id="kein_kontakt_grund" name="kein_kontakt_grund" rows="2"><?= htmlspecialchars($sponsor['kein_kontakt_grund'] ?? '') ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="kein_kontakt_wer">Festgestellt von</label>
                                        <input type="text" id="kein_kontakt_wer" name="kein_kontakt_wer"
                                               value="<?= htmlspecialchars($sponsor['kein_kontakt_wer'] ?? $user['name']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="kein_kontakt_datum">Datum</label>
                                        <input type="date" id="kein_kontakt_datum" name="kein_kontakt_datum"
                                               value="<?= $sponsor['kein_kontakt_datum'] ?? date('Y-m-d') ?>">
                                    </div>
                                </div>
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

    <script>
    function addApRow() {
        var container = document.getElementById('ap-container');
        var template = `
            <div class="ap-row" data-ap-row>
                <div>
                    <label>Anrede</label>
                    <select name="ap_anrede[]">
                        <option value="">–</option>
                        <option value="Herr">Herr</option>
                        <option value="Frau">Frau</option>
                        <option value="Divers">Divers</option>
                    </select>
                </div>
                <div>
                    <label>Vorname</label>
                    <input type="text" name="ap_vorname[]">
                </div>
                <div>
                    <label>Nachname</label>
                    <input type="text" name="ap_nachname[]">
                </div>
                <div>
                    <label>Funktion</label>
                    <input type="text" name="ap_funktion[]">
                </div>
                <div>
                    <label>Telefon</label>
                    <input type="tel" name="ap_telefon[]">
                </div>
                <div>
                    <label>E-Mail</label>
                    <input type="email" name="ap_email[]">
                </div>
                <button type="button" class="ap-remove" onclick="removeApRow(this)" title="Löschen">×</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', template);
        updateRemoveButtons();
    }

    function removeApRow(btn) {
        var row = btn.closest('[data-ap-row]');
        row.remove();
        updateRemoveButtons();
    }

    function updateRemoveButtons() {
        var rows = document.querySelectorAll('[data-ap-row]');
        rows.forEach(function(row) {
            var btn = row.querySelector('.ap-remove');
            btn.disabled = rows.length <= 1;
        });
    }

    function toggleKeinKontaktDetails() {
        var checkbox = document.getElementById('kein_kontakt');
        var details = document.getElementById('kein-kontakt-details');
        if (checkbox.checked) {
            details.classList.add('visible');
        } else {
            details.classList.remove('visible');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateRemoveButtons();
    });

    // Mobile sidebar toggle
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
