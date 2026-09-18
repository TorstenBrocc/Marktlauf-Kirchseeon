<?php
/**
 * Einsatzplan-Board (Admin + Orga) — Zuteilung per Drag & Drop.
 *
 * Arbeitsteilung mit schichten.php: dort werden Schichten GEPFLEGT (anlegen,
 * Titel/Zeit/Ort/Bedarf/Sichtbarkeit inline bearbeiten), hier werden Helfer
 * ZUGETEILT. Beide Seiten lesen dieselben Tabellen, es gibt keine zweite Wahrheit.
 *
 * Bedienung:
 *   - Helfer-Chip aus dem Pool auf eine Schichtkarte ziehen  -> zuteilen
 *   - Chip von Karte auf Karte ziehen                        -> umhaengen
 *   - Chip zurueck in den Pool ziehen                        -> Zuteilung loesen
 *   - Karte am Griff (⠿) ziehen                              -> Reihenfolge des Tages
 *
 * Gespeichert wird sofort per fetch (api/schicht_zuteilung_move.php,
 * api/schicht_sortierung.php), ohne Reload — Muster wie der Datei-Browser
 * (dateien.php / api/file_move.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helfer_aufgaben.php'; // helferTagLabel(), helferSchichtZeitfenster(), schichtenOrderBy()

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();

// Schichten in Anzeigereihenfolge (Handsortierung, Migration 077; faellt ohne
// die Migration auf die bisherige Zeitsortierung zurueck).
$schichten = $pdo->query('SELECT * FROM schichten ORDER BY ' . schichtenOrderBy($pdo))->fetchAll();

// Zuteilungen je Schicht
$zuteilungen = [];
$zStmt = $pdo->query('
    SELECT sz.schicht_id, h.id AS helfer_id, h.vorname, h.nachname, h.status
    FROM schicht_zuteilung sz
    JOIN helfer h ON h.id = sz.helfer_id
    ORDER BY h.nachname, h.vorname
');
foreach ($zStmt as $row) {
    $zuteilungen[(int) $row['schicht_id']][] = $row;
}

// Selbstmeldungen aus dem Anmeldeformular je Schicht — als Vorschlag auf der Karte.
$gemeldet = [];
$gStmt = $pdo->query('
    SELECT hs.schicht_id, h.id AS helfer_id
    FROM helfer_slots hs
    JOIN helfer h ON h.id = hs.helfer_id
    WHERE hs.schicht_id IS NOT NULL
');
foreach ($gStmt as $row) {
    $gemeldet[(int) $row['schicht_id']][] = (int) $row['helfer_id'];
}

// Alle Helfer fuer den Pool. Bewusst nicht nur "bestaetigt": wer sich selbst
// angemeldet hat, ist fachlich dabei — der Status steuert den persoenlichen
// Zugang, nicht die Einteilung. Abgelehnte bleiben draussen.
$helferAlle = $pdo->query('
    SELECT h.id, h.vorname, h.nachname, h.status,
           (SELECT COUNT(*) FROM schicht_zuteilung sz WHERE sz.helfer_id = h.id) AS einsaetze
    FROM helfer h
    WHERE h.status <> "abgelehnt"
    ORDER BY h.nachname, h.vorname
')->fetchAll();

// Beitraege (Kuchen/Sonstiges) als Tooltip am Chip
$beitragProHelfer = [];
foreach ($pdo->query('SELECT helfer_id, typ, freitext FROM helfer_beitrag ORDER BY typ') as $row) {
    $label = $row['typ'] === 'kuchen' ? 'Kuchen' : 'Sonstiges';
    if (!empty($row['freitext'])) {
        $label .= ': ' . $row['freitext'];
    }
    $beitragProHelfer[(int) $row['helfer_id']][] = $label;
}

// Nach Tag gruppieren (Reihenfolge der Schichten bleibt erhalten)
$byTag = [];
foreach ($schichten as $s) {
    $byTag[(string) ($s['tag'] ?? '')][] = $s;
}

$gesamtBedarf = 0;
$gesamtZugeteilt = 0;
foreach ($schichten as $s) {
    $gesamtBedarf += (int) $s['bedarf'];
    $gesamtZugeteilt += count($zuteilungen[(int) $s['id']] ?? []);
}

/** Tooltip-Text der Beiträge eines Helfers ("" wenn keine). */
function boardBeitragTooltip(array $beitragProHelfer, int $helferId): string {
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
    <title>Einsatzplan-Board | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .board-hinweis {
            font-size: 0.85rem; color: var(--text-light);
            margin: 0 0 1rem; line-height: 1.6;
        }
        .board-hinweis kbd {
            background: var(--white); border: 1px solid var(--border);
            border-radius: 4px; padding: 0.05rem 0.3rem; font-size: 0.8rem;
        }
        .board-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .board-actions .btn-link {
            display: inline-block; padding: 0.45rem 0.9rem; border-radius: 6px;
            border: 1px solid var(--border); background: var(--white);
            color: var(--text); text-decoration: none; font-size: 0.85rem;
        }
        .board-actions .btn-link:hover { border-color: var(--primary); color: var(--primary); }
        .board-actions .btn-link.primary {
            background: var(--primary); border-color: var(--primary); color: #fff;
        }

        /* Zweispaltiges Board: Pool links (klebt), Schichten rechts. */
        .board { display: grid; grid-template-columns: 280px 1fr; gap: 1.25rem; align-items: start; }

        .pool {
            position: sticky; top: 1rem;
            background: var(--white); border: 1px solid var(--border);
            border-radius: 10px; padding: 0.9rem;
            max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
        }
        .pool h2 { font-size: 0.95rem; margin: 0 0 0.6rem; }
        .pool-suche {
            width: 100%; padding: 0.45rem 0.6rem; margin-bottom: 0.6rem;
            border: 1px solid var(--border); border-radius: 6px; font-size: 0.85rem;
        }
        .pool-liste { overflow-y: auto; display: flex; flex-wrap: wrap; gap: 0.35rem; align-content: flex-start; }
        .pool.drag-over { border-color: var(--error); background: #fff6f6; }

        .chip {
            display: inline-flex; align-items: center; gap: 0.3rem;
            background: #eef4ee; border: 1px solid var(--border);
            border-radius: 999px; padding: 0.25rem 0.6rem;
            font-size: 0.82rem; cursor: grab; user-select: none;
        }
        .chip:active { cursor: grabbing; }
        .chip.dragging { opacity: 0.45; }
        .chip .chip-zahl {
            background: var(--primary); color: #fff; border-radius: 999px;
            font-size: 0.68rem; padding: 0 0.32rem; line-height: 1.4;
        }
        .chip.chip-neu { border-style: dashed; background: #fffdf2; }
        .chip .info-i {
            display: inline-flex; align-items: center; justify-content: center;
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--border); color: var(--text-light);
            font-size: 0.62rem; font-weight: 700; cursor: help;
        }
        .chip .chip-drop {
            border: none; background: none; color: var(--text-light);
            cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0;
        }
        .chip .chip-drop:hover { color: var(--error); }

        .tag-block { margin-bottom: 1.75rem; }
        .tag-block > h2 {
            font-size: 1.05rem; margin: 0 0 0.85rem;
            padding-bottom: 0.4rem; border-bottom: 2px solid var(--primary);
        }
        .karten { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 0.85rem; }

        .karte {
            background: var(--white); border: 1px solid var(--border);
            border-radius: 10px; padding: 0.8rem; display: flex; flex-direction: column; gap: 0.5rem;
        }
        .karte.drag-over { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0,150,64,0.15); }
        .karte.karte-dragging { opacity: 0.4; }
        .karte.karte-ziel { border-style: dashed; border-color: var(--primary); }
        .karte-kopf { display: flex; align-items: flex-start; gap: 0.45rem; }
        .karte-griff {
            cursor: grab; color: var(--text-light); font-size: 1rem; line-height: 1;
            padding: 0.1rem 0.15rem; user-select: none;
        }
        .karte-griff:active { cursor: grabbing; }
        .karte-titel { font-weight: 600; font-size: 0.95rem; flex: 1; }
        .karte-meta { font-size: 0.78rem; color: var(--text-light); }
        .karte-desc { font-size: 0.78rem; color: var(--text-light); }
        .bedarf-badge {
            font-size: 0.72rem; font-weight: 600; white-space: nowrap;
            padding: 0.12rem 0.45rem; border-radius: 999px;
            background: var(--error-bg); color: var(--error);
        }
        .bedarf-badge.voll { background: var(--success-bg); color: var(--success); }
        .karte-drop {
            min-height: 2.4rem; border: 1px dashed var(--border); border-radius: 8px;
            padding: 0.35rem; display: flex; flex-wrap: wrap; gap: 0.3rem; align-content: flex-start;
        }
        .karte-drop:empty::after {
            content: 'Helfer hierher ziehen'; color: var(--text-light);
            font-size: 0.75rem; padding: 0.25rem;
        }
        .karte-gemeldet { font-size: 0.72rem; color: var(--text-light); }

        #toast {
            position: fixed; left: 50%; bottom: 1.5rem; transform: translateX(-50%);
            background: #1f2a22; color: #fff; padding: 0.6rem 1rem; border-radius: 8px;
            font-size: 0.85rem; z-index: 999; max-width: 90vw; box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        #toast[hidden] { display: none; }
        #toast.warn { background: #92400e; }
        #toast.err { background: #991b1b; }

        @media (max-width: 900px) {
            .board { grid-template-columns: 1fr; }
            .pool { position: static; max-height: none; }
            .pool-liste { max-height: 220px; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'einsatzplan'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Einsatzplan-Board</h1>
            </header>

            <div class="board-actions">
                <a class="btn-link" href="schichten.php">← Schichten pflegen (Tabelle)</a>
                <a class="btn-link primary" href="api/einsatzplan_download.php" target="_blank" rel="noopener">📄 Gesamtplan als PDF</a>
            </div>

            <p class="board-hinweis">
                Helfer aus dem Pool auf eine Karte ziehen · Chip von Karte zu Karte ziehen hängt um ·
                Chip zurück in den Pool ziehen löst die Zuteilung · Karte am Griff <kbd>⠿</kbd> ziehen
                ändert die Reihenfolge des Tages. Alles wird sofort gespeichert.
                <br>
                <strong><?= $gesamtZugeteilt ?> / <?= $gesamtBedarf ?></strong> Plätze besetzt.
            </p>

            <div class="board">
                <!-- Pool: alle Helfer, gleichzeitig Ablage zum Lösen einer Zuteilung -->
                <aside class="pool" id="pool">
                    <h2>Helfer <span class="muted">(<?= count($helferAlle) ?>)</span></h2>
                    <input type="search" class="pool-suche" id="pool-suche" placeholder="Name suchen …" aria-label="Helfer suchen">
                    <div class="pool-liste" id="pool-liste">
                        <?php foreach ($helferAlle as $h): ?>
                            <?php
                            $hid = (int) $h['id'];
                            $tt = boardBeitragTooltip($beitragProHelfer, $hid);
                            $name = $h['vorname'] . ' ' . $h['nachname'];
                            ?>
                            <span class="chip<?= $h['status'] !== 'bestaetigt' ? ' chip-neu' : '' ?>"
                                  draggable="true"
                                  data-helfer-id="<?= $hid ?>"
                                  data-name="<?= htmlspecialchars($name) ?>"
                                  data-status="<?= htmlspecialchars($h['status']) ?>"
                                  data-tooltip="<?= htmlspecialchars($tt) ?>"
                                  <?= $h['status'] !== 'bestaetigt' ? 'title="Status &bdquo;' . htmlspecialchars($h['status']) . '&ldquo; — noch kein persönlicher Zugang versendet"' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                                <?php if ((int) $h['einsaetze'] > 0): ?>
                                    <span class="chip-zahl" data-rolle="einsaetze"><?= (int) $h['einsaetze'] ?></span>
                                <?php endif; ?>
                                <?php if ($tt !== ''): ?>
                                    <span class="info-i" title="Bringt außerdem mit — <?= htmlspecialchars($tt) ?>">i</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <div class="tage">
                    <?php if (!$byTag): ?>
                        <p class="muted">Noch keine Schichten angelegt — <a href="schichten.php">hier anlegen</a>.</p>
                    <?php endif; ?>
                    <?php foreach ($byTag as $tag => $tagSchichten): ?>
                        <section class="tag-block" data-tag="<?= htmlspecialchars((string) $tag) ?>">
                            <h2><?= htmlspecialchars($tag !== '' ? helferTagLabel((string) $tag) : 'Ohne festen Termin') ?></h2>
                            <div class="karten">
                                <?php foreach ($tagSchichten as $s): ?>
                                    <?php
                                    $sid = (int) $s['id'];
                                    $zug = $zuteilungen[$sid] ?? [];
                                    $anzahl = count($zug);
                                    $bedarf = (int) $s['bedarf'];
                                    $zf = helferSchichtZeitfenster($s);
                                    $zugeteilteIds = array_map(static fn($z) => (int) $z['helfer_id'], $zug);
                                    $offeneMeldungen = array_diff($gemeldet[$sid] ?? [], $zugeteilteIds);
                                    ?>
                                    <article class="karte" id="karte-<?= $sid ?>" data-schicht-id="<?= $sid ?>" data-bedarf="<?= $bedarf ?>">
                                        <div class="karte-kopf">
                                            <span class="karte-griff" title="Ziehen, um die Reihenfolge zu ändern">⠿</span>
                                            <span class="karte-titel"><?= htmlspecialchars($s['titel']) ?></span>
                                            <span class="bedarf-badge<?= $anzahl >= $bedarf ? ' voll' : '' ?>" data-rolle="bedarf">
                                                <?= $anzahl ?>/<?= $bedarf ?>
                                            </span>
                                        </div>
                                        <?php if ($zf !== '' || !empty($s['ort'])): ?>
                                            <div class="karte-meta">
                                                <?= $zf !== '' ? htmlspecialchars($zf) . ' Uhr' : '' ?>
                                                <?= ($zf !== '' && !empty($s['ort'])) ? ' · ' : '' ?>
                                                <?= !empty($s['ort']) ? '📍 ' . htmlspecialchars($s['ort']) : '' ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['beschreibung'])): ?>
                                            <div class="karte-desc"><?= nl2br(htmlspecialchars($s['beschreibung'])) ?></div>
                                        <?php endif; ?>

                                        <div class="karte-drop" data-schicht-id="<?= $sid ?>">
                                            <?php foreach ($zug as $z): ?>
                                                <?php
                                                $hid = (int) $z['helfer_id'];
                                                $tt = boardBeitragTooltip($beitragProHelfer, $hid);
                                                $name = $z['vorname'] . ' ' . $z['nachname'];
                                                ?>
                                                <span class="chip<?= $z['status'] !== 'bestaetigt' ? ' chip-neu' : '' ?>"
                                                      draggable="true"
                                                      data-helfer-id="<?= $hid ?>"
                                                      data-name="<?= htmlspecialchars($name) ?>"
                                                      data-status="<?= htmlspecialchars($z['status']) ?>"
                                                      data-tooltip="<?= htmlspecialchars($tt) ?>">
                                                    <?= htmlspecialchars($name) ?>
                                                    <?php if ($tt !== ''): ?>
                                                        <span class="info-i" title="Bringt außerdem mit — <?= htmlspecialchars($tt) ?>">i</span>
                                                    <?php endif; ?>
                                                    <button type="button" class="chip-drop" title="Zuteilung entfernen">×</button>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php if ($offeneMeldungen): ?>
                                            <div class="karte-gemeldet">
                                                Selbst gemeldet, noch nicht eingeteilt:
                                                <?= count($offeneMeldungen) ?> ·
                                                <a href="schichten.php#schicht-<?= $sid ?>">ansehen</a>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="toast" hidden></div>

    <script>
    (function () {
        'use strict';
        var CSRF = <?= json_encode($csrfToken) ?>;

        var pool = document.getElementById('pool');
        var poolListe = document.getElementById('pool-liste');
        var toastEl = document.getElementById('toast');
        var toastTimer = null;

        function toast(text, art) {
            toastEl.textContent = text;
            toastEl.className = art || '';
            toastEl.hidden = false;
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toastEl.hidden = true; }, art === 'err' ? 6000 : 3500);
        }

        // --- Was wird gerade gezogen? --------------------------------------
        // Zwei Drag-Arten teilen sich die Flaeche: Helfer-Chips und ganze Karten.
        // dragKind haelt sie auseinander, damit ein Chip-Drop nicht als
        // Umsortierung gelesen wird (und umgekehrt).
        var dragKind = null;   // 'helfer' | 'karte'
        var dragChip = null;
        var dragKarte = null;

        function post(url, daten) {
            var body = new URLSearchParams(daten);
            body.append('csrf_token', CSRF);
            return fetch(url, { method: 'POST', body: body }).then(function (r) { return r.json(); });
        }

        /** Zähler "x/y" und Färbung einer Karte nachziehen. */
        function setzeBedarf(karte, anzahl, bedarf, voll) {
            if (!karte) { return; }
            var badge = karte.querySelector('[data-rolle="bedarf"]');
            if (!badge) { return; }
            badge.textContent = anzahl + '/' + bedarf;
            badge.classList.toggle('voll', !!voll);
        }

        /** Einsatz-Zähler am Pool-Chip eines Helfers um delta verschieben. */
        function zaehleEinsaetze(helferId, delta) {
            var chip = poolListe.querySelector('.chip[data-helfer-id="' + helferId + '"]');
            if (!chip) { return; }
            var zahl = chip.querySelector('[data-rolle="einsaetze"]');
            var wert = zahl ? parseInt(zahl.textContent, 10) || 0 : 0;
            wert += delta;
            if (wert <= 0) {
                if (zahl) { zahl.remove(); }
                return;
            }
            if (!zahl) {
                zahl = document.createElement('span');
                zahl.className = 'chip-zahl';
                zahl.setAttribute('data-rolle', 'einsaetze');
                var infoI = chip.querySelector('.info-i');
                chip.insertBefore(zahl, infoI || null);
            }
            zahl.textContent = String(wert);
        }

        /** Chip für eine Schichtkarte bauen (aus einem Pool-Chip heraus). */
        function baueKartenChip(quelle) {
            var chip = document.createElement('span');
            chip.className = 'chip' + (quelle.dataset.status !== 'bestaetigt' ? ' chip-neu' : '');
            chip.draggable = true;
            chip.dataset.helferId = quelle.dataset.helferId;
            chip.dataset.name = quelle.dataset.name;
            chip.dataset.status = quelle.dataset.status;
            chip.dataset.tooltip = quelle.dataset.tooltip || '';
            chip.appendChild(document.createTextNode(quelle.dataset.name + ' '));
            if (chip.dataset.tooltip) {
                var i = document.createElement('span');
                i.className = 'info-i';
                i.title = 'Bringt außerdem mit — ' + chip.dataset.tooltip;
                i.textContent = 'i';
                chip.appendChild(i);
            }
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'chip-drop';
            x.title = 'Zuteilung entfernen';
            x.textContent = '×';
            chip.appendChild(x);
            return chip;
        }

        // --- Drag start/end (delegiert) ------------------------------------
        document.addEventListener('dragstart', function (e) {
            var karte = e.target.closest ? e.target.closest('.karte') : null;
            var chip = e.target.closest ? e.target.closest('.chip') : null;

            if (chip) {
                dragKind = 'helfer';
                dragChip = chip;
                chip.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', chip.dataset.helferId); } catch (_) {}
                return;
            }
            if (karte && karte.draggable) {
                dragKind = 'karte';
                dragKarte = karte;
                karte.classList.add('karte-dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', karte.dataset.schichtId); } catch (_) {}
                return;
            }
            // Alles andere (Text, Bilder) nicht als Board-Drag behandeln.
            dragKind = null;
        });

        document.addEventListener('dragend', function () {
            if (dragChip) { dragChip.classList.remove('dragging'); }
            if (dragKarte) {
                dragKarte.classList.remove('karte-dragging');
                dragKarte.draggable = false;
            }
            document.querySelectorAll('.drag-over, .karte-ziel').forEach(function (el) {
                el.classList.remove('drag-over', 'karte-ziel');
            });
            dragKind = null; dragChip = null; dragKarte = null;
        });

        // Karten sind nur ziehbar, wenn der Zug am Griff beginnt — sonst würde
        // jeder Chip-Drag die ganze Karte mitnehmen.
        document.addEventListener('mousedown', function (e) {
            var griff = e.target.closest('.karte-griff');
            if (!griff) { return; }
            var karte = griff.closest('.karte');
            if (karte) { karte.draggable = true; }
        });
        document.addEventListener('mouseup', function () {
            document.querySelectorAll('.karte[draggable="true"]').forEach(function (k) { k.draggable = false; });
        });

        // --- Dragover: Zielmarkierung --------------------------------------
        document.addEventListener('dragover', function (e) {
            if (dragKind === 'helfer') {
                var ziel = e.target.closest('.karte') || (pool.contains(e.target) ? pool : null);
                if (!ziel) { return; }
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (ziel !== pool || dragChip.closest('.karte')) { ziel.classList.add('drag-over'); }
                return;
            }
            if (dragKind === 'karte') {
                var zielKarte = e.target.closest('.karte');
                if (!zielKarte || zielKarte === dragKarte) { return; }
                // Nur innerhalb desselben Tages umsortieren.
                if (zielKarte.closest('.tag-block') !== dragKarte.closest('.tag-block')) { return; }
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                zielKarte.classList.add('karte-ziel');
            }
        });

        document.addEventListener('dragleave', function (e) {
            var el = e.target.closest ? e.target.closest('.karte, .pool') : null;
            if (el) { el.classList.remove('drag-over', 'karte-ziel'); }
        });

        // --- Drop -----------------------------------------------------------
        document.addEventListener('drop', function (e) {
            if (dragKind === 'helfer' && dragChip) {
                var zielKarte = e.target.closest('.karte');
                var inPool = pool.contains(e.target);
                if (!zielKarte && !inPool) { return; }
                e.preventDefault();
                document.querySelectorAll('.drag-over').forEach(function (el) { el.classList.remove('drag-over'); });

                var quellKarte = dragChip.closest('.karte');
                var helferId = dragChip.dataset.helferId;
                var name = dragChip.dataset.name;

                if (zielKarte) {
                    if (quellKarte === zielKarte) { return; }
                    dropAufKarte(dragChip, quellKarte, zielKarte, helferId, name);
                } else if (quellKarte) {
                    dropInPool(dragChip, quellKarte, helferId);
                }
                return;
            }

            if (dragKind === 'karte' && dragKarte) {
                var ziel = e.target.closest('.karte');
                if (!ziel || ziel === dragKarte) { return; }
                if (ziel.closest('.tag-block') !== dragKarte.closest('.tag-block')) { return; }
                e.preventDefault();
                ziel.classList.remove('karte-ziel');
                sortiereEin(dragKarte, ziel);
            }
        });

        /** Helfer auf eine Schicht ziehen (aus Pool = add, von Karte = move). */
        function dropAufKarte(chip, quellKarte, zielKarte, helferId, name) {
            // Schon auf dieser Karte? Der Server wiese das als No-op ab (UNIQUE auf
            // schicht_id+helfer_id), aber dann stünde der Chip bis zur Antwort doppelt
            // im Bild. Also hier abfangen — ein Zug, der nichts ändert, geht gar nicht los.
            if (zielKarte.querySelector('.karte-drop .chip[data-helfer-id="' + helferId + '"]')) {
                toast(name + ' ist dort bereits eingeteilt.', 'warn');
                return;
            }

            var daten = {
                action: quellKarte ? 'move' : 'add',
                helfer_id: helferId,
                schicht_id: zielKarte.dataset.schichtId
            };
            if (quellKarte) { daten.von_schicht_id = quellKarte.dataset.schichtId; }

            post('api/schicht_zuteilung_move.php', daten).then(function (d) {
                if (!d.ok) { toast(d.message || 'Zuteilen fehlgeschlagen.', 'err'); return; }
                if (d.noop) { toast(d.message, 'warn'); return; }

                var neuerChip = quellKarte ? chip : baueKartenChip(chip);
                zielKarte.querySelector('.karte-drop').appendChild(neuerChip);
                setzeBedarf(zielKarte, d.anzahl, d.bedarf, d.voll);

                if (quellKarte) {
                    // Umhängen: Quellkarte nachzählen, Gesamtzahl bleibt gleich.
                    aktualisiereKarte(quellKarte);
                } else {
                    zaehleEinsaetze(helferId, 1);
                }
                toast(d.warn ? (d.message + ' — ' + d.warn) : d.message, d.warn ? 'warn' : '');
            }).catch(function () { toast('Zuteilen fehlgeschlagen.', 'err'); });
        }

        /** Zuteilung lösen (Chip zurück in den Pool). */
        function dropInPool(chip, quellKarte, helferId) {
            post('api/schicht_zuteilung_move.php', {
                action: 'remove',
                helfer_id: helferId,
                von_schicht_id: quellKarte.dataset.schichtId
            }).then(function (d) {
                if (!d.ok) { toast(d.message || 'Entfernen fehlgeschlagen.', 'err'); return; }
                chip.remove();
                setzeBedarf(quellKarte, d.anzahl, d.bedarf, d.voll);
                zaehleEinsaetze(helferId, -1);
                toast(d.message);
            }).catch(function () { toast('Entfernen fehlgeschlagen.', 'err'); });
        }

        /** Zähler einer Karte aus dem DOM nachziehen (nach Umhängen). */
        function aktualisiereKarte(karte) {
            var anzahl = karte.querySelectorAll('.karte-drop .chip').length;
            var bedarf = parseInt(karte.dataset.bedarf, 10) || 0;
            setzeBedarf(karte, anzahl, bedarf, anzahl >= bedarf);
        }

        /** Karte vor die Zielkarte einsortieren und die Reihenfolge speichern. */
        function sortiereEin(karte, ziel) {
            var block = karte.closest('.tag-block');
            var container = ziel.parentNode;
            // Zieht man nach hinten, hinter das Ziel einfügen — sonst davor.
            var karten = Array.prototype.slice.call(container.children);
            var vonIdx = karten.indexOf(karte);
            var zuIdx = karten.indexOf(ziel);
            if (vonIdx < zuIdx) {
                container.insertBefore(karte, ziel.nextSibling);
            } else {
                container.insertBefore(karte, ziel);
            }

            var order = Array.prototype.map.call(
                block.querySelectorAll('.karte'),
                function (k) { return k.dataset.schichtId; }
            );
            var body = new URLSearchParams();
            body.append('csrf_token', CSRF);
            body.append('tag', block.dataset.tag || '');
            order.forEach(function (id) { body.append('order[]', id); });

            fetch('api/schicht_sortierung.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { toast(d.message || 'Reihenfolge nicht gespeichert.', 'err'); return; }
                    toast('Reihenfolge gespeichert.');
                })
                .catch(function () { toast('Reihenfolge nicht gespeichert.', 'err'); });
        }

        // --- Chip-× (Zuteilung entfernen ohne Drag) -------------------------
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.chip-drop');
            if (!btn) { return; }
            var chip = btn.closest('.chip');
            var karte = chip.closest('.karte');
            if (!karte) { return; }
            dropInPool(chip, karte, chip.dataset.helferId);
        });

        // --- Pool-Suche ------------------------------------------------------
        var suche = document.getElementById('pool-suche');
        suche.addEventListener('input', function () {
            var q = suche.value.trim().toLowerCase();
            poolListe.querySelectorAll('.chip').forEach(function (chip) {
                var treffer = q === '' || (chip.dataset.name || '').toLowerCase().indexOf(q) !== -1;
                chip.style.display = treffer ? '' : 'none';
            });
        });
    })();
    </script>
</body>
</html>
