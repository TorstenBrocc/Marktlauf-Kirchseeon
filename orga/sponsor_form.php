<?php
/**
 * Sponsor anlegen / bearbeiten
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_status.php';
require_once __DIR__ . '/../src/rechnung.php';
require_once __DIR__ . '/../src/sponsor_rotation.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

// Aktuell hinterlegte Pakete (für die Rechnungs-Karte). Paketpreise sind immer netto.
$rechnungPakete = sponsoringPakete(getDbConnection());

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$sponsorId = (int) ($_GET['id'] ?? 0);
$isEdit = $sponsorId > 0;
$sponsor = null;
$aufgaben = [];
$orgaUsers = [];
$ansprechpartner = [];
$driveFolderId = '';
$driveImages = [];
$driveError = '';

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

    // Seit Migration 063 liegen Sponsor-Aufgaben in `aufgaben` (kontext_typ='sponsor') —
    // dieselbe Quelle, aus der die ToDo-Kachel liest. Zwei Tabellen hätten zwei Wahrheiten
    // ergeben, sobald hier eine Frist gesetzt wird.
    $aufgabenStmt = $pdo->prepare("
        SELECT a.*, u.name AS verantwortlich_name
        FROM aufgaben a
        LEFT JOIN users u ON u.id = a.verantwortlich_user_id
        WHERE a.kontext_typ = 'sponsor' AND a.kontext_id = :id
        ORDER BY (a.status = 'erledigt'), (a.faellig_am IS NULL), a.faellig_am ASC, a.created_at DESC
    ");
    $aufgabenStmt->execute(['id' => $sponsorId]);
    $aufgaben = $aufgabenStmt->fetchAll();
    $orgaUsers = orgaUserListe($pdo);

    try {
        $apStmt = $pdo->prepare('SELECT * FROM sponsor_ansprechpartner WHERE sponsor_id = :id ORDER BY id ASC');
        $apStmt->execute(['id' => $sponsorId]);
        $ansprechpartner = $apStmt->fetchAll();
    } catch (PDOException $e) {
        // Table may not exist yet
    }

    // Drive-Ordner-Logos für die Auswahl-UI (WP-3). Fehler dürfen die Maske nie brechen.
    $driveFolderId = (string) ($sponsor['drive_folder_id'] ?? '');
    if ($driveFolderId !== '' && driveConfigured()) {
        try {
            $driveImages = sponsorDriveFolderImages($driveFolderId);
        } catch (Throwable $e) {
            $driveError = 'Drive-Ordner konnte nicht gelesen werden.';
        }
    }
}

// Gruppen (Konzern-Zugehörigkeit) für Autocomplete + aktuellen Namen laden.
$gruppen = [];
$aktuelleGruppe = '';
try {
    $pdo ??= getDbConnection();
    $gStmt = $pdo->query('SELECT id, name FROM sponsor_gruppen ORDER BY name');
    $gruppen = $gStmt->fetchAll();
    if ($isEdit && !empty($sponsor['gruppe_id'])) {
        foreach ($gruppen as $g) {
            if ((int) $g['id'] === (int) $sponsor['gruppe_id']) {
                $aktuelleGruppe = $g['name'];
                break;
            }
        }
    }
} catch (PDOException $e) {
    // Tabelle evtl. noch nicht angelegt
}

// Branchen-Liste aus Einstellungen laden.
$branchen = [];
try {
    $pdo ??= getDbConnection();
    $bStmt = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'sponsor_branchen'");
    $bRow = $bStmt->fetchColumn();
    if ($bRow) {
        $branchen = json_decode($bRow, true) ?? [];
    }
} catch (PDOException $e) {
    // ignore
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
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .form-container {
            max-width: 800px;
        }
        .form-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-card);
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
        .multiselect {
            position: relative;
        }
        .multiselect-trigger {
            width: 100%;
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--white);
            font-size: 0.9375rem;
            color: var(--text);
            text-align: left;
            cursor: pointer;
            position: relative;
            min-height: 2.4rem;
            box-sizing: border-box;
            appearance: none;
        }
        .multiselect-trigger::after {
            content: '';
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-top-color: var(--text-light);
            margin-top: 3px;
        }
        .multiselect-trigger.open::after {
            border-top-color: transparent;
            border-bottom-color: var(--text-light);
            margin-top: -3px;
        }
        .multiselect-dropdown {
            display: none;
            position: absolute;
            z-index: 200;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            max-height: 260px;
            overflow-y: auto;
        }
        .multiselect-dropdown.open {
            display: block;
        }
        .multiselect-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .multiselect-option:hover {
            background: var(--bg);
        }
        .multiselect-option input[type="checkbox"] {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .multiselect-placeholder {
            color: var(--text-light);
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
            grid-template-columns: 100px minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 90px 40px;
            gap: 0.5rem;
            align-items: end;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .ap-anschreiben {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding-bottom: 0.6rem;
        }
        .ap-anschreiben input[type="checkbox"] {
            width: 18px;
            height: 18px;
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
                <form method="post" action="api/sponsor_crud.php" enctype="multipart/form-data">
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

                        <div class="form-group">
                            <label for="gruppe">Konzern/Gruppe (optional)</label>
                            <input type="text" id="gruppe" name="gruppe_name" list="gruppe-liste"
                                   placeholder="z. B. Ahorn Gruppe"
                                   value="<?= htmlspecialchars($aktuelleGruppe) ?>">
                            <datalist id="gruppe-liste">
                                <?php foreach ($gruppen as $g): ?>
                                    <option value="<?= htmlspecialchars($g['name']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="form-group">
                            <label>Branche <span style="font-weight:400;color:var(--text-light)">(Mehrfachauswahl)</span></label>
                            <?php
                            $selectedBranchen = [];
                            if (!empty($sponsor['branche'])) {
                                $dec = json_decode($sponsor['branche'], true);
                                $selectedBranchen = is_array($dec) ? $dec : [$sponsor['branche']];
                            }
                            ?>
                            <div class="multiselect" id="branche-multiselect">
                                <button type="button" class="multiselect-trigger" id="branche-trigger"
                                        onclick="toggleBranchenDropdown()" aria-haspopup="listbox" aria-expanded="false">
                                    <span id="branche-label" class="<?= empty($selectedBranchen) ? 'multiselect-placeholder' : '' ?>">
                                        <?= empty($selectedBranchen) ? 'Bitte wählen …' : htmlspecialchars(implode(', ', $selectedBranchen)) ?>
                                    </span>
                                </button>
                                <div class="multiselect-dropdown" id="branche-dropdown" role="listbox">
                                    <?php foreach ($branchen as $b): ?>
                                        <label class="multiselect-option">
                                            <input type="checkbox" name="branche[]" value="<?= htmlspecialchars($b) ?>"
                                                   onchange="updateBrancheLabel()"
                                                   <?= in_array($b, $selectedBranchen, true) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($b) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Ansprechpartner</h2>

                        <div class="form-group">
                            <label for="ansprache">Ansprache in Anschreiben</label>
                            <select id="ansprache" name="ansprache">
                                <option value="sie"<?= (($sponsor['ansprache'] ?? 'sie') !== 'du') ? ' selected' : '' ?>>Sie (Standard)</option>
                                <option value="du"<?= (($sponsor['ansprache'] ?? 'sie') === 'du') ? ' selected' : '' ?>>Du</option>
                            </select>
                            <p style="font-size:0.78rem;color:var(--text-light);margin:0.35rem 0 0;line-height:1.5">
                                Steuert die <strong>Anredezeile</strong> aller Anschreiben an diesen Sponsor:
                                „Sehr geehrte Frau Jost," bzw. bei Du „Hallo Anja," (Vorname des
                                Ansprechpartners; ohne Vornamen „Hallo zusammen,").
                                <strong>Der übrige Text bleibt in Sie-Form</strong> — er ist so geschrieben
                                („erhalten Sie", „Ihre Unterstützung"). Wo geduzt werden soll, braucht die
                                Vorlage eine eigene Du-Fassung; sag Bescheid, für welche.
                                Die <strong>Rechnung selbst siezt immer</strong> (Geschäftsdokument, geht oft
                                an die Buchhaltung).
                            </p>
                        </div>

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
                                <div class="ap-anschreiben">
                                    <input type="hidden" name="ap_im_anschreiben[]" value="1">
                                    <input type="checkbox" checked title="Ins Anschreiben aufnehmen"
                                           onchange="this.previousElementSibling.value = this.checked ? '1' : '0'">
                                    <label style="margin:0;">Anschreiben</label>
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
                                    <?php $imAnschreiben = (int) ($ap['im_anschreiben'] ?? 1) === 1; ?>
                                    <div class="ap-anschreiben">
                                        <input type="hidden" name="ap_im_anschreiben[]" value="<?= $imAnschreiben ? '1' : '0' ?>">
                                        <input type="checkbox" <?= $imAnschreiben ? 'checked' : '' ?> title="Ins Anschreiben aufnehmen"
                                               onchange="this.previousElementSibling.value = this.checked ? '1' : '0'">
                                        <label style="margin:0;">Anschreiben</label>
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
                                <label for="paket">Sponsoring-Typ</label>
                                <select id="paket" name="paket">
                                    <option value="gold" <?= ($sponsor['paket'] ?? '') === 'gold' ? 'selected' : '' ?>>Gold</option>
                                    <option value="silber" <?= ($sponsor['paket'] ?? '') === 'silber' ? 'selected' : '' ?>>Silber</option>
                                    <option value="bronze" <?= ($sponsor['paket'] ?? '') === 'bronze' ? 'selected' : '' ?>>Bronze</option>
                                    <option value="hauptsponsor" <?= ($sponsor['paket'] ?? '') === 'hauptsponsor' ? 'selected' : '' ?>>Hauptsponsor</option>
                                    <option value="sachsponsor" <?= ($sponsor['paket'] ?? '') === 'sachsponsor' ? 'selected' : '' ?>>Sachsponsor</option>
                                    <option value="" <?= ($sponsor['paket'] ?? '') === '' || ($sponsor['paket'] ?? null) === null ? 'selected' : '' ?>>– noch offen –</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="summe">Betrag (€)</label>
                                <input type="number" id="summe" name="summe" step="1" min="0"
                                       value="<?= $sponsor['summe'] ?? '' ?>">
                            </div>
                        </div>
                        <p style="font-size:0.85rem; color: var(--text-light); margin:0.2rem 0 0.5rem;">
                            Gold/Silber/Bronze setzen den <strong>Betrag</strong> automatisch aus dem Pakettarif
                            (überschreibbar). <strong>Hauptsponsor</strong>: Betrag individuell eintragen.
                            <strong>Sachsponsor</strong>: kein Geld, keine Rechnung — was mitgebracht wird, ins Feld „Notizen“.
                        </p>

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
                        <h2>Bedingungen-Bestätigung</h2>
                        <p style="font-size:0.8rem; color: var(--text-light); margin-top:-0.5rem; margin-bottom:1rem;">
                            Hat der Sponsor die Sponsoring-Bedingungen bestätigt? Eintragen, sobald die
                            Rückmeldung da ist — dann erscheint in der Übersicht der grüne Haken.
                        </p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bedingungen_bestaetigt_am">Bestätigt am</label>
                                <input type="date" id="bedingungen_bestaetigt_am" name="bedingungen_bestaetigt_am"
                                       value="<?= !empty($sponsor['bedingungen_bestaetigt_am']) ? date('Y-m-d', strtotime((string) $sponsor['bedingungen_bestaetigt_am'])) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label for="bedingungen_weg">Weg der Rückmeldung</label>
                                <select id="bedingungen_weg" name="bedingungen_weg">
                                    <option value="">– bitte wählen –</option>
                                    <?php $bedWegCur = $sponsor['bedingungen_weg'] ?? ''; ?>
                                    <?php foreach (SPONSOR_BEDINGUNGEN_WEG as $wKey => $wLabel): ?>
                                        <option value="<?= $wKey ?>" <?= $bedWegCur === $wKey ? 'selected' : '' ?>><?= htmlspecialchars($wLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-single">
                                <input type="checkbox" id="bedingungen_beleg" name="bedingungen_beleg" value="1" <?= !empty($sponsor['bedingungen_beleg']) ? 'checked' : '' ?>>
                                <label for="bedingungen_beleg">Rückmeldung im Sponsor-Ordner abgelegt</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Rechnung</h2>
                        <p style="font-size:0.8rem; color: var(--text-light); margin-top:-0.5rem; margin-bottom:1rem;">
                            Abweichende Rechnungsanschrift nur ausfüllen, falls die Rechnung an eine andere
                            Adresse als die Firma gehen soll (z. B. zentrale Buchhaltung einer Unternehmensgruppe).
                            Leistung und Betrag kommen aus dem gebuchten Paket; Paketpreise sind netto (zzgl. 19&nbsp;% USt).
                        </p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="rechnung_firma">Firma / z. Hd.</label>
                                <input type="text" id="rechnung_firma" name="rechnung_firma"
                                       value="<?= htmlspecialchars($sponsor['rechnung_firma'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="rechnung_email">Rechnungs-E-Mail</label>
                                <input type="email" id="rechnung_email" name="rechnung_email"
                                       placeholder="z. B. buchhaltung@firma.de"
                                       value="<?= htmlspecialchars($sponsor['rechnung_email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rechnung_strasse">Straße + Hausnummer</label>
                            <input type="text" id="rechnung_strasse" name="rechnung_strasse"
                                   value="<?= htmlspecialchars($sponsor['rechnung_strasse'] ?? '') ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="rechnung_plz">PLZ</label>
                                <input type="text" id="rechnung_plz" name="rechnung_plz" maxlength="10"
                                       value="<?= htmlspecialchars($sponsor['rechnung_plz'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="rechnung_ort">Ort</label>
                                <input type="text" id="rechnung_ort" name="rechnung_ort" maxlength="120"
                                       value="<?= htmlspecialchars($sponsor['rechnung_ort'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-single">
                                <input type="checkbox" id="rechnung_betrag_brutto" name="rechnung_betrag_brutto" value="1"
                                       <?= ($sponsor['rechnung_betrag_brutto'] ?? 0) ? 'checked' : '' ?>>
                                <label for="rechnung_betrag_brutto">Diesen Sponsor brutto abrechnen (übersteuert die globale Einstellung)</label>
                            </div>
                            <p style="font-size:0.85rem; color: var(--text-light); margin:0.2rem 0 0;">
                                Ist der Haken gesetzt, wird der Paketbetrag für diesen Sponsor als <strong>Brutto</strong>
                                abgerechnet (USt wird herausgerechnet). Ohne Haken wird <strong>netto</strong> abgerechnet
                                (Paketpreis zzgl. USt) — das ist der Normalfall.
                            </p>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Recherche-Kontext</h2>
                        <p style="font-size:0.8rem; color: var(--text-light); margin-top:-0.5rem; margin-bottom:1rem;">
                            Infos aus der Recherche-Phase: Förderprogramm, Kontaktweg und Quellenbelege.
                            Werden beim CSV-Import automatisch befüllt.
                        </p>

                        <div class="form-group">
                            <label for="foerderprogramm">Förderprogramm / Sponsoring-Angebot</label>
                            <textarea id="foerderprogramm" name="foerderprogramm" rows="3"><?= htmlspecialchars($sponsor['foerderprogramm'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="kontaktweg">Antrag / Kontaktweg</label>
                            <textarea id="kontaktweg" name="kontaktweg" rows="2"><?= htmlspecialchars($sponsor['kontaktweg'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="quellenurl">Quellenlink (Recherche-Beleg)</label>
                            <input type="url" id="quellenurl" name="quellenurl" maxlength="500"
                                   placeholder="https://…"
                                   value="<?= htmlspecialchars($sponsor['quellenurl'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Öffentliche Darstellung (Website-Rotation)</h2>
                        <p style="font-size:0.8rem; color: var(--text-light); margin-top:-0.5rem; margin-bottom:1rem;">
                            Steuert das Sponsoren-Laufband auf der Startseite. Der Sponsor erscheint nur mit
                            gesetztem Haken <em>und</em> Logo. Verlinkt wird auf die hier eingetragene <strong>Website</strong>.
                        </p>

                        <div class="form-group">
                            <div class="checkbox-single">
                                <input type="checkbox" id="in_rotation" name="in_rotation" value="1"
                                       <?= ($sponsor['in_rotation'] ?? 0) ? 'checked' : '' ?>>
                                <label for="in_rotation">In der Website-Rotation anzeigen</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="website">Website (Ziel der Verlinkung)</label>
                            <input type="text" id="website" name="website" maxlength="255"
                                   placeholder="z. B. beispiel.de"
                                   value="<?= htmlspecialchars($sponsor['website'] ?? '') ?>">
                        </div>

                        <?php if ($isEdit && driveConfigured()): ?>
                            <div class="form-group">
                                <label>Logo aus dem Drive-Ordner wählen</label>
                                <?php if ($driveFolderId === ''): ?>
                                    <p style="font-size:0.85rem; color: var(--text-light); margin:0.2rem 0 0;">
                                        Sobald der Status auf „zugesagt" steht und gespeichert wird, legt das System
                                        automatisch einen Sponsor-Ordner im Drive an. Danach erscheint hier die Auswahl.
                                    </p>
                                <?php elseif ($driveError !== ''): ?>
                                    <p style="font-size:0.85rem; color:#c0392b;"><?= htmlspecialchars($driveError) ?></p>
                                <?php else: ?>
                                    <p style="font-size:0.8rem; margin:0.2rem 0 0.5rem;">
                                        <a href="https://drive.google.com/drive/folders/<?= htmlspecialchars($driveFolderId) ?>" target="_blank" rel="noopener noreferrer">Sponsor-Ordner im Drive öffnen ↗</a>
                                    </p>
                                    <?php if (empty($driveImages)): ?>
                                        <p style="font-size:0.85rem; color: var(--text-light);">
                                            Noch keine Bilddateien im Ordner. Lege Logos in den Drive-Ordner oder lade unten direkt hoch.
                                        </p>
                                    <?php else: ?>
                                        <p style="font-size:0.8rem; color: var(--text-light); margin:0.2rem 0 0.5rem;">
                                            Datei aus dem Sponsor-Ordner wählen — wird beim Speichern web-optimiert übernommen.
                                        </p>
                                        <label style="display:block; margin-bottom:0.3rem;">
                                            <input type="radio" name="logo_drive_pick" value="" <?= empty($sponsor['logo_drive_file_id']) ? 'checked' : '' ?>> — keine Änderung —
                                        </label>
                                        <?php foreach ($driveImages as $img): ?>
                                            <label style="display:block; margin-bottom:0.3rem;">
                                                <input type="radio" name="logo_drive_pick" value="<?= htmlspecialchars($img['id']) ?>"
                                                    <?= ($sponsor['logo_drive_file_id'] ?? '') === $img['id'] ? 'checked' : '' ?>>
                                                <?= htmlspecialchars($img['name']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="logo"><?= $isEdit && driveConfigured() && $driveFolderId !== '' ? 'Logo direkt hochladen (Alternative zur Drive-Auswahl)' : 'Logo fürs Laufband' ?></label>
                            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                            <p style="font-size:0.8rem; color: var(--text-light); margin-top:0.4rem;">
                                Am besten <strong>freigestellt (ohne Hintergrund)</strong> als PNG, liegend (~2:1),
                                mind. ca. 460×230&nbsp;px. SVG geht auch. Wird automatisch web-optimiert.
                            </p>
                            <?php if (!empty($sponsor['logo_web_asset'])): ?>
                                <div style="margin-top:0.7rem;">
                                    <div style="font-size:0.8rem; color: var(--text-light); margin-bottom:0.3rem;">Aktuelles Logo:</div>
                                    <img src="../assets/sponsoren-live/<?= htmlspecialchars($sponsor['logo_web_asset']) ?>"
                                         alt="Aktuelles Logo"
                                         style="max-height:60px; max-width:220px; background:#f4f4f4; padding:6px; border-radius:6px;">
                                </div>
                            <?php endif; ?>
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

                <?php if ($isEdit && driveConfigured()): ?>
                <div class="form-card">
                    <h2>Bestätigungs-Beleg</h2>
                    <p style="font-size:0.85rem; color: var(--text-light); margin-top:-0.5rem; margin-bottom:1rem;">
                        Legt die „Bestätigung Sponsoring" als PDF im Sponsor-Drive-Ordner ab (aus der Vorlage erzeugt).
                        Läuft automatisch beim Versand — hier manuell wiederholbar, falls die Ablage fehlschlug.
                        <strong>Es wird keine Mail versendet.</strong>
                    </p>
                    <form method="post" action="api/sponsor_crud.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="archive_bestaetigung">
                        <input type="hidden" name="sponsor_id" value="<?= $sponsorId ?>">
                        <button type="submit" class="btn btn-secondary">Bestätigungs-Beleg im Drive ablegen</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($isEdit): ?>
                <div class="form-card">
                    <h2>Aufgaben</h2>

                    <?php if (empty($aufgaben)): ?>
                        <p style="color: var(--text-light);">Keine Aufgaben vorhanden.</p>
                    <?php else: ?>
                        <ul class="aufgaben-list">
                            <?php foreach ($aufgaben as $a): ?>
                                <?php $erledigt = ($a['status'] === 'erledigt'); ?>
                                <li>
                                    <form method="post" action="api/aufgabe_orga_crud.php" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="status" value="<?= $erledigt ? 'offen' : 'erledigt' ?>">
                                        <input type="hidden" name="aufgabe_id" value="<?= (int) $a['id'] ?>">
                                        <input type="hidden" name="zurueck" value="sponsor">
                                        <input type="hidden" name="kontext_id" value="<?= $sponsorId ?>">
                                        <button type="submit" class="btn-mini <?= $erledigt ? 'btn-mini-success' : '' ?>" title="<?= $erledigt ? 'Als offen markieren' : 'Als erledigt markieren' ?>">
                                            <?= $erledigt ? '✓' : '○' ?>
                                        </button>
                                    </form>
                                    <span class="aufgabe-text <?= $erledigt ? 'aufgabe-erledigt' : '' ?>">
                                        <?= htmlspecialchars($a['titel']) ?>
                                        <?php if (!empty($a['faellig_am']) || !empty($a['verantwortlich_name'])): ?>
                                            <small style="color:var(--text-light)">
                                                <?php if (!empty($a['faellig_am'])): ?>
                                                    · bis <?= htmlspecialchars(date('d.m.Y', strtotime((string) $a['faellig_am']))) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($a['verantwortlich_name'])): ?>
                                                    · <?= htmlspecialchars($a['verantwortlich_name']) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                    <div class="aufgabe-actions">
                                        <form method="post" action="api/aufgabe_orga_crud.php" style="display:inline" onsubmit="return confirm('Aufgabe löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="aufgabe_id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="zurueck" value="sponsor">
                                            <input type="hidden" name="kontext_id" value="<?= $sponsorId ?>">
                                            <button type="submit" class="btn-mini btn-mini-danger" title="Löschen">×</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="api/aufgabe_orga_crud.php" class="add-aufgabe-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="kontext_typ" value="sponsor">
                        <input type="hidden" name="kontext_id" value="<?= $sponsorId ?>">
                        <input type="hidden" name="zurueck" value="sponsor">
                        <input type="text" name="titel" placeholder="Neue Aufgabe..." required>
                        <?php /* Frist und Verantwortlicher sind freiwillig (TT 2026-08-13) — wer
                                nur schnell etwas notieren will, tippt weiterhin nur den Titel. */ ?>
                        <input type="date" name="faellig_am" title="Frist (optional)">
                        <select name="verantwortlich_user_id" title="Verantwortlich (optional)">
                            <option value="">– wer? –</option>
                            <?php foreach ($orgaUsers as $ou): ?>
                                <option value="<?= (int) $ou['id'] ?>"><?= htmlspecialchars($ou['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                <div class="ap-anschreiben">
                    <input type="hidden" name="ap_im_anschreiben[]" value="1">
                    <input type="checkbox" checked title="Ins Anschreiben aufnehmen"
                           onchange="this.previousElementSibling.value = this.checked ? '1' : '0'">
                    <label style="margin:0;">Anschreiben</label>
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

    function toggleBranchenDropdown() {
        var trigger = document.getElementById('branche-trigger');
        var dropdown = document.getElementById('branche-dropdown');
        var open = dropdown.classList.toggle('open');
        trigger.classList.toggle('open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function updateBrancheLabel() {
        var checked = document.querySelectorAll('#branche-dropdown input[type="checkbox"]:checked');
        var label = document.getElementById('branche-label');
        if (checked.length === 0) {
            label.textContent = 'Bitte wählen …';
            label.className = 'multiselect-placeholder';
        } else {
            label.textContent = Array.from(checked).map(function(c) { return c.value; }).join(', ');
            label.className = '';
        }
    }

    document.addEventListener('click', function(e) {
        var ms = document.getElementById('branche-multiselect');
        if (ms && !ms.contains(e.target)) {
            document.getElementById('branche-dropdown').classList.remove('open');
            document.getElementById('branche-trigger').classList.remove('open');
            document.getElementById('branche-trigger').setAttribute('aria-expanded', 'false');
        }
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

    // Betrag folgt dem Sponsoring-Typ: Gold/Silber/Bronze aus dem Pakettarif vorbefüllen,
    // Hauptsponsor (individuell) / Sachsponsor (kein Geld) / offen -> Betrag leeren.
    (function() {
        const TYP_BETRAG = <?php
            $typBetrag = [];
            foreach (['gold', 'silber', 'bronze'] as $pk) {
                $b = paketBetrag($rechnungPakete[$pk]['investition'] ?? null);
                if ($b !== null && $b > 0) { $typBetrag[$pk] = (int) $b; }
            }
            echo json_encode($typBetrag, JSON_UNESCAPED_UNICODE);
        ?>;
        const sel = document.getElementById('paket');
        const betrag = document.getElementById('summe');
        if (!sel || !betrag) return;
        sel.addEventListener('change', function() {
            if (Object.prototype.hasOwnProperty.call(TYP_BETRAG, sel.value)) {
                betrag.value = TYP_BETRAG[sel.value];
            } else {
                betrag.value = '';
            }
        });
    })();
    </script>
</body>
</html>
