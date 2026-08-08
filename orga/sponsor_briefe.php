<?php
/**
 * Sponsorenbriefe-Editor (Admin + Orga).
 * Split-View: links Markdown, rechts Live-Vorschau (serverseitig gerendert,
 * identisch zum echten Versand). Drei Vorlagen: Erstanschreiben, Folgejahr, Frei.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/sponsor_brief.php';
require_once __DIR__ . '/../src/channels/mail.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$slug = (string) ($_GET['slug'] ?? 'erstanschreiben');
if (!sponsorBriefSlugValid($slug)) {
    $slug = 'erstanschreiben';
}

$pdo = getDbConnection();
$vorlage = sponsorBriefLoad($pdo, $slug, (int) $user['id']);
$defaults = sponsorBriefDefaults();
$default = $defaults[$slug];
$platzhalter = sponsorBriefPlatzhalterHilfe($slug);

// Erstanschreiben = shared für alle; die anderen drei = user-scoped.
$isUserScoped = in_array($slug, ['folgejahr', 'bestaetigung', 'frei'], true);
$hasStandardtext = $slug !== 'frei';
$draftHinweis = '';
if ($isUserScoped && $vorlage['draft'] && $vorlage['draft_ts'] !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $vorlage['draft_ts']);
    if ($dt) {
        $draftHinweis = 'Gespeichert am ' . $dt->format('d.m.Y, H:i') . ' Uhr';
    }
}

// Einstellungen für den Admin-Bereich laden
$briefSettings = [];
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('sponsor_brief_event_datum','sponsor_brief_antwort_bis','sponsoring_pakete')");
    while ($row = $stmt->fetch()) { $briefSettings[$row['key']] = $row['value']; }
} catch (PDOException $e) {}

$briefEventDatum = $briefSettings['sponsor_brief_event_datum'] ?? '';
$briefAntwortBis = $briefSettings['sponsor_brief_antwort_bis'] ?? '';
$paketeDefaults = [
    ['key'=>'hauptsponsor','name'=>'Hauptsponsor','investition'=>'auf Anfrage',
     'highlights'=>'Zentraler Partner des Events, maximale Sichtbarkeit auf allen Kanälen'],
    ['key'=>'gold','name'=>'Gold','investition'=>'1.000 €',
     'highlights'=>'Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fläche, 5 Startplätze, Moderations-Erwähnungen'],
    ['key'=>'silber','name'=>'Silber','investition'=>'500 €',
     'highlights'=>'Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze'],
    ['key'=>'bronze','name'=>'Bronze','investition'=>'250 €',
     'highlights'=>'Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben'],
];
$paketeMap = [];
if (!empty($briefSettings['sponsoring_pakete'])) {
    $decoded = json_decode($briefSettings['sponsoring_pakete'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $p) { if (isset($p['key'])) $paketeMap[$p['key']] = $p; }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sponsorenbriefe | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .brief-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .brief-tab {
            padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none;
            background: var(--white); border: 1px solid var(--border); color: var(--text);
            font-size: 0.9rem;
        }
        .brief-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .brief-card {
            background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card);
            padding: 1.5rem; margin-bottom: 1.25rem;
        }
        .brief-betreff { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.95rem; box-sizing: border-box; }
        .brief-platzhalter { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.75rem 0; }
        .ph-chip {
            font-family: monospace; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px;
            background: var(--bg); border: 1px solid var(--border); cursor: pointer; color: var(--text);
        }
        .ph-chip:hover { background: var(--border); }
        .brief-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 900px) { .brief-split { grid-template-columns: 1fr; } }
        .brief-split-head h3 { font-size: 0.9rem; color: var(--text-light); }
        #koerper_md {
            width: 100%; min-height: 460px; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 4px; font-family: monospace; font-size: 0.85rem; line-height: 1.5;
            box-sizing: border-box; resize: vertical;
        }
        #preview-frame {
            width: 100%; height: 460px; border: 1px solid var(--border); border-radius: 4px; background: #fff; box-sizing: border-box;
        }
        .brief-split-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem; }
        .brief-split-head h3 { margin: 0; }
        .preview-toggle-btn {
            font-size: 0.78rem; background: none; border: 1px solid var(--border);
            border-radius: 4px; padding: 0.15rem 0.55rem; cursor: pointer; color: var(--text-light); line-height: 1.5;
        }
        .preview-toggle-btn:hover { background: var(--border); color: var(--text); }
        .brief-split.preview-hidden { grid-template-columns: 1fr; }
        .brief-split.preview-hidden .preview-col { display: none; }
        .brief-actions { display: flex; gap: 1rem; margin-top: 1.25rem; align-items: center; }
        .brief-hint { font-size: 0.8rem; color: var(--text-light); }
        .baustein-panel { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 0.85rem 1rem; margin-bottom: 1rem; }
        .baustein-panel > h4 { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-light); margin: 0 0 0.4rem; }
        .baustein-item { border-bottom: 1px solid var(--border); }
        .baustein-item:last-of-type { border-bottom: none; }
        .baustein-header { display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0; }
        .baustein-header label { font-size: 0.875rem; cursor: pointer; flex: 1; user-select: none; }
        .baustein-header input[type=checkbox] { accent-color: var(--primary); cursor: pointer; flex-shrink: 0; }
        .baustein-expand-btn {
            font-size: 0.72rem; background: none; border: 1px solid var(--border);
            border-radius: 3px; padding: 0.1rem 0.4rem; cursor: pointer; color: var(--text-light);
            white-space: nowrap; line-height: 1.5;
        }
        .baustein-expand-btn:hover { background: var(--border); color: var(--text); }
        .baustein-body { padding: 0.3rem 0 0.6rem 1.4rem; }
        .baustein-text {
            width: 100%; min-height: 72px; padding: 0.35rem 0.5rem;
            border: 1px solid var(--border); border-radius: 4px;
            font-family: monospace; font-size: 0.78rem; line-height: 1.4;
            box-sizing: border-box; resize: vertical; background: var(--white);
        }
        .baustein-actions { margin-top: 0.65rem; }
        .plakat-liste { list-style: none; margin: 0.75rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
        .plakat-item { display: flex; align-items: center; flex-wrap: nowrap; gap: 0.75rem; padding: 0.5rem 0.75rem; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; }
        .plakat-item span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
        .plakat-item .btn-del { flex-shrink: 0; padding: 0.25rem 0.6rem; font-size: 0.8rem; }
        .plakat-item .anhang-abwahl { flex-shrink: 0; accent-color: var(--primary); width: 1rem; height: 1rem; cursor: pointer; margin: 0; }
        .plakat-section-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
        .plakat-badge { display: inline-flex; align-items: center; gap: 0.3rem; background: var(--primary); color: #fff; border-radius: 12px; padding: 0.15rem 0.6rem; font-size: 0.75rem; font-weight: 600; }
        .plakat-hinweis { font-size: 0.8rem; color: var(--text); background: rgba(255,193,7,0.15); border: 1px solid rgba(255,193,7,0.55); border-radius: 6px; padding: 0.5rem 0.75rem; margin: 0.75rem 0 0.5rem; line-height: 1.5; }
        .plakat-upload-form { margin-top: 0.75rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body>
<?php $activeNav = 'sponsor_briefe'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Sponsorenbriefe</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="brief-tabs">
                <?php foreach ($defaults as $s => $d): ?>
                    <a class="brief-tab<?= $s === $slug ? ' active' : '' ?>" href="sponsor_briefe.php?slug=<?= urlencode($s) ?>"><?= htmlspecialchars($d['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <form method="post" action="api/sponsor_brief_save.php" id="brief-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">

                <div class="brief-card">
                    <label for="betreff"><strong>Betreff</strong></label>
                    <input type="text" id="betreff" name="betreff" class="brief-betreff" maxlength="255"
                           value="<?= htmlspecialchars($vorlage['betreff']) ?>">

                    <div class="brief-platzhalter">
                        <span class="brief-hint">Platzhalter einfügen:</span>
                        <?php foreach ($platzhalter as $ph => $beschreibung): ?>
                            <span class="ph-chip" data-ph="<?= htmlspecialchars($ph) ?>" title="<?= htmlspecialchars($beschreibung) ?>"><?= htmlspecialchars($ph) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($slug === 'bestaetigung'): ?>
                    <div class="baustein-panel">
                        <h4>Abschnitte</h4>
                        <?php foreach (sponsorBestaetigungSektionen() as $sek): ?>
                        <div class="baustein-item">
                            <div class="baustein-header">
                                <input type="checkbox" id="baustein-<?= $sek['id'] ?>" class="baustein-cb"
                                       data-id="<?= htmlspecialchars($sek['id']) ?>"
                                       <?= $sek['checked'] ? 'checked' : '' ?>>
                                <label for="baustein-<?= $sek['id'] ?>"><?= htmlspecialchars($sek['titel']) ?></label>
                                <button type="button" class="baustein-expand-btn" data-id="<?= $sek['id'] ?>">Text ▾</button>
                            </div>
                            <div class="baustein-body" id="baustein-body-<?= $sek['id'] ?>" hidden>
                                <textarea class="baustein-text" data-id="<?= htmlspecialchars($sek['id']) ?>"><?= htmlspecialchars($sek['text']) ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="baustein-actions">
                            <button type="button" class="btn btn-secondary btn-small" id="btn-zusammenstellen">Zusammenstellen →</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="brief-split" id="brief-split">
                        <div>
                            <div class="brief-split-head">
                                <h3>Markdown</h3>
                                <?= sponsorMarkdownLegende() ?>
                            </div>
                            <textarea id="koerper_md" name="koerper_md"><?= htmlspecialchars($vorlage['koerper_md']) ?></textarea>
                        </div>
                        <div class="preview-col">
                            <div class="brief-split-head">
                                <h3>Vorschau (Beispieldaten)</h3>
                                <button type="button" class="preview-toggle-btn" id="preview-toggle">Vorschau ausblenden</button>
                            </div>
                            <iframe id="preview-frame" sandbox="" title="Vorschau"></iframe>
                        </div>
                    </div>

                    <div class="brief-actions">
                        <?php if ($isUserScoped): ?>
                            <button type="button" class="btn btn-primary" id="btn-save">Speichern</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        <?php endif; ?>
                        <?php if ($hasStandardtext): ?>
                            <button type="button" class="btn btn-secondary" id="reset-default"><?= $slug === 'bestaetigung' ? 'Reset Abschnittsblöcke' : 'Standardtext wiederherstellen' ?></button>
                        <?php endif; ?>
                        <?php if ($slug === 'bestaetigung'): ?>
                            <button type="button" class="btn btn-secondary" id="reset-original">Ursprungstext</button>
                        <?php endif; ?>
                        <span id="draft-status" class="brief-hint"><?= htmlspecialchars($draftHinweis) ?></span>
                    </div>
                </div>
            </form>

            <?php if (in_array($slug, ['frei', 'bestaetigung'], true)):
                $istBestaetigung = $slug === 'bestaetigung';
                $plakatFolder = driveConfigured() ? drivePlakatFolderId($pdo, driveRennJahr($pdo)) : null;
                $plakat_files = [];
                if ($plakatFolder !== null) {
                    try {
                        foreach (driveListChildren($plakatFolder) as $pf) { if (!$pf['isFolder']) { $plakat_files[] = $pf; } }
                    } catch (Throwable $e) { $plakat_files = []; }
                }
                $assetFolder = ($istBestaetigung && driveConfigured()) ? driveBestaetigungAssetsFolderId($pdo) : null;
                $asset_files = [];
                if ($assetFolder !== null) {
                    try {
                        foreach (driveListChildren($assetFolder) as $af) { if (!$af['isFolder']) { $asset_files[] = $af; } }
                    } catch (Throwable $e) { $asset_files = []; }
                }
            ?>
            <div class="brief-card" style="margin-top:1.25rem">
                <div class="plakat-section">
                    <div class="plakat-section-header">
                        <strong>📎 <?= $istBestaetigung ? 'Anhänge' : 'Plakate als Anhang' ?></strong>
                    </div>

                    <?php
                    // Eine Anhang-Gruppe (Plakate oder Bestätigungs-Assets) rendern.
                    // Bei der Bestätigung bekommt jede Datei eine Abwahl-Checkbox; die Abwahl
                    // lebt browser-seitig (localStorage) und gilt bis zum nächsten Versand.
                    $renderGruppe = function (array $files, ?string $folder, string $gruppe, string $icon,
                                              string $titel, string $emptyMsg, string $keinOrdnerMsg) use ($istBestaetigung, $csrfToken, $slug) {
                        if ($istBestaetigung): ?>
                        <h4 style="font-size:0.82rem;font-weight:600;color:var(--text-light);margin:0.9rem 0 0.3rem;"><?= htmlspecialchars($titel) ?></h4>
                        <?php endif; ?>
                        <?php if ($folder === null): ?>
                            <p class="brief-hint" style="margin:0.5rem 0;"><?= $keinOrdnerMsg ?></p>
                        <?php elseif (count($files) > 0): ?>
                        <ul class="plakat-liste">
                            <?php foreach ($files as $f): $kb = round((int)($f['size'] ?? 0) / 1024); ?>
                                <li class="plakat-item">
                                    <?php if ($istBestaetigung): ?>
                                    <input type="checkbox" class="anhang-abwahl" data-group="<?= $gruppe ?>"
                                           value="<?= htmlspecialchars($f['id']) ?>" title="An-/Abwählen" checked>
                                    <?php endif; ?>
                                    <span title="<?= htmlspecialchars($f['name']) ?>"><?= $icon ?> <?= htmlspecialchars($f['name']) ?></span>
                                    <small class="brief-hint"><?= $kb ?> KB</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="brief-hint" style="margin:0.5rem 0;"><?= $emptyMsg ?></p>
                        <?php endif;
                    };

                    $renderGruppe($plakat_files, $plakatFolder, 'plakat', '📄', 'Plakate',
                        'Der festgelegte Plakate-Ordner ist leer — es werden keine Plakate angehängt.',
                        'Kein Plakate-Ordner festgelegt. Öffne unter „Dateien" den gewünschten Ordner und klicke „📌 Als Plakate-Ordner".');

                    if ($istBestaetigung) {
                        $renderGruppe($asset_files, $assetFolder, 'asset', '📎', 'Bestätigungs-Anhänge',
                            'Der Bestätigungs-Anhang-Ordner ist leer.',
                            'Kein Bestätigungs-Anhang-Ordner festgelegt. Öffne unter „Dateien" den Ordner „Sponsoren-Bestätigung" und klicke „📎 Als Bestätigungs-Anhang-Ordner".');
                    }
                    ?>

                    <p class="plakat-hinweis">⚠️ Die Anhänge kommen live aus den festgelegten Ordnern — was dort liegt, wird angehängt.<?php if ($istBestaetigung): ?> Abgewählte Dateien bleiben abgewählt bis zum nächsten Versand, danach sind wieder alle dabei. Die Abwahl gilt nur für dich.<?php endif; ?> Dateien endgültig entfernen: unter „Dateien".</p>

                    <?php if ($plakatFolder !== null): ?>
                    <form method="post" action="api/file_upload.php" enctype="multipart/form-data" class="plakat-upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="tab" value="orga">
                        <input type="hidden" name="folder" value="<?= htmlspecialchars($plakatFolder) ?>">
                        <input type="hidden" name="redirect_after" value="sponsor_briefe.php?slug=<?= urlencode($slug) ?>">
                        <input type="file" name="datei" accept="application/pdf" required style="font-size:0.9rem;">
                        <button type="submit" class="btn btn-primary">Plakat-PDF hochladen</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="api/sponsor_brief_settings_save.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <div class="brief-card" style="margin-top:1.5rem">
                    <h3 style="font-size:0.95rem;margin:0 0 1rem">Platzhalter-Einstellungen</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
                        <div>
                            <label style="display:block;font-size:0.85rem;color:var(--text-light);margin-bottom:0.35rem">
                                Event-Datum <code style="font-size:0.8rem">{{event_datum}}</code>
                            </label>
                            <input type="date" name="sponsor_brief_event_datum"
                                   value="<?= htmlspecialchars($briefEventDatum) ?>"
                                   style="padding:0.4rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.9rem;width:100%;box-sizing:border-box">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.85rem;color:var(--text-light);margin-bottom:0.35rem">
                                Rückmeldefrist <code style="font-size:0.8rem">{{antwort_bis}}</code>
                            </label>
                            <input type="date" name="sponsor_brief_antwort_bis"
                                   value="<?= htmlspecialchars($briefAntwortBis) ?>"
                                   style="padding:0.4rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.9rem;width:100%;box-sizing:border-box">
                        </div>
                    </div>
                    <h3 style="font-size:0.95rem;margin:0 0 0.6rem">
                        Sponsoring-Pakete <code style="font-size:0.8rem">{{paket_tabelle}}</code>
                    </h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-bottom:1rem">
                        <thead>
                            <tr style="background:var(--bg)">
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border);width:110px">Paket</th>
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border);width:140px">Investition</th>
                                <th style="text-align:left;padding:0.4rem 0.5rem;border-bottom:1px solid var(--border)">Highlights</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paketeDefaults as $pd):
                            $pVals = $paketeMap[$pd['key']] ?? $pd; ?>
                            <tr>
                                <td style="padding:0.4rem 0.5rem;font-weight:500;white-space:nowrap"><?= htmlspecialchars($pd['name']) ?></td>
                                <td style="padding:0.4rem 0.5rem">
                                    <input type="text" name="paket_<?= htmlspecialchars($pd['key']) ?>_investition"
                                           value="<?= htmlspecialchars((string)($pVals['investition'] ?? $pd['investition'])) ?>"
                                           maxlength="60"
                                           style="width:100%;padding:0.35rem;border:1px solid var(--border);border-radius:4px;box-sizing:border-box">
                                </td>
                                <td style="padding:0.4rem 0.5rem">
                                    <input type="text" name="paket_<?= htmlspecialchars($pd['key']) ?>_highlights"
                                           value="<?= htmlspecialchars((string)($pVals['highlights'] ?? $pd['highlights'])) ?>"
                                           maxlength="255"
                                           style="width:100%;padding:0.35rem;border:1px solid var(--border);border-radius:4px;box-sizing:border-box">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="font-size:0.8rem;color:var(--text-light);margin:0.25rem 0 1rem;line-height:1.5">
                        <strong>ℹ&#xFE0E; Hinweis:</strong> Alle Paketpreise verstehen sich <strong>netto</strong>;
                        auf der Rechnung kommen 19&nbsp;% USt oben drauf (z.&nbsp;B. Gold 1.000&nbsp;€ →
                        1.190&nbsp;€ brutto). Braucht ein einzelner Sponsor eine Brutto-Abrechnung, gibt es
                        dafür einen Haken in der Sponsor-Maske (Karte „Rechnung / Leistung").
                    </p>

                    <div class="brief-actions">
                        <button type="submit" class="btn btn-secondary">Einstellungen speichern</button>
                    </div>
                </div>
            </form>

        </main>
    </div>
    <script>
    (function() {
        const csrf = <?= json_encode($csrfToken) ?>;
        const defaultText = <?= json_encode($default['koerper_md']) ?>;
        const defaultBetreff = <?= json_encode($default['betreff']) ?>;
        const ta = document.getElementById('koerper_md');
        const betreff = document.getElementById('betreff');
        const frame = document.getElementById('preview-frame');
        let timer = null;

        function renderPreview() {
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('koerper_md', ta.value);
            body.set('slug', <?= json_encode($slug) ?>);
            fetch('api/sponsor_brief_preview.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            })
                .then(function(r) { return r.text(); })
                .then(function(html) { frame.srcdoc = html; })
                .catch(function() { /* Vorschau optional */ });
        }
        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(renderPreview, 400);
        }
        ta.addEventListener('input', schedule);
        renderPreview();

        // Platzhalter an Cursorposition einfügen
        document.querySelectorAll('.ph-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                const ph = chip.dataset.ph;
                const start = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.slice(0, start) + ph + ta.value.slice(end);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = start + ph.length;
                schedule();
            });
        });

        <?php if ($hasStandardtext): ?>
        // Standard-Reset. Bei der Bestätigung überschreibt der Abschnitts-Block unten
        // window.resetBriefDefault, damit der Body aus den angehakten Abschnitten neu entsteht.
        window.resetBriefDefault = function() {
            ta.value = defaultText;
            betreff.value = defaultBetreff;
            renderPreview();
        };
        document.getElementById('reset-default').addEventListener('click', function() {
            if (!confirm('Text und Betreff auf die Standardvorlage zurücksetzen? Ungespeicherte Änderungen gehen verloren.')) {
                return;
            }
            window.resetBriefDefault();
        });
        <?php endif; ?>

        <?php if ($isUserScoped): ?>
        document.getElementById('btn-save').addEventListener('click', function() {
            const statusEl = document.getElementById('draft-status');
            statusEl.textContent = 'Speichert…';
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('vorlage_art', 'sponsor');
            body.set('slug', <?= json_encode($slug) ?>);
            body.set('betreff', betreff.value);
            body.set('koerper_md', ta.value);
            fetch('api/draft_save.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    var m = (data.gespeichert_am || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/);
                    var ts = m ? m[3] + '.' + m[2] + '.' + m[1] + ', ' + m[4] + ':' + m[5] + ' Uhr' : '';
                    statusEl.textContent = 'Gespeichert am ' + ts;
                } else {
                    statusEl.textContent = 'Fehler: ' + (data.error || 'Unbekannt');
                }
            })
            .catch(function() { statusEl.textContent = 'Speichern fehlgeschlagen.'; });
        });
        <?php endif; ?>

        // Vorschau-Höhe beim manuellen Resize der Textarea nachziehen (max 700px)
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(function(entries) {
                for (var entry of entries) {
                    frame.style.height = Math.min(entry.target.offsetHeight, 700) + 'px';
                }
            }).observe(ta);
        }

        // Preview-Toggle
        var previewToggle = document.getElementById('preview-toggle');
        var briefSplit = document.getElementById('brief-split');
        if (previewToggle && briefSplit) {
            previewToggle.addEventListener('click', function() {
                var hidden = briefSplit.classList.toggle('preview-hidden');
                previewToggle.textContent = hidden ? 'Vorschau einblenden' : 'Vorschau ausblenden';
            });
        }
    })();

    <?php if ($slug === 'bestaetigung'): ?>
    (function() {
        const INTRO = <?= json_encode(
            "{{anrede}}\n\nherzlichen Dank, dass Sie den Marktlauf Kirchseeon am **{{event_datum}}** als **{{paket_text}}** unterstützen. Wir freuen uns sehr über Ihre Zusage und die Zusammenarbeit!\n\nDamit wir Ihren Markenauftritt optimal vorbereiten können, bitten wir Sie, uns folgende Unterlagen und Informationen zukommen zu lassen:",
            JSON_UNESCAPED_UNICODE
        ) ?>;
        const OUTRO = <?= json_encode(
            "Sollte Ihnen etwas fehlen oder Sie noch Fragen haben, kommen Sie jederzeit gerne auf mich zu.\n\nVielen Dank für Ihre Unterstützung und Ihr Vertrauen – gemeinsam machen wir den Marktlauf Kirchseeon zu einem unvergesslichen Erlebnis!\n\n{{signatur}}",
            JSON_UNESCAPED_UNICODE
        ) ?>;
        // Ursprungstexte der Abschnitte (Code-Default) — Fallback für „Standardtext wiederherstellen".
        const SECTION_ORIGINS = <?= json_encode(array_column(sponsorBestaetigungSektionen(), 'text', 'id'), JSON_UNESCAPED_UNICODE) ?>;
        const DEFAULT_BETREFF = <?= json_encode($default['betreff'], JSON_UNESCAPED_UNICODE) ?>;

        const ta = document.getElementById('koerper_md');
        const btn = document.getElementById('btn-zusammenstellen');
        if (!btn || !ta) return;

        // Body aus INTRO + angehakten Abschnitten + OUTRO bauen. Schreibt NUR hier bzw. beim
        // Reset in den Markdown — Tippen/Haken/Schließen im Panel lässt den Body bewusst unangetastet.
        function composeBody() {
            var parts = [INTRO];
            document.querySelectorAll('.baustein-cb').forEach(function(cb) {
                if (!cb.checked) return;
                var textArea = document.querySelector('.baustein-text[data-id="' + cb.dataset.id + '"]');
                if (textArea && textArea.value.trim() !== '') parts.push(textArea.value);
            });
            parts.push(OUTRO);
            ta.value = parts.join('\n\n');
            ta.dispatchEvent(new Event('input'));
        }

        // „Standardtext wiederherstellen" bei der Bestätigung: Abschnitts-Texte zurück auf den
        // Ursprung (Fallback), die aktuelle Haken-Auswahl bleibt, dann Body aus den angehakten
        // Abschnitten neu zusammenstellen. Abgewählte Abschnitte bleiben also draußen.
        window.resetBriefDefault = function() {
            document.querySelectorAll('.baustein-text').forEach(function(t) {
                var id = t.dataset.id;
                if (id in SECTION_ORIGINS) {
                    t.value = SECTION_ORIGINS[id];
                    t.dispatchEvent(new Event('input')); // persistiert den Ursprung in localStorage
                }
            });
            var betreffEl = document.getElementById('betreff');
            if (betreffEl) betreffEl.value = DEFAULT_BETREFF;
            composeBody();
        };

        // Totaler Fallback („Alles auf Original zurücksetzen"): unabhängig von Auswahl und
        // Bearbeitung den kompletten Original-Brief wiederherstellen — alle Abschnitte anhaken,
        // alle Texte + Betreff auf das feste Code-Original, Body neu bauen. Die Notbremse, wenn
        // jemand seinen Stand zerlegt hat.
        var resetOriginalBtn = document.getElementById('reset-original');
        if (resetOriginalBtn) {
            resetOriginalBtn.addEventListener('click', function() {
                if (!confirm('Wirklich ALLES verwerfen und den kompletten Original-Brief wiederherstellen?\n\nAlle Abschnitte werden wieder angehakt, alle Texte und der Betreff auf den Originalstand gesetzt.')) {
                    return;
                }
                document.querySelectorAll('.baustein-cb').forEach(function(cb) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change')); // persistiert die Auswahl in localStorage
                });
                document.querySelectorAll('.baustein-text').forEach(function(t) {
                    var id = t.dataset.id;
                    if (id in SECTION_ORIGINS) {
                        t.value = SECTION_ORIGINS[id];
                        t.dispatchEvent(new Event('input')); // persistiert den Ursprung in localStorage
                    }
                });
                var betreffEl = document.getElementById('betreff');
                if (betreffEl) betreffEl.value = DEFAULT_BETREFF;
                composeBody();
            });
        }

        // Klappmenü: Text ▾ / ▴ toggle
        document.querySelectorAll('.baustein-expand-btn').forEach(function(expandBtn) {
            expandBtn.addEventListener('click', function() {
                var id = expandBtn.dataset.id;
                var body = document.getElementById('baustein-body-' + id);
                if (!body) return;
                var open = body.hidden;
                body.hidden = !open;
                expandBtn.textContent = open ? 'Text ▴' : 'Text ▾';
            });
        });

        // Zusammenstellen: liest Text aus den editierbaren Baustein-Textareas
        btn.addEventListener('click', function() {
            if (ta.value.trim() !== '' && !confirm('Den aktuellen Text überschreiben und neu zusammenstellen?')) {
                return;
            }
            composeBody();
        });
    })();

    // localStorage-Persistenz für Abschnitt-Zustände (Checkboxen + Texte)
    (function() {
        var STORAGE_KEY = 'mkl_baustein_bestaetigung';

        function saveBausteinState() {
            var state = {};
            document.querySelectorAll('.baustein-cb').forEach(function(cb) {
                var id = cb.dataset.id;
                var textEl = document.querySelector('.baustein-text[data-id="' + id + '"]');
                state[id] = { checked: cb.checked, text: textEl ? textEl.value : '' };
            });
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch(e) {}
        }

        try {
            var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            document.querySelectorAll('.baustein-cb').forEach(function(cb) {
                var id = cb.dataset.id;
                if (id in saved) {
                    cb.checked = saved[id].checked;
                    var textEl = document.querySelector('.baustein-text[data-id="' + id + '"]');
                    if (textEl && saved[id].text) textEl.value = saved[id].text;
                }
            });
        } catch(e) {}

        document.querySelectorAll('.baustein-cb').forEach(function(cb) {
            cb.addEventListener('change', saveBausteinState);
        });
        document.querySelectorAll('.baustein-text').forEach(function(t) {
            t.addEventListener('input', saveBausteinState);
        });
    })();

    // Anhang-Abwahl (Plakate + Bestätigungs-Assets): browser-seitig, pro Person, bis zum
    // nächsten Versand. Gespeichert werden nur die ABGEWÄHLTEN Drive-IDs je Gruppe.
    // Derselbe Speicher-Schlüssel wird auf der Versandseite gelesen und dort nach erfolgreichem
    // Versand geleert (dann sind wieder alle Anhänge dabei).
    (function() {
        var KEY = 'mkl_anhang_abwahl';
        function load() {
            try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); return { plakat: s.plakat || [], asset: s.asset || [] }; }
            catch(e) { return { plakat: [], asset: [] }; }
        }
        function save(s) { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch(e) {} }
        var boxes = document.querySelectorAll('.anhang-abwahl');
        if (!boxes.length) return;
        var state = load();
        boxes.forEach(function(cb) {
            var g = cb.dataset.group;
            cb.checked = (state[g] || []).indexOf(cb.value) === -1;
            cb.addEventListener('change', function() {
                var s = load();
                var arr = s[g] || [];
                var i = arr.indexOf(cb.value);
                if (cb.checked) { if (i !== -1) arr.splice(i, 1); }
                else if (i === -1) { arr.push(cb.value); }
                s[g] = arr;
                save(s);
            });
        });
    })();
    <?php endif; ?>

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
