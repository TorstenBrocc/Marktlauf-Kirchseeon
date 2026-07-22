<?php
/**
 * Einsatzplan / Schichten (Admin + Orga)
 * Schichten (Aufgaben mit Zeit/Ort) anlegen und Helfer zuteilen.
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

$pdo = getDbConnection();

// Alle Schichten
$schichten = $pdo->query('
    SELECT * FROM schichten
    ORDER BY (tag IS NULL), tag, (von IS NULL), von, titel
')->fetchAll();

// Zuteilungen (Helfer je Schicht), gruppiert in PHP
$zuteilungen = [];
$zStmt = $pdo->query('
    SELECT sz.schicht_id, h.id AS helfer_id, h.vorname, h.nachname
    FROM schicht_zuteilung sz
    JOIN helfer h ON h.id = sz.helfer_id
    ORDER BY h.nachname, h.vorname
');
foreach ($zStmt as $row) {
    $zuteilungen[(int) $row['schicht_id']][] = $row;
}

// Selbstmeldungen aus der Anmeldung je Schicht (helfer_slots.schicht_id).
// Das ist die "Gemeldet"-Liste: Helfer, die sich fuer diese Schicht eingetragen
// haben und per Klick verbindlich zugeteilt werden koennen.
$gemeldet = [];
$gStmt = $pdo->query('
    SELECT hs.schicht_id, h.id AS helfer_id, h.vorname, h.nachname, h.status
    FROM helfer_slots hs
    JOIN helfer h ON h.id = hs.helfer_id
    WHERE hs.schicht_id IS NOT NULL
    ORDER BY h.nachname, h.vorname
');
foreach ($gStmt as $row) {
    $gemeldet[(int) $row['schicht_id']][] = $row;
}

// Beitraege je Helfer (Kuchen / Sonstiges) fuer das "i"-Tooltip im Einsatzplan
// (umgekehrter Inhalt zur "Kuchen & Sonstiges"-Uebersicht).
$beitragProHelfer = [];
$bStmt = $pdo->query('
    SELECT helfer_id, typ, freitext
    FROM helfer_beitrag
    ORDER BY typ
');
foreach ($bStmt as $row) {
    $label = $row['typ'] === 'kuchen' ? 'Kuchen' : 'Sonstiges';
    if (!empty($row['freitext'])) {
        $label .= ': ' . $row['freitext'];
    }
    $beitragProHelfer[(int) $row['helfer_id']][] = $label;
}

// Bestätigte Helfer + Verfügbarkeit (für Zuteil-Dropdown)
$confirmedHelfer = $pdo->query('
    SELECT h.id, h.vorname, h.nachname,
           GROUP_CONCAT(CONCAT(DATE_FORMAT(hs.tag, "%a %d.%m."), " ", COALESCE(CONCAT(hs.aufgabe, " – "), ""), hs.zeitfenster)
               ORDER BY hs.tag SEPARATOR ", ") AS slots
    FROM helfer h
    LEFT JOIN helfer_slots hs ON h.id = hs.helfer_id
    WHERE h.status = "bestaetigt"
    GROUP BY h.id
    ORDER BY h.nachname, h.vorname
')->fetchAll();

function schichtZeit(array $s): string {
    $parts = [];
    if (!empty($s['tag'])) {
        $parts[] = date('D, d.m.Y', strtotime($s['tag']));
    }
    if (!empty($s['von'])) {
        $zeit = substr($s['von'], 0, 5);
        if (!empty($s['bis'])) {
            $zeit .= '–' . substr($s['bis'], 0, 5);
        }
        $parts[] = $zeit . ' Uhr';
    } elseif (!empty($s['zeitfenster'])) {
        $parts[] = $s['zeitfenster'];
    }
    return $parts ? implode(' · ', $parts) : 'Zeit offen';
}

/** Tooltip-Text der Beiträge eines Helfers ("" wenn keine). */
function beitragTooltip(array $beitragProHelfer, int $helferId): string {
    $list = $beitragProHelfer[$helferId] ?? [];
    return $list ? implode(' | ', $list) : '';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Einsatzplan | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .schicht-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .schicht-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .schicht-title { margin: 0 0 0.25rem; font-size: 1.05rem; }
        .schicht-meta { font-size: 0.8rem; color: var(--text-light); }
        .schicht-desc { font-size: 0.875rem; margin: 0.5rem 0 0; white-space: pre-wrap; }
        .besetzung {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .besetzung.voll { background: var(--success-bg); color: var(--success); }
        .besetzung.offen { background: #fff3cd; color: #856404; }
        .helfer-chips { list-style: none; padding: 0; margin: 0.75rem 0 0; display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .helfer-chip {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 999px; padding: 0.2rem 0.35rem 0.2rem 0.65rem;
            font-size: 0.8rem;
        }
        .helfer-chip button {
            border: none; background: transparent; cursor: pointer;
            color: var(--error); font-size: 1rem; line-height: 1; padding: 0 0.2rem;
        }
        .schicht-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
        .add-form { display: flex; gap: 0.4rem; align-items: flex-end; flex-wrap: wrap; }
        .add-form select { padding: 0.4rem; min-width: 220px; }
        .edit-form { margin-top: 0.75rem; display: none; }
        .edit-form.open { display: block; }
        .field-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .field-row .form-group { flex: 1; min-width: 140px; }
        .neu-card { border: 2px dashed var(--border); background: #fafafa; }
        .form-group label { display:block; font-size: 0.75rem; margin-bottom: 0.25rem; color: var(--text-light); }
        .form-group input, .form-group textarea {
            width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .anmeldung-toggle {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.85rem; margin: 0.75rem 0; cursor: pointer;
        }
        .anmeldung-toggle input { width: auto; }
        .tag-anmeldung { color: var(--success); font-weight: 600; }
        .tag-intern { color: var(--text-light); font-style: italic; }
        .gemeldet-block { margin-top: 0.75rem; }
        .gemeldet-label { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.04em; }
        .gemeldet-chip { background: #f5f8ff; border-color: #c9d8ff; }
        .chip-status { font-size: 0.7rem; color: #856404; }
        .chip-add {
            border: none; background: var(--primary); color: var(--white);
            border-radius: 999px; padding: 0.1rem 0.5rem; font-size: 0.72rem; cursor: pointer;
        }
        .chip-add:hover { background: var(--primary-dark); }
        .info-i {
            display: inline-flex; align-items: center; justify-content: center;
            width: 15px; height: 15px; border-radius: 50%;
            background: var(--text-light); color: var(--white);
            font-size: 0.65rem; font-style: italic; font-weight: 700;
            cursor: help; user-select: none;
        }
    </style>
</head>
<body>
<?php $activeNav = 'schichten'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Einsatzplan</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <!-- Neue Schicht anlegen -->
            <div class="schicht-card neu-card">
                <h2 style="margin-top:0;font-size:1rem;">Neue Schicht / Aufgabe</h2>
                <form method="post" action="api/schicht_crud.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="field-row">
                        <div class="form-group" style="flex:2;">
                            <label>Aufgabe / Titel *</label>
                            <input type="text" name="titel" required maxlength="255" placeholder="z.B. Getränkestand Ziel">
                        </div>
                        <div class="form-group">
                            <label>Ort</label>
                            <input type="text" name="ort" maxlength="255" placeholder="z.B. Zielbereich">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>Tag</label>
                            <input type="date" name="tag">
                        </div>
                        <div class="form-group">
                            <label>Von</label>
                            <input type="time" name="von">
                        </div>
                        <div class="form-group">
                            <label>Bis</label>
                            <input type="time" name="bis">
                        </div>
                        <div class="form-group">
                            <label>Bedarf (Anzahl)</label>
                            <input type="number" name="bedarf" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label>Zeitfenster (Freitext)</label>
                            <input type="text" name="zeitfenster" maxlength="80" placeholder="z.B. nach Absprache">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Beschreibung (für Helfer sichtbar)</label>
                        <textarea name="beschreibung" placeholder="Was ist zu tun? Details, Ansprechpartner …"></textarea>
                    </div>
                    <label class="anmeldung-toggle">
                        <input type="checkbox" name="in_anmeldung" value="1" checked>
                        In der Helfer-Anmeldung zum Eintragen anzeigen
                    </label>
                    <button type="submit" class="btn btn-primary">Schicht anlegen</button>
                </form>
            </div>

            <p class="stats" style="margin:1.5rem 0 1rem;color:var(--text-light);font-size:0.875rem;">
                <?= count($schichten) ?> Schicht(en) angelegt
            </p>

            <?php if (empty($schichten)): ?>
                <p>Noch keine Schichten angelegt.</p>
            <?php else: ?>
                <?php foreach ($schichten as $s): ?>
                    <?php
                    $sid = (int) $s['id'];
                    $zugeteilt = $zuteilungen[$sid] ?? [];
                    $anzahl = count($zugeteilt);
                    $bedarf = (int) $s['bedarf'];
                    $voll = $anzahl >= $bedarf;
                    $zugeteilteIds = array_map(static fn($z) => (int) $z['helfer_id'], $zugeteilt);
                    ?>
                    <div class="schicht-card" id="schicht-<?= $sid ?>">
                        <div class="schicht-head">
                            <div>
                                <h3 class="schicht-title"><?= htmlspecialchars($s['titel']) ?></h3>
                                <div class="schicht-meta">
                                    <?= htmlspecialchars(schichtZeit($s)) ?>
                                    <?php if (!empty($s['ort'])): ?> · <?= htmlspecialchars($s['ort']) ?><?php endif; ?>
                                    <?php if ((int) $s['in_anmeldung'] === 1): ?>
                                        · <span class="tag-anmeldung">in Anmeldung</span>
                                    <?php else: ?>
                                        · <span class="tag-intern">nur intern</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="besetzung <?= $voll ? 'voll' : 'offen' ?>">
                                besetzt <?= $anzahl ?> / Soll <?= $bedarf ?>
                            </span>
                        </div>

                        <?php if (!empty($s['beschreibung'])): ?>
                            <p class="schicht-desc"><?= htmlspecialchars($s['beschreibung']) ?></p>
                        <?php endif; ?>

                        <?php if ($zugeteilt): ?>
                            <ul class="helfer-chips">
                                <?php foreach ($zugeteilt as $z): ?>
                                    <?php $tt = beitragTooltip($beitragProHelfer, (int) $z['helfer_id']); ?>
                                    <li class="helfer-chip">
                                        <?= htmlspecialchars($z['vorname'] . ' ' . $z['nachname']) ?>
                                        <?php if ($tt !== ''): ?>
                                            <span class="info-i" tabindex="0" title="Bringt außerdem mit — <?= htmlspecialchars($tt) ?>">i</span>
                                        <?php endif; ?>
                                        <form method="post" action="api/schicht_zuteilung.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                            <input type="hidden" name="helfer_id" value="<?= (int) $z['helfer_id'] ?>">
                                            <button type="submit" title="Zuteilung entfernen">×</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php
                        // Gemeldet (Selbstauskunft aus der Anmeldung), die noch nicht zugeteilt sind.
                        $gemeldetOffen = array_filter(
                            $gemeldet[$sid] ?? [],
                            static fn($g) => !in_array((int) $g['helfer_id'], $zugeteilteIds, true)
                        );
                        ?>
                        <?php if ($gemeldetOffen): ?>
                            <div class="gemeldet-block">
                                <span class="gemeldet-label">Gemeldet (aus Anmeldung):</span>
                                <ul class="helfer-chips">
                                    <?php foreach ($gemeldetOffen as $g): ?>
                                        <?php $tt = beitragTooltip($beitragProHelfer, (int) $g['helfer_id']); ?>
                                        <li class="helfer-chip gemeldet-chip">
                                            <?= htmlspecialchars($g['vorname'] . ' ' . $g['nachname']) ?>
                                            <?php if ($g['status'] !== 'bestaetigt'): ?>
                                                <span class="chip-status" title="Helfer-Status: <?= htmlspecialchars($g['status']) ?>">(<?= htmlspecialchars($g['status']) ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($tt !== ''): ?>
                                                <span class="info-i" tabindex="0" title="Bringt außerdem mit — <?= htmlspecialchars($tt) ?>">i</span>
                                            <?php endif; ?>
                                            <form method="post" action="api/schicht_zuteilung.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                                <input type="hidden" name="helfer_id" value="<?= (int) $g['helfer_id'] ?>">
                                                <button type="submit" class="chip-add" title="Verbindlich zuteilen">+ zuteilen</button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="schicht-actions">
                            <form method="post" action="api/schicht_zuteilung.php" class="add-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                <div class="form-group" style="margin:0;">
                                    <label>Helfer zuteilen</label>
                                    <select name="helfer_id" required>
                                        <option value="">– bestätigten Helfer wählen –</option>
                                        <?php foreach ($confirmedHelfer as $h): ?>
                                            <?php if (in_array((int) $h['id'], $zugeteilteIds, true)) continue; ?>
                                            <option value="<?= (int) $h['id'] ?>">
                                                <?= htmlspecialchars($h['vorname'] . ' ' . $h['nachname']) ?><?= $h['slots'] ? ' — ' . htmlspecialchars($h['slots']) : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-small btn-primary">Zuteilen</button>
                            </form>
                            <button type="button" class="btn btn-small btn-secondary" onclick="document.getElementById('edit-<?= $sid ?>').classList.toggle('open')">Bearbeiten</button>
                        </div>

                        <!-- Bearbeiten -->
                        <div class="edit-form" id="edit-<?= $sid ?>">
                            <form method="post" action="api/schicht_crud.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                <div class="field-row">
                                    <div class="form-group" style="flex:2;">
                                        <label>Aufgabe / Titel *</label>
                                        <input type="text" name="titel" required maxlength="255" value="<?= htmlspecialchars($s['titel']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Ort</label>
                                        <input type="text" name="ort" maxlength="255" value="<?= htmlspecialchars($s['ort'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="field-row">
                                    <div class="form-group">
                                        <label>Tag</label>
                                        <input type="date" name="tag" value="<?= htmlspecialchars($s['tag'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Von</label>
                                        <input type="time" name="von" value="<?= htmlspecialchars($s['von'] ? substr($s['von'], 0, 5) : '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Bis</label>
                                        <input type="time" name="bis" value="<?= htmlspecialchars($s['bis'] ? substr($s['bis'], 0, 5) : '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Bedarf</label>
                                        <input type="number" name="bedarf" min="1" value="<?= (int) $s['bedarf'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Zeitfenster (Freitext)</label>
                                        <input type="text" name="zeitfenster" maxlength="80" value="<?= htmlspecialchars($s['zeitfenster'] ?? '') ?>" placeholder="z.B. nach Absprache">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Beschreibung</label>
                                    <textarea name="beschreibung"><?= htmlspecialchars($s['beschreibung'] ?? '') ?></textarea>
                                </div>
                                <label class="anmeldung-toggle">
                                    <input type="checkbox" name="in_anmeldung" value="1" <?= (int) $s['in_anmeldung'] === 1 ? 'checked' : '' ?>>
                                    In der Helfer-Anmeldung zum Eintragen anzeigen
                                </label>
                                <div style="display:flex;gap:0.5rem;">
                                    <button type="submit" class="btn btn-small btn-primary">Speichern</button>
                                </div>
                            </form>
                            <form method="post" action="api/schicht_crud.php" style="margin-top:0.5rem;" onsubmit="return confirm('Schicht wirklich löschen? Zuteilungen gehen verloren.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                <button type="submit" class="btn btn-small btn-danger">Schicht löschen</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
