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
require_once __DIR__ . '/../src/sponsor_leitfaden.php';

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
        /* Ansprechpartner: ruhige Textzeilen, Doppelklick zum Bearbeiten, Autosave in die DB */
        /* Jeder Ansprechpartner als eigene Karte-in-Karte (grau auf weißer form-card) */
        .ap-item {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0.5rem 1rem;
            padding: 0.8rem 0.9rem;
            margin-bottom: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg);
        }
        .ap-item:last-of-type {
            margin-bottom: 0;
        }
        /* Signatur: eine Zeile pro Feld */
        .ap-item-main {
            flex: 1 1 320px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }
        .ap-line {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.35rem;
            min-width: 0;
        }
        .ap-line-name { font-weight: 600; }
        /* Vor-/Nachname −10 % (1.35em → 1.215em); Anrede so groß wie Funktion (1.1em) */
        .ap-line-name .ap-edit[data-field="vorname"],
        .ap-line-name .ap-edit[data-field="nachname"] {
            font-size: 1.215em;
        }
        .ap-line-anrede .ap-edit,
        .ap-line-funktion .ap-edit {
            font-size: 1.1em;
        }
        .ap-line-funktion { font-weight: 600; }
        .ap-line-anrede { color: var(--text-light); }
        /* Telefon + E-Mail: Aktions-Icon (klickbar) + editierbarer Wert */
        .ap-contact-line { font-size: 1.1em; color: var(--text-light); }
        .ap-action {
            flex: 0 0 auto;
            text-decoration: none;
            font-size: 0.95em;
            line-height: 1;
        }
        .ap-action-hidden { display: none; }
        /* Editable value: click-to-edit affordance, wraps instead of clipping */
        .ap-edit {
            border-radius: 4px;
            padding: 0.05rem 0.25rem;
            cursor: text;
            max-width: 100%;
            overflow-wrap: anywhere;
        }
        .ap-edit:hover {
            background: var(--bg);
            box-shadow: inset 0 0 0 1px var(--border);
        }
        .ap-edit:focus {
            outline: 2px solid var(--primary);
            outline-offset: 1px;
        }
        .ap-edit.ap-empty {
            color: var(--text-light);
            font-style: italic;
            font-weight: 400;
        }
        .ap-inline-input {
            font: inherit;
            padding: 0.15rem 0.3rem;
            border: 1px solid var(--primary);
            border-radius: 4px;
            box-sizing: border-box;
            max-width: 100%;
        }
        .ap-anschreiben-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: var(--text-light);
            cursor: pointer;
            white-space: nowrap;
        }
        .ap-anschreiben-toggle input[type="checkbox"] {
            width: 17px;
            height: 17px;
        }
        .ap-status {
            font-size: 0.75rem;
            color: var(--text-light);
            min-width: 5.5rem;
            text-align: right;
        }
        .ap-status.ok { color: var(--primary); }
        .ap-status.err { color: var(--error); }
        .ap-remove {
            background: var(--error-bg);
            color: var(--error);
            border: none;
            border-radius: 4px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 1rem;
            flex: 0 0 auto;
        }
        .ap-remove:hover {
            background: var(--error);
            color: white;
        }
        .ap-hint {
            color: var(--text-light);
            font-size: 0.9rem;
            padding: 0.5rem 0;
        }
        .btn-add-ap {
            margin-top: 0.75rem;
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
                <form id="sponsor-form" method="post" action="api/sponsor_crud.php" enctype="multipart/form-data">
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

                        <?php
                        /**
                         * Render one inline-editable value span for an Ansprechpartner.
                         * The raw value lives in data-value; the autosave endpoint reads it.
                         */
                        function apEditSpan(string $field, string $value, string $placeholder, string $extraClass = ''): string {
                            $empty = ($value === '');
                            $cls = 'ap-edit' . ($extraClass !== '' ? ' ' . $extraClass : '') . ($empty ? ' ap-empty' : '');
                            $display = $empty ? $placeholder : $value;
                            return '<span class="' . $cls . '" data-field="' . htmlspecialchars($field) . '"'
                                . ' data-value="' . htmlspecialchars($value) . '"'
                                . ' data-placeholder="' . htmlspecialchars($placeholder) . '"'
                                . ' tabindex="0" title="Doppelklick zum Bearbeiten">' . htmlspecialchars($display) . '</span>';
                        }

                        /**
                         * Klickbares Aktions-Icon (Anrufen / E-Mail) vor dem editierbaren Wert.
                         * Leerer Wert -> Icon ausgeblendet (nichts zu wählen). Eigenes Element,
                         * damit die Doppelklick-Bearbeitung des Werts nicht kollidiert.
                         */
                        function apActionIcon(string $kind, string $value): string {
                            $icon = $kind === 'tel' ? '📞' : '✉';
                            $title = $kind === 'tel' ? 'Anrufen' : 'E-Mail schreiben';
                            $href = '';
                            if ($value !== '') {
                                $href = $kind === 'tel'
                                    ? 'tel:' . preg_replace('/[^\d+]/', '', $value)
                                    : 'mailto:' . $value;
                            }
                            return '<a class="ap-action' . ($value === '' ? ' ap-action-hidden' : '') . '"'
                                . ' data-kind="' . $kind . '" title="' . $title . '"'
                                . ' href="' . htmlspecialchars($href) . '"'
                                . ' target="_blank" rel="noopener noreferrer">' . $icon . '</a>';
                        }
                        ?>
                        <?php if (!$isEdit): ?>
                        <p class="ap-hint">
                            Ansprechpartner kannst du hinzufügen, sobald der Sponsor angelegt ist —
                            <strong>bitte erst oben „Speichern"</strong>. Danach landest du direkt wieder
                            in dieser Maske und pflegst die Kontakte per Doppelklick (jede Änderung wird
                            sofort gespeichert).
                        </p>
                        <?php else: ?>
                        <div id="ap-container"
                             data-sponsor-id="<?= (int) $sponsorId ?>"
                             data-csrf="<?= htmlspecialchars($csrfToken) ?>">
                            <?php foreach ($ansprechpartner as $ap): ?>
                                <?php $imAnschreiben = (int) ($ap['im_anschreiben'] ?? 1) === 1; ?>
                                <div class="ap-item" data-ap-id="<?= (int) $ap['id'] ?>">
                                    <div class="ap-item-main">
                                        <div class="ap-line ap-line-anrede">
                                            <?= apEditSpan('anrede', (string) ($ap['anrede'] ?? ''), 'Anrede', 'ap-anrede') ?>
                                        </div>
                                        <div class="ap-line ap-line-name">
                                            <?= apEditSpan('vorname', (string) ($ap['vorname'] ?? ''), 'Vorname') ?>
                                            <?= apEditSpan('nachname', (string) ($ap['nachname'] ?? ''), 'Nachname') ?>
                                        </div>
                                        <div class="ap-line ap-line-funktion">
                                            <?= apEditSpan('funktion', (string) ($ap['funktion'] ?? ''), 'Funktion') ?>
                                        </div>
                                        <div class="ap-line ap-contact-line">
                                            <?= apActionIcon('tel', (string) ($ap['telefon'] ?? '')) ?>
                                            <?= apEditSpan('telefon', (string) ($ap['telefon'] ?? ''), 'Telefon') ?>
                                        </div>
                                        <div class="ap-line ap-contact-line">
                                            <?= apActionIcon('mail', (string) ($ap['email'] ?? '')) ?>
                                            <?= apEditSpan('email', (string) ($ap['email'] ?? ''), 'E-Mail') ?>
                                        </div>
                                    </div>
                                    <label class="ap-anschreiben-toggle" title="Ins Anschreiben aufnehmen">
                                        <input type="checkbox" data-field="im_anschreiben" <?= $imAnschreiben ? 'checked' : '' ?>>
                                        <span>Anschreiben</span>
                                    </label>
                                    <span class="ap-status" aria-live="polite"></span>
                                    <button type="button" class="ap-remove" title="Ansprechpartner löschen">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-add-ap" onclick="addApRow()">+ Ansprechpartner hinzufügen</button>
                        <?php endif; ?>
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
                            <label for="quellenurl">Fördermaske</label>
                            <input type="url" id="quellenurl" name="quellenurl" maxlength="500"
                                   placeholder="https://…"
                                   value="<?= htmlspecialchars($sponsor['quellenurl'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="weitere_links">Weitere Links</label>
                            <?php
                            $wl = trim((string) ($sponsor['weitere_links'] ?? ''));
                            if ($wl !== ''):
                            ?>
                                <ul style="margin:0.2rem 0 0.5rem; padding-left:1.1rem; font-size:0.9rem;">
                                <?php foreach (preg_split('/\r\n|\r|\n/', $wl) ?: [] as $zeile):
                                    $zeile = trim($zeile);
                                    if ($zeile === '') { continue; }
                                    if (strpos($zeile, '|') !== false) {
                                        [$lab, $u] = array_map('trim', explode('|', $zeile, 2));
                                    } else {
                                        $lab = $u = $zeile;
                                    }
                                    $href = preg_match('#^https?://#i', $u) ? $u : 'https://' . $u;
                                ?>
                                    <li><a href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($lab) ?> ↗</a></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <textarea id="weitere_links" name="weitere_links" rows="3"
                                      placeholder="Eine URL pro Zeile — optional: Beschriftung | https://…"><?= htmlspecialchars($sponsor['weitere_links'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Leitfaden / ausgefüllte Anfrage</label>
                            <?php if ($isEdit && !empty($sponsor['leitfaden_datei'])): ?>
                                <p style="margin:0.2rem 0 0.4rem;">
                                    📄 <a href="api/leitfaden_download.php?id=<?= (int) $sponsorId ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(sponsorLeitfadenDisplayName((string) $sponsor['leitfaden_datei'])) ?> ↓</a>
                                </p>
                                <details style="font-size:0.85rem; color: var(--text-light);">
                                    <summary style="cursor:pointer;">ersetzen</summary>
                                    <input type="file" name="leitfaden" style="margin-top:0.4rem;"
                                           accept=".pdf,.doc,.docx,.odt,.rtf,.md,.txt">
                                </details>
                            <?php else: ?>
                                <input type="file" name="leitfaden"
                                       accept=".pdf,.doc,.docx,.odt,.rtf,.md,.txt">
                                <p style="font-size:0.8rem; color: var(--text-light); margin-top:0.4rem;">
                                    Optional: die ausgefüllte Anfrage/Ausfüllhilfe hochladen (PDF, DOC(X), ODT, RTF, MD, TXT; max. 10&nbsp;MB).
                                    Sichtbar nur hier im Orga-Bereich.<?= $isEdit ? '' : ' Wird nach dem Anlegen gespeichert.' ?>
                                </p>
                            <?php endif; ?>
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
                        <?php if ($isEdit): ?>
                        <span style="font-size:0.8rem; color: var(--text-light);">Änderungen werden automatisch gespeichert — der Button ist nur noch Sicherheitsnetz.</span>
                        <?php endif; ?>
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
    // ---- Ansprechpartner: Inline-Edit mit Autosave -------------------------
    (function () {
        var container = document.getElementById('ap-container');
        if (!container) return; // Anlegen-Modus: kein Container, nichts zu tun

        var SPONSOR_ID = container.dataset.sponsorId;
        var CSRF = container.dataset.csrf;
        var TEXT_FIELDS = ['vorname', 'nachname', 'funktion', 'telefon', 'email'];
        var ANREDE_OPTIONS = ['', 'Herr', 'Frau', 'Divers'];

        function setStatus(item, text, kind) {
            var el = item.querySelector('.ap-status');
            if (!el) return;
            el.textContent = text || '';
            el.className = 'ap-status' + (kind ? ' ' + kind : '');
        }

        function valueOf(item, field) {
            var el = item.querySelector('.ap-edit[data-field="' + field + '"]');
            return el ? (el.dataset.value || '') : '';
        }

        function rowIsEmpty(item) {
            var vals = item.querySelectorAll('.ap-edit');
            for (var i = 0; i < vals.length; i++) {
                if ((vals[i].dataset.value || '').trim() !== '') return false;
            }
            return true;
        }

        function saveRow(item) {
            // Neue, noch komplett leere Zeile nicht anlegen
            if ((item.dataset.apId || '0') === '0' && rowIsEmpty(item)) return;

            // Pro Zeile nur ein Request gleichzeitig — sonst könnte eine neue Zeile
            // (id noch 0) doppelt eingefügt werden. Trailing-Save nach Abschluss.
            if (item._apSaving) { item._apDirty = true; return; }
            item._apSaving = true;
            item._apDirty = false;

            var body = new URLSearchParams();
            body.set('action', 'save');
            body.set('csrf_token', CSRF);
            body.set('sponsor_id', SPONSOR_ID);
            body.set('ap_id', item.dataset.apId || '0');
            body.set('anrede', valueOf(item, 'anrede'));
            TEXT_FIELDS.forEach(function (f) { body.set(f, valueOf(item, f)); });
            var chk = item.querySelector('input[data-field="im_anschreiben"]');
            body.set('im_anschreiben', chk && chk.checked ? '1' : '0');

            setStatus(item, 'speichert…');
            fetch('api/ansprechpartner_save.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) {
                    if (d.id) item.dataset.apId = d.id;
                    setStatus(item, 'gespeichert', 'ok');
                } else {
                    setStatus(item, (d && d.message) || 'Fehler', 'err');
                }
            }).catch(function () {
                setStatus(item, 'Netzwerkfehler', 'err');
            }).finally(function () {
                item._apSaving = false;
                // Während des Requests kam eine weitere Änderung: jetzt nachziehen
                // (id ist inzwischen gesetzt → wird ein Update, kein zweiter Insert).
                if (item._apDirty) saveRow(item);
            });
        }

        function applyValue(span, val) {
            span.dataset.value = val;
            span.textContent = val !== '' ? val : (span.dataset.placeholder || '');
            span.classList.toggle('ap-empty', val === '');
            updateActionIcon(span);
        }

        // Klickbares Aktions-Icon (tel:/mailto:) an den aktuellen Wert anpassen.
        // Leerer Wert -> Icon ausgeblendet.
        function updateActionIcon(span) {
            var field = span.dataset.field;
            if (field !== 'telefon' && field !== 'email') return;
            var line = span.closest('.ap-contact-line');
            var a = line && line.querySelector('.ap-action');
            if (!a) return;
            var val = span.dataset.value || '';
            if (val === '') {
                a.classList.add('ap-action-hidden');
                a.removeAttribute('href');
            } else {
                a.classList.remove('ap-action-hidden');
                a.setAttribute('href', field === 'telefon'
                    ? 'tel:' + val.replace(/[^\d+]/g, '')
                    : 'mailto:' + val);
            }
        }

        function startEdit(span) {
            if (span.querySelector('input, select')) return; // schon im Edit
            var field = span.dataset.field;
            var current = span.dataset.value || '';
            var editor;
            if (field === 'anrede') {
                editor = document.createElement('select');
                ANREDE_OPTIONS.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o;
                    opt.textContent = (o === '' ? '–' : o);
                    if (o === current) opt.selected = true;
                    editor.appendChild(opt);
                });
            } else {
                editor = document.createElement('input');
                editor.type = (field === 'email' ? 'email' : (field === 'telefon' ? 'tel' : 'text'));
                editor.value = current;
            }
            editor.className = 'ap-inline-input';
            span.textContent = '';
            span.classList.remove('ap-empty');
            span.appendChild(editor);
            editor.focus();
            if (editor.select) editor.select();

            var closed = false;
            function commit() {
                if (closed) return;
                closed = true;
                var val = (editor.value || '').trim();
                applyValue(span, val);
                if (val !== current) saveRow(span.closest('.ap-item'));
            }
            function cancel() {
                if (closed) return;
                closed = true;
                applyValue(span, current);
            }
            editor.addEventListener('blur', commit);
            editor.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); editor.blur(); }
                else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
            });
            if (field === 'anrede') {
                editor.addEventListener('change', function () { editor.blur(); });
            }
        }

        // Bearbeiten per Doppelklick, Enter/F2 (Tastatur)
        container.addEventListener('dblclick', function (e) {
            var span = e.target.closest('.ap-edit');
            if (span && container.contains(span)) startEdit(span);
        });
        container.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== 'F2') return;
            var span = e.target.closest('.ap-edit');
            if (span && !span.querySelector('input, select')) { e.preventDefault(); startEdit(span); }
        });
        // Anschreiben-Häkchen speichert sofort
        container.addEventListener('change', function (e) {
            if (e.target.matches('input[data-field="im_anschreiben"]')) {
                saveRow(e.target.closest('.ap-item'));
            }
        });
        // Löschen (mit Bestätigung)
        container.addEventListener('click', function (e) {
            var btn = e.target.closest('.ap-remove');
            if (!btn) return;
            var item = btn.closest('.ap-item');
            if (!confirm('Diesen Ansprechpartner löschen?')) return;
            var apId = item.dataset.apId || '0';
            if (apId === '0') { item.remove(); return; } // war nie gespeichert
            var body = new URLSearchParams();
            body.set('action', 'delete');
            body.set('csrf_token', CSRF);
            body.set('sponsor_id', SPONSOR_ID);
            body.set('ap_id', apId);
            fetch('api/ansprechpartner_save.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) { item.remove(); }
                else { setStatus(item, (d && d.message) || 'Löschen fehlgeschlagen', 'err'); }
            }).catch(function () { setStatus(item, 'Netzwerkfehler', 'err'); });
        });

        function span(field, placeholder, extra) {
            var cls = 'ap-edit' + (extra ? ' ' + extra : '') + ' ap-empty';
            return '<span class="' + cls + '" data-field="' + field + '" data-value="" data-placeholder="' +
                placeholder + '" tabindex="0" title="Doppelklick zum Bearbeiten">' + placeholder + '</span>';
        }

        // Kontaktzeile: ausgeblendetes Aktions-Icon (füllt sich beim ersten gespeicherten Wert) + editierbarer Wert.
        function contactLine(field, placeholder, kind) {
            var icon = kind === 'tel' ? '📞' : '✉';
            var title = kind === 'tel' ? 'Anrufen' : 'E-Mail schreiben';
            return '<div class="ap-line ap-contact-line">' +
                '<a class="ap-action ap-action-hidden" data-kind="' + kind + '" title="' + title + '"' +
                ' target="_blank" rel="noopener noreferrer">' + icon + '</a>' +
                span(field, placeholder) +
                '</div>';
        }

        // Neue Zeile: leeres ap-item einfügen (Autosave beim ersten gefüllten Feld)
        window.addApRow = function () {
            var item = document.createElement('div');
            item.className = 'ap-item';
            item.dataset.apId = '0';
            item.innerHTML =
                '<div class="ap-item-main">' +
                    '<div class="ap-line ap-line-anrede">' + span('anrede', 'Anrede', 'ap-anrede') + '</div>' +
                    '<div class="ap-line ap-line-name">' +
                        span('vorname', 'Vorname') +
                        span('nachname', 'Nachname') +
                    '</div>' +
                    '<div class="ap-line ap-line-funktion">' + span('funktion', 'Funktion') + '</div>' +
                    contactLine('telefon', 'Telefon', 'tel') +
                    contactLine('email', 'E-Mail', 'mail') +
                '</div>' +
                '<label class="ap-anschreiben-toggle" title="Ins Anschreiben aufnehmen">' +
                    '<input type="checkbox" data-field="im_anschreiben" checked><span>Anschreiben</span>' +
                '</label>' +
                '<span class="ap-status" aria-live="polite"></span>' +
                '<button type="button" class="ap-remove" title="Ansprechpartner löschen">×</button>';
            container.appendChild(item);
            var first = item.querySelector('.ap-edit[data-field="vorname"]');
            if (first) startEdit(first);
        };
    })();

    // ---- Einzelmaske: feldweiser Autosave -------------------------------------
    // Jedes Feld speichert sich beim Ändern selbst (change feuert bei Text/Zahl/Textarea
    // erst beim Verlassen). Nur im Bearbeiten-Modus aktiv; die Neuanlage läuft weiter
    // über den „Anlegen"-Button. Datei-Uploads, Logo-Drive-Auswahl, kein-Kontakt-Block
    // und Löschen behalten ihren eigenen Weg.
    (function () {
        var form = document.getElementById('sponsor-form');
        if (!form) return;
        var idField = form.querySelector('input[name="sponsor_id"]');
        if (!idField) return; // Anlegen-Modus: noch keine Sponsor-id, kein Autosave
        var SPONSOR_ID = idField.value;
        var CSRF = (form.querySelector('input[name="csrf_token"]') || {}).value || '';

        // Erlaubte Felder (Spiegel der Backend-Whitelist in api/sponsor_crud.php).
        var FIELDS = {
            firma: 1, gruppe_name: 1, ansprache: 1, paket: 1, summe: 1, prioritaet: 1,
            ort: 1, status: 1, wiedervorlage: 1, bedingungen_bestaetigt_am: 1,
            bedingungen_weg: 1, bedingungen_beleg: 1, rechnung_firma: 1, rechnung_email: 1,
            rechnung_strasse: 1, rechnung_plz: 1, rechnung_ort: 1, rechnung_betrag_brutto: 1,
            foerderprogramm: 1, kontaktweg: 1, quellenurl: 1, weitere_links: 1,
            website: 1, notizen: 1
        };

        var timers = {};
        function statusEl(control) {
            // Statusanzeige pro Feld: einmalig neben dem Label anlegen, danach wiederverwenden.
            var group = control.closest('.form-group') || control.parentNode;
            var el = group.querySelector(':scope > .field-status') || group.querySelector('.field-status');
            if (!el) {
                el = document.createElement('span');
                el.className = 'field-status';
                el.setAttribute('aria-live', 'polite');
                el.style.marginLeft = '0.5rem';
                el.style.fontSize = '0.78rem';
                var lbl = group.querySelector('label');
                if (lbl) { lbl.appendChild(el); } else { group.appendChild(el); }
            }
            return el;
        }
        function setStatus(control, key, text, color, fade) {
            var el = statusEl(control);
            el.textContent = text;
            el.style.color = color;
            if (timers[key]) { clearTimeout(timers[key]); delete timers[key]; }
            if (fade) {
                timers[key] = setTimeout(function () { el.textContent = ''; }, 2000);
            }
        }

        function save(control, field, params) {
            var body = new URLSearchParams();
            body.set('action', 'field_update');
            body.set('csrf_token', CSRF);
            body.set('sponsor_id', SPONSOR_ID);
            body.set('field', field);
            Object.keys(params).forEach(function (k) {
                if (Array.isArray(params[k])) {
                    params[k].forEach(function (v) { body.append(k, v); });
                } else {
                    body.set(k, params[k]);
                }
            });
            setStatus(control, field, 'speichert…', 'var(--text-light)', false);
            fetch('api/sponsor_crud.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (d && d.ok) {
                    setStatus(control, field, 'gespeichert', '#007230', true);
                } else {
                    setStatus(control, field, (d && d.message) || 'Fehler', '#c0392b', false);
                }
            }).catch(function () {
                setStatus(control, field, 'Netzwerkfehler', '#c0392b', false);
            });
        }

        form.addEventListener('change', function (e) {
            var el = e.target;
            var name = el.name;
            if (!name) return; // Ansprechpartner-Felder tragen keinen name -> ignoriert

            // Branche: Mehrfachauswahl -> alle angehakten Werte gemeinsam speichern.
            if (name === 'branche[]') {
                var checked = form.querySelectorAll('input[name="branche[]"]:checked');
                var vals = Array.prototype.map.call(checked, function (c) { return c.value; });
                var trigger = document.getElementById('branche-trigger') || el;
                save(trigger, 'branche', { 'value[]': vals });
                return;
            }

            if (!FIELDS[name]) return;

            var value = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : el.value;
            save(el, name, { value: value });
        });
    })();

    function toggleKeinKontaktDetails() {
        var checkbox = document.getElementById('kein_kontakt');
        var details = document.getElementById('kein-kontakt-details');
        if (checkbox.checked) {
            details.classList.add('visible');
        } else {
            details.classList.remove('visible');
        }
    }

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
            // Programmatisch gesetzter Betrag feuert kein change -> Autosave selbst anstoßen,
            // damit der Betrag zum neuen Typ passt (Feld bleibt die Quelle der Wahrheit).
            betrag.dispatchEvent(new Event('change', { bubbles: true }));
        });
    })();
    </script>
</body>
</html>
