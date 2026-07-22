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
        .neu-kachel .field-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .neu-kachel .field-row .form-group { flex: 1; min-width: 140px; }
        .neu-kachel label { display:block; font-size: 0.75rem; margin-bottom: 0.25rem; color: var(--text-light); }
        .neu-kachel input, .neu-kachel textarea {
            width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem;
        }
        .neu-kachel .anmeldung-toggle { display:flex; align-items:center; gap:0.5rem; margin:0.75rem 0; }
        .neu-kachel .anmeldung-toggle input { width:auto; }

        /* Eine Kachel pro Tag; Schichten als Tabellen-/Spaltenzeilen. */
        .tag-kachel { padding: 0; }
        .zeile-kopf, .schicht-zeile {
            display: grid;
            grid-template-columns:
                104px                   /* Zeit */
                minmax(100px, 1.2fr)    /* Schicht */
                minmax(78px, 0.7fr)     /* Ort */
                minmax(92px, 1fr)       /* Beschreibung */
                minmax(110px, 1.1fr)    /* Zugeteilt */
                minmax(120px, 1fr)      /* Helfer zuteilen */
                50px                    /* Bedarf */
                96px                    /* Sichtbar */
                30px;                   /* Löschen */
            gap: 0.6rem;
            align-items: start;
            padding: 0.7rem 1rem;
        }
        .zeile-kopf > div, .col { min-width: 0; overflow-wrap: anywhere; }
        /* Löschen klar abgesetzt, wirkt wie eigene Spalte. */
        .zeile-kopf > :last-child, .col-loeschen { margin-left: 0.5rem; }
        .zeile-kopf {
            border-bottom: 2px solid var(--border);
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--text-light); font-weight: 600;
        }
        .schicht-zeile { border-top: 1px solid var(--border); }
        .schicht-zeile:hover { background: #fafafa; }
        .name-haupt { font-weight: 600; font-size: 0.95rem; }
        .name-sub { font-size: 0.78rem; color: var(--text-light); margin-top: 0.25rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .muted { color: var(--text-light); opacity: 0.7; }
        .besetzung {
            display: inline-block; font-size: 0.78rem; font-weight: 700;
            padding: 0.15rem 0.5rem; border-radius: 999px; white-space: nowrap;
        }
        .besetzung.voll { background: var(--success-bg); color: var(--success); }
        .besetzung.offen { background: #fff3cd; color: #856404; }
        .helfer-chips { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .helfer-chip {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 999px; padding: 0.15rem 0.35rem 0.15rem 0.6rem;
            font-size: 0.8rem;
        }
        .helfer-chip button {
            border: none; background: transparent; cursor: pointer;
            color: var(--error); font-size: 1rem; line-height: 1; padding: 0 0.2rem;
        }
        .col-zuteilen select { width: 100%; padding: 0.35rem 0.5rem; }
        .gemeldet-mini { margin-top: 0.4rem; }
        .gemeldet-label { font-size: 0.68rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.04em; }
        .gemeldet-chip { background: #f5f8ff; border-color: #c9d8ff; }
        .chip-status { font-size: 0.7rem; color: #856404; }
        .chip-add {
            border: none; background: var(--primary); color: var(--white);
            border-radius: 999px; padding: 0.05rem 0.4rem; font-size: 0.8rem; cursor: pointer; line-height: 1.2;
        }
        .chip-add:hover { background: var(--primary-dark); }
        .col-bedarf { display: flex; align-items: center; gap: 0.5rem; }
        .del-btn { border: none; background: transparent; cursor: pointer; font-size: 0.95rem; opacity: 0.55; }
        .del-btn:hover { opacity: 1; }
        .tag-anmeldung { color: var(--success); font-weight: 600; }
        .tag-intern { color: var(--text-light); font-style: italic; }
        .info-i {
            display: inline-flex; align-items: center; justify-content: center;
            width: 15px; height: 15px; border-radius: 50%;
            background: var(--text-light); color: var(--white);
            font-size: 0.65rem; font-style: italic; font-weight: 700;
            cursor: help; user-select: none;
        }

        /* Inline-Edit (Doppelklick). Editor als kleines Popover unter dem Feld,
           damit die schmalen Spalten nicht überlaufen. */
        .ie { display: inline; position: relative; }
        .ie-view { cursor: text; border-radius: 3px; padding: 0 2px; }
        .ie-view:hover { background: #eef3ff; box-shadow: inset 0 -1px 0 var(--border); }
        .ie-edit { display: none; }
        .ie.editing .ie-view { opacity: 0.4; }
        .ie.editing .ie-edit {
            display: inline-flex; align-items: center; gap: 0.3rem; flex-wrap: wrap;
            position: absolute; top: 100%; left: 0; z-index: 20; margin-top: 2px;
            background: var(--white); border: 1px solid var(--primary); border-radius: 6px;
            padding: 0.4rem; box-shadow: 0 4px 14px rgba(0,0,0,0.15); min-width: max-content;
        }
        .ie-edit input, .ie-edit textarea, .ie-edit select {
            padding: 0.3rem 0.4rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; font-family: inherit;
        }
        .ie-titel .ie-edit input { min-width: 14rem; }
        .ie-desc .ie-edit textarea { min-width: 16rem; resize: vertical; }
        .ie-zeit-edit { flex-direction: column; align-items: flex-start; }
        .ie-zeit-edit label { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; color: var(--text-light); }
        .ie-zeit-edit input[type="time"] { width: 7rem; }
        .ie-bedarf .ie-edit input { width: 4rem; }
        .ie-save {
            border: none; background: var(--primary); color: var(--white);
            border-radius: 4px; padding: 0.25rem 0.5rem; cursor: pointer; font-size: 0.85rem; line-height: 1;
        }
        .ie-save:hover { background: var(--primary-dark); }

        /* Schmalere Screens: Spalten aufbrechen, Zeilen stapeln (nur vertikales Scrollen). */
        @media (max-width: 1100px) {
            .zeile-kopf { display: none; }
            .schicht-zeile { grid-template-columns: 1fr; gap: 0.4rem; padding: 0.85rem 1rem; }
            .col { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: baseline; margin-left: 0 !important; }
            .col::before {
                content: attr(data-label); flex: 0 0 7rem;
                font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em;
                color: var(--text-light); font-weight: 600;
            }
            .col[data-label=""]::before { display: none; }
            .col-name { font-size: 1rem; }
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
                // Nach Tag gruppieren (Reihenfolge aus SQL: Tage aufsteigend, ohne Tag zuletzt).
                $byTag = [];
                foreach ($schichten as $s) {
                    $byTag[(string) ($s['tag'] ?? '')][] = $s;
                }
                // Je Tag sortieren: "Ganzer Tag" oben, dann nach Startzeit (von),
                // Freitext-Zeitfenster (ohne feste Uhrzeit) ans Ende.
                foreach ($byTag as &$_list) {
                    usort($_list, static function (array $a, array $b): int {
                        $ra = ($a['titel'] === 'Ganzer Tag') ? 0 : 1;
                        $rb = ($b['titel'] === 'Ganzer Tag') ? 0 : 1;
                        if ($ra !== $rb) { return $ra <=> $rb; }
                        $va = !empty($a['von']) ? substr((string) $a['von'], 0, 5) : '99:99';
                        $vb = !empty($b['von']) ? substr((string) $b['von'], 0, 5) : '99:99';
                        if ($va !== $vb) { return strcmp($va, $vb); }
                        return strcmp((string) ($a['zeitfenster'] ?? '') . $a['titel'],
                                      (string) ($b['zeitfenster'] ?? '') . $b['titel']);
                    });
                }
                unset($_list);

                // Inline-editierbares Feld (Doppelklick -> Eingabe, Haken/Enter speichert).
                $ie = function (int $sid, string $field, $value, string $viewHtml, array $opt = []) use ($csrfToken): string {
                    $type = $opt['type'] ?? 'text';
                    $ph   = htmlspecialchars((string) ($opt['placeholder'] ?? ''));
                    $cls  = (string) ($opt['class'] ?? '');
                    $val  = htmlspecialchars((string) ($value ?? ''));
                    $csrf = htmlspecialchars($csrfToken);
                    $inner = $type === 'textarea'
                        ? '<textarea name="' . $field . '" rows="2" placeholder="' . $ph . '">' . $val . '</textarea>'
                        : '<input type="' . $type . '" name="' . $field . '" value="' . $val . '" placeholder="' . $ph . '"' . ($type === 'number' ? ' min="1"' : '') . '>';
                    return '<form method="post" action="api/schicht_field.php" class="ie ' . $cls . '">'
                        . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                        . '<input type="hidden" name="schicht_id" value="' . $sid . '">'
                        . '<span class="ie-view" tabindex="0" title="Doppelklick zum Bearbeiten">' . $viewHtml . '</span>'
                        . '<span class="ie-edit">' . $inner . '<button type="submit" class="ie-save" title="Speichern">✓</button></span>'
                        . '</form>';
                };
                ?>
                <?php foreach ($byTag as $tag => $tagSchichten): ?>
                    <h2 class="tag-heading"><?= htmlspecialchars($tag !== '' ? helferTagLabel($tag) : 'Ohne festen Termin') ?></h2>
                    <div class="kachel tag-kachel">
                        <div class="zeile-kopf">
                            <div>Zeit</div><div>Schicht</div><div>Ort</div><div>Beschreibung</div><div>Zugeteilt</div><div>Helfer zuteilen</div><div>Bedarf</div><div>Sichtbar</div><div></div>
                        </div>
                    <?php foreach ($tagSchichten as $s): ?>
                        <?php
                        $sid = (int) $s['id'];
                        $zugeteilt = $zuteilungen[$sid] ?? [];
                        $anzahl = count($zugeteilt);
                        $bedarf = (int) $s['bedarf'];
                        $voll = $anzahl >= $bedarf;
                        $zugeteilteIds = array_map(static fn($z) => (int) $z['helfer_id'], $zugeteilt);
                        $zf = helferSchichtZeitfenster($s);
                        $vonV = $s['von'] ? substr((string) $s['von'], 0, 5) : '';
                        $bisV = $s['bis'] ? substr((string) $s['bis'], 0, 5) : '';
                        $gemeldetOffen = array_filter(
                            $gemeldet[$sid] ?? [],
                            static fn($g) => !in_array((int) $g['helfer_id'], $zugeteilteIds, true)
                        );
                        ?>
                        <div class="schicht-zeile" id="schicht-<?= $sid ?>">
                            <!-- 1) Zeit / Verfügbarkeit (Doppelklick: von/bis oder Freitext) -->
                            <div class="col col-zeit" data-label="Zeit">
                                <form method="post" action="api/schicht_field.php" class="ie ie-zeit">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                    <span class="ie-view" tabindex="0" title="Doppelklick zum Bearbeiten"><?= htmlspecialchars($zf !== '' ? $zf : 'Zeit offen') ?></span>
                                    <span class="ie-edit ie-zeit-edit">
                                        <label>Von <input type="time" name="von" value="<?= htmlspecialchars($vonV) ?>"></label>
                                        <label>Bis <input type="time" name="bis" value="<?= htmlspecialchars($bisV) ?>"></label>
                                        <label>oder Text <input type="text" name="zeitfenster" maxlength="80" value="<?= htmlspecialchars($s['zeitfenster'] ?? '') ?>" placeholder="z.B. nach Absprache"></label>
                                        <button type="submit" class="ie-save" title="Speichern">✓</button>
                                    </span>
                                </form>
                            </div>

                            <!-- 2) Schichtname (Doppelklick) -->
                            <div class="col col-name" data-label="Schicht">
                                <div class="name-haupt"><?= $ie($sid, 'titel', $s['titel'], htmlspecialchars($s['titel']), ['class' => 'ie-titel']) ?></div>
                            </div>

                            <!-- 3) Ort (Doppelklick) -->
                            <div class="col col-ort" data-label="Ort">
                                <?= $ie($sid, 'ort', $s['ort'] ?? '', $s['ort'] ? '📍 ' . htmlspecialchars($s['ort']) : '<span class="muted">+ Ort</span>') ?>
                            </div>

                            <!-- 4) Beschreibung (Doppelklick) -->
                            <div class="col col-beschreibung" data-label="Beschreibung">
                                <?= $ie($sid, 'beschreibung', $s['beschreibung'] ?? '', $s['beschreibung'] ? nl2br(htmlspecialchars($s['beschreibung'])) : '<span class="muted">+ Beschreibung</span>', ['type' => 'textarea', 'placeholder' => 'Was ist zu tun? Details …', 'class' => 'ie-desc']) ?>
                            </div>

                            <!-- 5) Zugeteilte Helfer -->
                            <div class="col col-zugeteilt" data-label="Zugeteilt">
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
                                <?php else: ?>
                                    <span class="muted">–</span>
                                <?php endif; ?>
                            </div>

                            <!-- 7) Helfer zuteilen (Auswahl fügt direkt hinzu) + Gemeldete als Schnellzuteilung -->
                            <div class="col col-zuteilen" data-label="Helfer zuteilen">
                                <form method="post" action="api/schicht_zuteilung.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                    <select name="helfer_id" onchange="this.form.submit()" aria-label="Bestätigten Helfer zuteilen">
                                        <option value="">+ Helfer wählen …</option>
                                        <?php foreach ($confirmedHelfer as $h): ?>
                                            <?php if (in_array((int) $h['id'], $zugeteilteIds, true)) continue; ?>
                                            <option value="<?= (int) $h['id'] ?>"><?= htmlspecialchars($h['vorname'] . ' ' . $h['nachname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <?php if ($gemeldetOffen): ?>
                                    <div class="gemeldet-mini">
                                        <span class="gemeldet-label">Gemeldet</span>
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
                                                        <button type="submit" class="chip-add" title="Verbindlich zuteilen">+</button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 8) Bedarfs-Pille (Doppelklick: nur Bedarf änderbar) -->
                            <div class="col col-bedarf" data-label="Bedarf">
                                <form method="post" action="api/schicht_field.php" class="ie ie-bedarf">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                    <span class="ie-view besetzung <?= $voll ? 'voll' : 'offen' ?>" tabindex="0" title="Doppelklick: Bedarf ändern"><?= $anzahl ?>/<?= $bedarf ?></span>
                                    <span class="ie-edit">
                                        <input type="number" name="bedarf" min="1" value="<?= $bedarf ?>">
                                        <button type="submit" class="ie-save" title="Speichern">✓</button>
                                    </span>
                                </form>
                            </div>

                            <!-- 8) Sichtbar (Dropdown schaltet in Anmeldung / nur intern) -->
                            <div class="col col-sichtbar" data-label="Sichtbar">
                                <form method="post" action="api/schicht_field.php" class="ie ie-anm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                    <span class="ie-view" tabindex="0" title="Doppelklick zum Ändern"><?php if ((int) $s['in_anmeldung'] === 1): ?><span class="tag-anmeldung">in Anmeldung</span><?php else: ?><span class="tag-intern">nur intern</span><?php endif; ?></span>
                                    <span class="ie-edit">
                                        <select name="in_anmeldung" onchange="this.form.submit()">
                                            <option value="1" <?= (int) $s['in_anmeldung'] === 1 ? 'selected' : '' ?>>in Anmeldung</option>
                                            <option value="0" <?= (int) $s['in_anmeldung'] === 0 ? 'selected' : '' ?>>nur intern</option>
                                        </select>
                                    </span>
                                </form>
                            </div>

                            <!-- 9) Löschen (eigene Spalte, klar abgesetzt) -->
                            <div class="col col-loeschen" data-label="">
                                <form method="post" action="api/schicht_crud.php" onsubmit="return confirm('Schicht wirklich löschen? Zuteilungen gehen verloren.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="schicht_id" value="<?= $sid ?>">
                                    <button type="submit" class="del-btn" title="Schicht löschen">🗑</button>
                                </form>
                            </div>
                        </div><!-- .schicht-zeile -->
                    <?php endforeach; ?>
                    </div><!-- .tag-kachel -->
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

    // Inline-Bearbeitung per Doppelklick: Ansicht -> Eingabe, Haken/Enter speichert.
    (function() {
        function openEdit(ie) {
            ie.classList.add('editing');
            var field = ie.querySelector('.ie-edit input, .ie-edit textarea, .ie-edit select');
            if (field) { field.focus(); if (field.select) { field.select(); } }
        }
        document.addEventListener('dblclick', function(e) {
            var view = e.target.closest('.ie-view');
            if (!view) { return; }
            openEdit(view.closest('.ie'));
        });
        // Escape bricht ab.
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') { return; }
            var ie = e.target.closest('.ie.editing');
            if (ie) { ie.classList.remove('editing'); }
        });
        // Verlässt der Fokus das Feld (ohne Speichern), Bearbeitung wieder einfrieren.
        document.addEventListener('focusout', function(e) {
            var ie = e.target.closest('.ie.editing');
            if (!ie) { return; }
            setTimeout(function() {
                if (!ie.contains(document.activeElement)) { ie.classList.remove('editing'); }
            }, 150);
        });
    })();
    </script>
</body>
</html>
