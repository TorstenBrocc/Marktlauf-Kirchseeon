<?php
/**
 * Einsatzplan / Schichten (Admin + Orga)
 * Schichten (Aufgaben mit Zeit/Ort) anlegen und Helfer zuteilen.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helfer_aufgaben.php'; // helferTagLabel()

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
        /* Tagesüberschrift wie im Anmeldeformular (grüne Trennlinie). */
        .tag-heading {
            font-size: 1.1rem;
            margin: 1.75rem 0 0.9rem;
            padding-bottom: 0.4rem;
            border-bottom: 2px solid var(--primary);
        }
        .tag-heading:first-of-type { margin-top: 0.5rem; }
        /* Anlege-Formular eingeklappt. */
        .neu-details { margin-bottom: 0.5rem; }
        .neu-details > summary {
            cursor: pointer; font-weight: 600; color: var(--primary);
            padding: 0.6rem 0.9rem; background: var(--white);
            border: 1px dashed var(--border); border-radius: 8px; list-style: none;
        }
        .neu-details[open] > summary { margin-bottom: 1rem; }
        .neu-kachel { border: 2px dashed var(--border); background: #fafafa; }
        /* Schicht-Kachel (nutzt globales .kachel; hier nur Innenleben). */
        .schicht-kachel { display: flex; flex-direction: column; }
        .schicht-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .schicht-title { margin: 0; font-size: 1rem; line-height: 1.3; }
        .schicht-meta { font-size: 0.8rem; color: var(--text-light); margin-top: 0.3rem; }
        .schicht-desc { font-size: 0.85rem; margin: 0.5rem 0 0; white-space: pre-wrap; }
        .zugeteilt-label, .gemeldet-label {
            font-size: 0.7rem; color: var(--text-light);
            text-transform: uppercase; letter-spacing: 0.04em;
            margin-top: 0.75rem;
        }
        .besetzung {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
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
        .schicht-actions { display: flex; gap: 0.5rem; margin-top: auto; padding-top: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
        .add-form { display: flex; gap: 0.4rem; align-items: flex-end; flex-wrap: wrap; flex: 1; }
        .add-form .form-group { flex: 1; min-width: 0; }
        .add-form select { padding: 0.4rem; width: 100%; max-width: 100%; }
        .edit-form { margin-top: 0.75rem; display: none; }
        .edit-form.open { display: block; }
        .field-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .field-row .form-group { flex: 1; min-width: 140px; }
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
        .gemeldet-block { margin-top: 0.25rem; }
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

            <!-- Neue Schicht anlegen (eingeklappt, damit die Übersicht im Fokus bleibt) -->
            <details class="neu-details">
                <summary>+ Neue Schicht / Aufgabe anlegen</summary>
                <div class="kachel neu-kachel">
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
            </details>

            <p class="stats" style="margin:1.5rem 0 1rem;color:var(--text-light);font-size:0.875rem;">
                <?= count($schichten) ?> Schicht(en) angelegt
            </p>

            <?php if (empty($schichten)): ?>
                <p>Noch keine Schichten angelegt.</p>
            <?php else: ?>
                <?php
                $lastTag = '__init__';
                foreach ($schichten as $s):
                    $sid = (int) $s['id'];
                    $zugeteilt = $zuteilungen[$sid] ?? [];
                    $anzahl = count($zugeteilt);
                    $bedarf = (int) $s['bedarf'];
                    $voll = $anzahl >= $bedarf;
                    $zugeteilteIds = array_map(static fn($z) => (int) $z['helfer_id'], $zugeteilt);
                    $tag = (string) ($s['tag'] ?? '');
                    if ($tag !== $lastTag):
                        if ($lastTag !== '__init__') { echo "</div>\n"; } // vorheriges Raster schließen
                        $lastTag = $tag;
                        echo '<h2 class="tag-heading">' . htmlspecialchars($tag !== '' ? helferTagLabel($tag) : 'Ohne festen Termin') . '</h2>';
                        echo '<div class="kachel-grid">';
                    endif;
                    ?>
                    <div class="kachel schicht-kachel" id="schicht-<?= $sid ?>">
                        <div class="schicht-head">
                            <h3 class="schicht-title"><?= htmlspecialchars($s['titel']) ?></h3>
                            <span class="besetzung <?= $voll ? 'voll' : 'offen' ?>">
                                <?= $anzahl ?>/<?= $bedarf ?>
                            </span>
                        </div>
                        <div class="schicht-meta">
                            <?php $zf = helferSchichtZeitfenster($s); ?>
                            <?= htmlspecialchars($zf !== '' ? $zf : 'Zeit offen') ?>
                            <?php if (!empty($s['ort'])): ?> · <?= htmlspecialchars($s['ort']) ?><?php endif; ?>
                            <?php if ((int) $s['in_anmeldung'] === 1): ?>
                                · <span class="tag-anmeldung">in Anmeldung</span>
                            <?php else: ?>
                                · <span class="tag-intern">nur intern</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($s['beschreibung'])): ?>
                            <p class="schicht-desc"><?= htmlspecialchars($s['beschreibung']) ?></p>
                        <?php endif; ?>

                        <?php if ($zugeteilt): ?>
                            <div class="zugeteilt-label">Zugeteilt</div>
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
                </div><!-- letztes .kachel-grid schließen -->
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
