<?php
/**
 * Social-Media-Orchestrator: KI-Nachbericht aus Raceresult-Ergebnissen.
 * Phase 1: Mock-Daten, manueller Dispatch (Copy/Download).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/llm_client.php';
require_once __DIR__ . '/../src/raceresult_client.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();

$pdo      = getDbConnection();
$provider = llmActiveProvider($pdo);

// Strava-Club-URL (global, siehe Einstellungen) — hier entsteht der Renn-Content,
// der seinen Weg zu Strava findet, deshalb ein Absprung-Button im Kopf.
$stravaUrl = '';
try {
    $stravaStmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'strava_url'");
    $stravaStmt->execute();
    $stravaUrl = $stravaStmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Tabelle evtl. noch nicht vorhanden
}

// Mock-Daten für JS-Share-Card
$mockData    = raceResultData($pdo);
$mockDataJson = json_encode($mockData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

// Letzten gespeicherten Entwurf laden (neuester draft/approved)
$last = $pdo->query(
    'SELECT id, llm_text_article, llm_text_social, status, llm_provider, created_at
       FROM post_race_contents
      ORDER BY id DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

// Vereinsweite Einstellungen der Social-Seite laden
$socialMerkfeld = '';
$socialHashtags = '';
$raceresultApiUrl = '';
$socialContent  = []; // {anlass: {prompt, fakten}}
try {
    $stmt = $pdo->query(
        "SELECT `key`, `value` FROM einstellungen
          WHERE `key` IN ('social_merkfeld', 'social_hashtags', 'raceresult_api_url', 'social_prompts')"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if ($k === 'social_merkfeld')    { $socialMerkfeld   = (string) ($v ?? ''); }
        if ($k === 'social_hashtags')    { $socialHashtags   = (string) ($v ?? ''); }
        if ($k === 'raceresult_api_url') { $raceresultApiUrl = (string) ($v ?? ''); }
        if ($k === 'social_prompts') {
            $decoded = json_decode((string) ($v ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $anlassKey => $entry) {
                    if (is_string($entry)) {
                        $socialContent[$anlassKey] = ['prompt' => $entry, 'fakten' => ''];
                    } elseif (is_array($entry)) {
                        $socialContent[$anlassKey] = [
                            'prompt' => (string) ($entry['prompt'] ?? ''),
                            'fakten' => (string) ($entry['fakten'] ?? ''),
                        ];
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    // Tabelle/Spalten existieren evtl. noch nicht
}
$raceresultConfigured = $raceresultApiUrl !== '';

// Repo-Assets (Logos/Marken) für die Grafik — rekursiver Scan über assets/images.
// Statische Dateien → Vorschau lädt zuverlässig (kein Auth-Endpoint). SVG bewusst
// ausgeklammert (html2canvas rastert SVG unzuverlässig).
$repoAssets = [];
$assetsRoot = realpath(__DIR__ . '/../assets/images');
if ($assetsRoot !== false && is_dir($assetsRoot)) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($assetsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $file) {
        if (!$file->isFile()) { continue; }
        if (!in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp'], true)) { continue; }
        $rel = ltrim(str_replace($assetsRoot, '', $file->getPathname()), '/\\');
        $rel = str_replace('\\', '/', $rel);
        $url = '../assets/images/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
        $repoAssets[] = ['label' => $rel, 'url' => $url];
    }
    usort($repoAssets, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Social Media | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Kopf: Titel links, Notiz + Meta-Business-Button rechts oben */
        .so-header-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 1.5rem; flex-wrap: wrap;
        }
        .so-header-tools { display: flex; align-items: stretch; gap: 0.75rem; }
        .so-merk-card textarea {
            width: 300px; height: 100%; box-sizing: border-box;
            font-family: inherit; font-size: 0.82rem; line-height: 1.4;
            padding: 0.5rem 0.6rem; border: 1px solid var(--border); border-radius: 6px;
            resize: none; overflow: hidden; text-align: right;
        }
        .so-merk-card.locked textarea { background: #f6f6f4; color: var(--text); cursor: default; }
        .so-fb-btn { display: inline-flex; align-items: center; white-space: nowrap; }
        @media (max-width: 720px) {
            .so-header-tools { width: 100%; }
            .so-merk-card { flex: 1 1 auto; }
            .so-merk-card textarea { width: 100%; }
        }

        .so-card {
            background: var(--white); border-radius: 8px;
            box-shadow: var(--shadow-card);
            padding: 1.5rem; margin-bottom: 1.25rem;
        }
        .so-card h2 { font-size: 1rem; margin: 0 0 1rem; }
        .so-provider-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .so-provider-row label { font-size: 0.9rem; color: var(--text-light); }
        .so-provider-row select {
            padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px;
            font-size: 0.9rem; background: var(--white);
        }
        .so-textareas { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 860px) { .so-textareas { grid-template-columns: 1fr; } }
        .so-textareas label { display: block; font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.4rem; }
        .so-textareas textarea {
            width: 100%; min-height: 260px; padding: 0.75rem;
            border: 1px solid var(--border); border-radius: 6px;
            font-size: 0.9rem; line-height: 1.55; resize: vertical; box-sizing: border-box;
            font-family: inherit;
        }
        .so-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin-top: 1rem; }
        .so-status {
            font-size: 0.8rem; padding: 0.25rem 0.6rem; border-radius: 4px;
            background: var(--bg); border: 1px solid var(--border);
        }
        .so-status.approved { background: #d1fae5; border-color: #6ee7b7; color: #065f46; }
        .so-spinner { display: none; }
        .so-spinner.active { display: inline-block; }
        .so-notice {
            font-size: 0.82rem; color: var(--text-light); padding: 0.6rem 0.9rem;
            background: var(--bg); border-radius: 6px; border-left: 3px solid var(--border);
        }
        #so-error { display: none; color: #dc2626; font-size: 0.88rem; margin-top: 0.5rem; }
        #so-saved-msg { display: none; color: #16a34a; font-size: 0.88rem; }

        /* Eingabefelder (Anlass, Prompt, Stichpunkte, Hashtags, RR-URL, Newsletter) */
        .so-field { margin-bottom: 0.9rem; }
        .so-field label { display: block; font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.35rem; }
        .so-field input[type="text"], .so-field input[type="url"], .so-field textarea, .so-field select {
            width: 100%; padding: 0.5rem 0.6rem; border: 1px solid var(--border);
            border-radius: 6px; font-size: 0.9rem; font-family: inherit; box-sizing: border-box; background: var(--white);
        }
        .so-field textarea { resize: vertical; min-height: 70px; line-height: 1.5; }
        .so-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 860px) { .so-grid2 { grid-template-columns: 1fr; } }
        .so-save-row { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
        .so-saved { display: none; color: #16a34a; font-size: 0.8rem; }
        /* Einklappbares RaceResult-Modul */
        .so-collapse { border: 1px solid var(--border); border-radius: 8px; background: var(--white); box-shadow: var(--shadow-card); padding: 0.75rem 1.25rem; margin-bottom: 1.25rem; }
        .so-collapse > summary { cursor: pointer; font-size: 1rem; font-weight: 600; padding: 0.4rem 0; list-style: revert; }
        .so-collapse[open] > summary { margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
        .so-badge-ok { font-size: 0.72rem; font-weight: 500; padding: 0.1rem 0.5rem; border-radius: 10px; background: #d1fae5; color: #065f46; }
        .so-badge-off { font-size: 0.72rem; font-weight: 500; padding: 0.1rem 0.5rem; border-radius: 10px; background: var(--bg); color: var(--text-light); border: 1px solid var(--border); }
        /* Spickzettel */
        .so-guide { font-size: 0.86rem; line-height: 1.55; }
        .so-guide h3 { font-size: 0.9rem; margin: 0.9rem 0 0.3rem; }
        .so-guide ul { margin: 0 0 0.5rem 1.1rem; padding: 0; }
        .so-guide code { background: var(--bg); padding: 0.05rem 0.3rem; border-radius: 3px; }
        /* Newsletter */
        .so-nl-preview { width: 100%; height: 420px; border: 1px solid var(--border); border-radius: 6px; background: #fff; }
        .so-subjects { list-style: none; padding: 0; margin: 0.5rem 0 0; display: flex; flex-direction: column; gap: 0.4rem; }
        .so-subjects li { display: flex; gap: 0.5rem; align-items: center; font-size: 0.9rem; }
        #so-nl-error, #so-rr-msg, #so-ht-msg { font-size: 0.82rem; margin-top: 0.4rem; }
        #so-nl-error { display: none; color: #dc2626; }

        /* Facebook-blauer Meta-Business-Button */
        .so-mba-btn { background: #1877F2; color: #fff; }
        .so-mba-btn:hover { background: #1461c9; color: #fff; }
        /* Eingabe-Layout Modul 1: Anlass + Hashtags links, Fakten rechts auf gleicher Höhe */
        .so-input-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem;
            grid-template-areas: "anlass fakten" "hashtags fakten";
        }
        #so-anlass-field   { grid-area: anlass; }
        #so-hashtags-field { grid-area: hashtags; }
        #so-fakten-field   { grid-area: fakten; display: flex; flex-direction: column; }
        #so-fakten-field textarea { flex: 1 1 auto; min-height: 120px; }
        @media (max-width: 860px) {
            .so-input-grid { grid-template-columns: 1fr; grid-template-areas: "anlass" "hashtags" "fakten"; }
        }
        /* Werkzeuge in der Posting-Anleitung (Button + Notiz) */
        .so-guide-tools { display: flex; gap: 0.75rem; align-items: stretch; flex-wrap: wrap; margin-bottom: 1rem; }
        .so-guide-tools .so-merk-card { flex: 1 1 240px; }
        .so-guide-tools .so-merk-card textarea { width: 100%; }
        /* Grafik-Steuerung Modul 3 */
        .so-card-controls {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem 1rem; margin-top: 0.75rem;
        }
        .so-card-controls .so-field { margin-bottom: 0; }
        .so-photo-row { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-top: 0.9rem; }
        .so-photo-picker {
            margin-top: 0.75rem; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 6px; background: var(--bg);
            display: flex; gap: 0.6rem; flex-wrap: wrap; max-height: 260px; overflow-y: auto;
        }
        .so-thumb {
            width: 92px; cursor: pointer; border: 2px solid transparent; border-radius: 6px;
            background: var(--white); padding: 3px; text-align: center;
        }
        .so-thumb:hover { border-color: var(--primary); }
        .so-thumb img { width: 100%; height: 68px; object-fit: cover; border-radius: 4px; display: block; }
        .so-thumb span { font-size: 0.66rem; color: var(--text-light); display: block; margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .so-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; margin: 0.5rem 0; }
        .so-chip { display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg);
            border: 1px solid var(--border); border-radius: 12px; padding: 0.15rem 0.2rem 0.15rem 0.6rem; font-size: 0.78rem; }
        .so-chip button { border: none; background: none; cursor: pointer; color: #b91c1c; font-size: 0.9rem; line-height: 1; padding: 0 0.3rem; }

        /* Die eigentliche Render-Card (off-screen, echte Pixelgröße) — Höhe wird
           je Format per JS gesetzt (1080×1080 / 1080×1350 / 1080×1920). */
        #social-share-card {
            width: 1080px; height: 1080px;
            background: #007230; /* Fallback; sichtbare Fläche liefern Overlay/Foto */
            display: flex; flex-direction: column;
            justify-content: space-between; padding: 80px;
            box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #ffffff; position: relative; overflow: hidden;
        }
        /* Hintergrund-Foto (Vollfläche) + Farb-Overlay darüber; Inhalt darüber */
        .sc-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .sc-overlay { position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(145deg, #009640 0%, #007230 100%); }
        #social-share-card > *:not(.sc-bg):not(.sc-overlay) { position: relative; z-index: 2; }
        .sc-logos { display: flex; flex-wrap: wrap; gap: 40px; align-items: center; margin-bottom: 24px; }
        .sc-logos img { height: 96px; width: auto; max-width: 340px; object-fit: contain; }
        .sc-event { font-size: 28px; font-weight: 400; opacity: 0.85; margin-bottom: 16px; }
        .sc-headline { font-size: 72px; font-weight: 700; line-height: 1.1; margin-bottom: 0; }
        .sc-metrics { display: flex; flex-direction: column; gap: 36px; }
        .sc-metric-row { display: flex; gap: 60px; }
        .sc-metric { display: flex; flex-direction: column; }
        .sc-metric-label { font-size: 22px; opacity: 0.75; margin-bottom: 6px; }
        .sc-metric-value { font-size: 48px; font-weight: 700; line-height: 1; }
        .sc-metric-sub { font-size: 26px; opacity: 0.85; margin-top: 4px; }
        .sc-highlight {
            font-size: 26px; opacity: 0.9; background: rgba(255,255,255,0.12);
            border-radius: 12px; padding: 24px 32px; line-height: 1.4;
        }
        .sc-footer { display: flex; justify-content: space-between; align-items: flex-end; }
        .sc-url { font-size: 22px; opacity: 0.6; }
        .sc-wordmark { font-size: 32px; font-weight: 700; letter-spacing: 1px; }
        #so-card-preview { display: none; margin-top: 1rem; }
        #so-card-preview img { max-width: 360px; border-radius: 8px; border: 1px solid var(--border); }
        #so-card-error { display: none; color: #dc2626; font-size: 0.88rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
<?php $activeNav = 'social_media'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Social Media</h1>
            <p class="content-subtitle">Zentrale KI-gestützte Content-Produktion für Instagram, Facebook &amp; Newsletter</p>
            <?php if ($stravaUrl): ?>
            <ul class="quick-links" style="margin-top:0.75rem;padding:0;">
                <li style="border:none;padding:0;"><a href="<?= htmlspecialchars($stravaUrl) ?>" target="_blank" rel="noopener" class="btn-brand btn-brand-strava">Strava öffnen</a></li>
            </ul>
            <?php endif; ?>
        </header>

        <!-- Modul 1: Inhalt generieren (allgemein) -->
        <div class="so-card">
            <h2>1 · Inhalt generieren</h2>
            <div class="so-provider-row">
                <label for="so-provider">KI-Anbieter:</label>
                <select id="so-provider">
                    <option value="gemini"  <?= $provider === 'gemini'  ? 'selected' : '' ?>>Google Gemini (Free)</option>
                    <option value="mistral" <?= $provider === 'mistral' ? 'selected' : '' ?>>Mistral Small</option>
                </select>
                <span id="so-provider-saved" style="display:none;font-size:.8rem;color:#16a34a">✓ gespeichert</span>
            </div>

            <div class="so-input-grid">
                <div class="so-field" id="so-anlass-field">
                    <label for="so-anlass">Anlass / Thema</label>
                    <select id="so-anlass">
                        <option value="allgemein">Allgemeiner Beitrag</option>
                        <option value="ankuendigung">Ankündigung des Events</option>
                        <option value="countdown">Countdown / Vorfreude</option>
                        <option value="sponsoren_dank">Dank an Sponsoren &amp; Partner</option>
                        <option value="helfer">Helfer-Aufruf / -Dank</option>
                        <option value="renntag">Renntag-Nachbericht (nutzt RaceResult-Daten)</option>
                    </select>
                </div>
                <div class="so-field" id="so-hashtags-field">
                    <label for="so-hashtags">Standard-Hashtags <span style="font-weight:400">(werden an den Social-Post gehängt)</span></label>
                    <textarea id="so-hashtags" rows="2" placeholder="#marktlauf #kirchseeon #atsv"><?= htmlspecialchars($socialHashtags) ?></textarea>
                    <div class="so-save-row" style="margin-top:0.4rem">
                        <button class="btn btn-small btn-secondary" id="so-save-hashtags">Hashtags speichern</button>
                        <span class="so-saved" id="so-ht-msg">Gespeichert</span>
                    </div>
                </div>
                <div class="so-field" id="so-fakten-field">
                    <label for="so-stichpunkte">Fakten / Stichpunkte <span style="font-weight:400">(pro Anlass gespeichert)</span> <span class="so-saved" id="so-ft-msg">✓ gespeichert</span></label>
                    <textarea id="so-stichpunkte" placeholder="z. B. Datum, Uhrzeit, Distanzen, Anmeldeschluss, Besonderheiten …"></textarea>
                </div>
            </div>
            <div class="so-field">
                <label for="so-prompt">Eigener Prompt / zusätzliche Anweisung <span style="font-weight:400">(pro Anlass speicherbar, optional)</span></label>
                <textarea id="so-prompt" placeholder="z. B. „locker und jugendlich formulieren, max. 3 Sätze, Frage am Ende“"></textarea>
                <div class="so-save-row" style="margin-top:0.4rem">
                    <button class="btn btn-small btn-secondary" id="so-save-prompt">Prompt für diesen Anlass speichern</button>
                    <span class="so-saved" id="so-pr-msg">Gespeichert</span>
                </div>
            </div>

            <div class="so-actions">
                <button class="btn btn-primary" id="so-generate-btn">Entwürfe generieren</button>
                <span class="so-spinner active" id="so-spinner" style="display:none">⏳ KI läuft …</span>
            </div>
            <div id="so-error"></div>

            <div class="so-textareas" style="margin-top:1.25rem">
                <div>
                    <label for="so-social">Social-Media-Post (Instagram / Facebook)</label>
                    <textarea id="so-social" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?=
                        htmlspecialchars($last['llm_text_social'] ?? '')
                    ?></textarea>
                </div>
                <div>
                    <label for="so-article">Presse-Artikel (Lokalzeitung)</label>
                    <textarea id="so-article" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?=
                        htmlspecialchars($last['llm_text_article'] ?? '')
                    ?></textarea>
                </div>
            </div>
            <div class="so-actions">
                <button class="btn btn-secondary" id="so-save-draft">Als Entwurf speichern</button>
                <button class="btn btn-primary"    id="so-save-approved">Freigeben</button>
                <?php if ($last): ?>
                <span class="so-status <?= $last['status'] === 'approved' ? 'approved' : '' ?>">
                    <?= $last['status'] === 'approved' ? '✓ Freigegeben' : 'Entwurf' ?>
                    (<?= htmlspecialchars(date('d.m.Y H:i', strtotime($last['created_at']))) ?>)
                </span>
                <?php endif; ?>
                <span id="so-saved-msg">Gespeichert.</span>
            </div>
        </div>

        <!-- Modul 2: Zusatzquelle Renntag (RaceResult) -->
        <details class="so-collapse" <?= $raceresultConfigured ? '' : 'open' ?>>
            <summary>2 · Zusatzquelle Renntag · RaceResult
                <?php if ($raceresultConfigured): ?><span class="so-badge-ok">Link hinterlegt</span><?php else: ?><span class="so-badge-off">kein Link</span><?php endif; ?>
            </summary>
            <p class="so-notice" style="margin-bottom:0.9rem">
                <strong>Wofür:</strong> optionale Datenquelle für den Anlass „Renntag-Nachbericht" (Modul 1).
                Ist ein SimpleAPI-Link hinterlegt, liest das Dashboard daraus beim Generieren die Ergebnisse
                (Sieger:innen, Zeiten, Teilnehmerzahlen) und füttert damit die KI-Texte <em>und</em> die
                Ergebnis-Grafik (Modul 3). Die Daten werden dabei <strong>nur live gelesen, nicht gespeichert</strong>.
                Ohne Link oder solange das Event (Testmodus) keine Daten liefert, werden automatisch Beispiel-Daten verwendet.
                <br><em>„Link hinterlegt" bedeutet nur: eine URL ist gespeichert — nicht, dass sie bereits Daten liefert (das lässt sich erst mit echten Renndaten prüfen).</em>
            </p>
            <div class="so-field" style="margin-bottom:0">
                <label for="so-rr-url">RaceResult SimpleAPI-Link — in RaceResult unter „Zugriffsrechte/Freigabe → Freigabe (SimpleAPI)", Typ „Liste" anlegen und den erzeugten Link hier einfügen</label>
                <div class="so-save-row">
                    <input type="url" id="so-rr-url" value="<?= htmlspecialchars($raceresultApiUrl) ?>" placeholder="https://my.raceresult.com/377952/RRPublish/data/list?..." style="flex:1 1 320px">
                    <button class="btn btn-small btn-secondary" id="so-save-rr">Speichern</button>
                    <span class="so-saved" id="so-rr-msg">Gespeichert</span>
                </div>
            </div>
        </details>

        <!-- Share-Card: versteckter Render-Div (off-layout, aber im DOM). Höhe unbegrenzt,
             damit höhere Formate (Portrait/Story) nicht abgeschnitten werden. -->
        <div style="position:absolute;left:-9999px;top:0;width:1080px;overflow:visible" aria-hidden="true">
            <div id="social-share-card">
                <img class="sc-bg" id="sc-bg" alt="" style="display:none">
                <div class="sc-overlay" id="sc-overlay"></div>
                <div>
                    <div class="sc-event" id="sc-event">Marktlauf Kirchseeon · 20. September 2026</div>
                    <div class="sc-headline">Danke &amp; Glückwunsch!</div>
                </div>
                <div class="sc-metrics">
                    <div class="sc-metric-row">
                        <div class="sc-metric">
                            <span class="sc-metric-label">Sieger 10 km</span>
                            <span class="sc-metric-value" id="sc-sieger10">–</span>
                            <span class="sc-metric-sub" id="sc-sieger10-zeit"></span>
                        </div>
                        <div class="sc-metric">
                            <span class="sc-metric-label">Siegerin 10 km</span>
                            <span class="sc-metric-value" id="sc-siegerin10">–</span>
                            <span class="sc-metric-sub" id="sc-siegerin10-zeit"></span>
                        </div>
                    </div>
                    <div class="sc-metric-row">
                        <div class="sc-metric">
                            <span class="sc-metric-label">Teilnehmer</span>
                            <span class="sc-metric-value" id="sc-tn">–</span>
                        </div>
                        <div class="sc-metric">
                            <span class="sc-metric-label">Finisher</span>
                            <span class="sc-metric-value" id="sc-finisher">–</span>
                        </div>
                    </div>
                </div>
                <div class="sc-highlight" id="sc-highlight"></div>
                <div class="sc-logos" id="sc-logos"></div>
                <div class="sc-footer">
                    <span class="sc-url">atsv-kirchseeon-marktlauf.de</span>
                    <span class="sc-wordmark">ATSV Kirchseeon</span>
                </div>
            </div>
        </div>

        <!-- Modul 3: Grafik & Formate -->
        <div class="so-card">
            <h2>3 · Grafik &amp; Formate</h2>
            <p class="so-notice">
                PNG-Grafik mit Ergebnis-Highlights in drei Formaten: <strong>Quadratisch 1080×1080</strong> (Feed),
                <strong>Portrait 1080×1350</strong> (Instagram-Feed, füllt am meisten) und <strong>Story 1080×1920</strong>.
            </p>
            <div class="so-card-controls">
                <div class="so-field">
                    <label for="so-card-format">Format</label>
                    <select id="so-card-format">
                        <option value="portrait">Portrait 1080×1350 (Feed)</option>
                        <option value="square">Quadratisch 1080×1080</option>
                        <option value="story">Story 1080×1920</option>
                    </select>
                </div>
                <div class="so-field">
                    <label for="so-card-layout">Anordnung</label>
                    <select id="so-card-layout">
                        <option value="freiraum">Oben gebündelt + Freiraum</option>
                        <option value="zentriert">Vertikal zentriert</option>
                        <option value="verteilt">Verteilt (Poster)</option>
                    </select>
                </div>
                <div class="so-field">
                    <label for="so-card-scheme">Farb-Schema</label>
                    <select id="so-card-scheme">
                        <option value="gruen">Grün (Marke)</option>
                        <option value="dunkel">Dunkelgrün</option>
                        <option value="akzent">Akzent (Orange)</option>
                    </select>
                </div>
            </div>
            <div class="so-photo-row">
                <span style="font-size:0.85rem;color:var(--text-light)">Logos (Repo-Assets, mehrere möglich):</span>
                <button class="btn btn-small btn-secondary" id="so-pick-logo">Logo hinzufügen</button>
                <button class="btn btn-small btn-secondary" id="so-reset-logo">nur ATSV</button>
            </div>
            <div id="so-logo-chips" class="so-chips"></div>
            <div class="so-photo-picker" id="so-logo-picker" style="display:none"></div>
            <div class="so-photo-row">
                <span style="font-size:0.85rem;color:var(--text-light)">Hintergrundfoto:</span>
                <button class="btn btn-small btn-secondary" id="so-pick-photo">Aus Datei-Ablage wählen</button>
                <span id="so-photo-name" style="font-size:0.82rem;color:var(--text-light)">kein Foto</span>
                <button class="btn btn-small btn-secondary" id="so-clear-photo" style="display:none">entfernen</button>
            </div>
            <p style="font-size:0.8rem;color:var(--text-light);margin:0.3rem 0 0">
                Fotos (z. B. Siegerehrung) zuerst in der <a href="dateien.php?tab=orga&amp;kat=presse" style="color:var(--primary)">Datei-Ablage</a>
                unter der Kategorie <strong>„Social-Media Bilder"</strong> hochladen — sie erscheinen dann hier zur Auswahl.
            </p>
            <div class="so-photo-picker" id="so-photo-picker" style="display:none"></div>
            <div class="so-actions" style="margin-top:0.75rem">
                <button class="btn btn-secondary" id="so-render-card">Grafik erzeugen</button>
                <button class="btn btn-secondary" id="so-download-card" style="display:none">PNG herunterladen</button>
            </div>
            <div id="so-card-error"></div>
            <div id="so-card-preview">
                <p style="font-size:.82rem;color:var(--text-light);margin:0.5rem 0 0.4rem" id="so-card-caption">Vorschau:</p>
                <img id="so-card-img" src="" alt="Share-Card Vorschau">
            </div>

            <details class="so-collapse" style="margin-top:1rem">
                <summary>⚠️ Anleitung zum Posten im Meta Business Account</summary>
                <div class="so-guide">
                    <div class="so-guide-tools">
                        <a class="btn so-mba-btn" href="https://business.facebook.com/latest/home?nav_ref=bm_home_redirect&amp;asset_id=1236742862857199" target="_blank" rel="noopener noreferrer">Meta Business Account öffnen ↗</a>
                        <div class="so-merk-card" id="so-merk-wrap2">
                            <textarea class="so-merk-text" rows="1" data-csrf="<?= htmlspecialchars($csrf) ?>"
                                      placeholder="Notiz … (Doppelklick sperrt &amp; speichert)"><?= htmlspecialchars($socialMerkfeld) ?></textarea>
                        </div>
                    </div>

                    <h3>1 · Formate (Bildgröße)</h3>
                    <ul>
                        <li><strong>Portrait 1080×1350 (4:5)</strong> — empfohlen fürs Instagram-Feed, füllt am meisten Platz.</li>
                        <li><strong>Quadratisch 1080×1080 (1:1)</strong> — sicher überall (Feed IG + FB).</li>
                        <li><strong>Story 1080×1920 (9:16)</strong> — für Instagram-/Facebook-Stories.</li>
                        <li>Das Instagram-Profil-Grid schneidet neuerdings auf <strong>3:4</strong> — Logo/Text/Gesichter mittig halten.</li>
                        <li><strong>PNG</strong> für Text/Grafik (scharf), <strong>JPG</strong> für Fotos (kleiner).</li>
                    </ul>

                    <h3>2 · Posten via Meta Business Suite</h3>
                    <ul>
                        <li>Grafik herunterladen (Modul 3) → oben „Meta Business Account öffnen" → „Beiträge &amp; Reels" → Beitrag erstellen.</li>
                        <li>Kanäle Instagram + Facebook anhaken → Grafik als Foto hochladen → Caption (Social-Post aus Modul 1) einfügen → Vorschau → veröffentlichen oder terminieren.</li>
                        <li>Für eine <strong>Story</strong>: im Composer „Story" wählen → Story-Grafik hochladen.</li>
                    </ul>

                    <h3>3 · Links — je Kanal unterschiedlich (Stand 2026)</h3>
                    <ul>
                        <li><strong>Instagram-Feed:</strong> für normale Accounts <em>kein</em> klickbarer Link in der Caption → Domain aufs Bild + „Link in Bio". (Klickbare Caption-Links testet Meta nur für Meta-Verified-Creator.)</li>
                        <li><strong>Facebook-Feed:</strong> Link in der Caption ist in der Regel klickbar.</li>
                        <li><strong>Story (Instagram):</strong> Link-Sticker über die aufgemalte Domain legen — für alle öffentlichen Accounts verfügbar, <strong>ein</strong> Link pro Story.</li>
                    </ul>

                    <h3>4 · Renntag-Daten (RaceResult)</h3>
                    <ul>
                        <li>Beim Anlass „Renntag-Nachbericht" zieht das Dashboard die Ergebnisse aus dem in Modul 2 hinterlegten RaceResult-Link und nutzt sie für Text + Ergebnis-Grafik. Ohne Link/Daten werden Beispiel-Daten verwendet.</li>
                    </ul>
                </div>
            </details>
        </div>

        <!-- Modul 4: Newsletter -->
        <div class="so-card">
            <h2>4 · Newsletter</h2>
            <p class="so-notice">
                Fakten eingeben → KI erzeugt einen fertigen HTML-Newsletter + 3 Betreffzeilen (nutzt den KI-Anbieter aus Modul 1).
                Danach kopieren und im Newsletter-Tool (Brevo) einfügen.
            </p>
            <div class="so-field" style="margin-top:0.75rem">
                <label for="so-nl-fakten">Fakten / Inhalte für diese Ausgabe</label>
                <textarea id="so-nl-fakten" placeholder="z. B. Anmeldung gestartet, neue Strecke, Sponsoren-News, Termine, Danksagungen …" style="min-height:110px"></textarea>
            </div>
            <div class="so-actions">
                <button class="btn btn-primary" id="so-nl-generate">Newsletter generieren</button>
                <span class="so-spinner" id="so-nl-spinner" style="display:none">⏳ KI läuft …</span>
            </div>
            <div id="so-nl-error"></div>
            <div id="so-nl-result" style="display:none;margin-top:1rem">
                <label style="display:block;font-size:0.85rem;color:var(--text-light);margin-bottom:0.35rem">Betreffzeilen-Vorschläge</label>
                <ul class="so-subjects" id="so-nl-subjects"></ul>
                <label style="display:block;font-size:0.85rem;color:var(--text-light);margin:1rem 0 0.35rem">Vorschau</label>
                <iframe class="so-nl-preview" id="so-nl-preview" title="Newsletter-Vorschau"></iframe>
                <div class="so-actions">
                    <button class="btn btn-secondary" id="so-nl-copy-html">HTML kopieren</button>
                    <span id="so-nl-copied" class="so-saved">Kopiert</span>
                </div>
            </div>
        </div>

        <!-- Modul 5: Veröffentlichen -->
        <div class="so-card">
            <h2>5 · Veröffentlichen</h2>
            <p class="so-notice" style="margin-bottom:0.9rem">
                Ein <strong>Social-Post = Bild + Text</strong>: das <strong>Bild</strong> lädst du in Modul 3 als PNG herunter
                (Format wählbar), den <strong>Text</strong> hier. Kopieren ist praktisch zum direkten Einfügen in die
                Meta Business Suite, Download als Datei zum Aufheben/Weitergeben.
            </p>
            <div class="so-field" style="margin-bottom:0.6rem">
                <label>Presse-Artikel</label>
                <div class="so-save-row">
                    <button class="btn btn-small btn-secondary" id="so-copy-article">Text kopieren</button>
                    <button class="btn btn-small btn-secondary" id="so-dl-article">Als .txt herunterladen</button>
                </div>
            </div>
            <div class="so-field" style="margin-bottom:0">
                <label>Social-Media-Post (Body-Text)</label>
                <div class="so-save-row">
                    <button class="btn btn-small btn-secondary" id="so-copy-social">Text kopieren</button>
                    <button class="btn btn-small btn-secondary" id="so-dl-social">Als .txt herunterladen</button>
                    <span style="font-size:0.8rem;color:var(--text-light)">Bild → Modul 3 (PNG)</span>
                </div>
            </div>
            <div class="so-dispatch" style="margin-top:1.1rem;border-top:1px solid var(--border);padding-top:0.9rem">
                <h3 style="margin:0 0 0.5rem;font-size:1rem">Automatisch posten (Make.com)</h3>
                <p class="so-notice" style="margin:0 0 0.6rem">
                    Sendet den <strong>Social-Text</strong> oben + die zuletzt in <strong>Modul 3</strong> erzeugte
                    <strong>Grafik</strong> über Make.com an die gewählten Kanäle. Ist kein Auto-Posting eingerichtet
                    oder klappt der Versand nicht, erscheint hier der Hinweis zum manuellen Posten — die Buttons oben
                    bleiben dafür bestehen.
                </p>
                <div class="so-save-row" style="align-items:center;gap:1rem;flex-wrap:wrap">
                    <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.9rem">
                        <input type="checkbox" id="so-ch-ig" checked> Instagram
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.9rem">
                        <input type="checkbox" id="so-ch-fb" checked> Facebook
                    </label>
                    <button class="btn btn-primary btn-small" id="so-dispatch-btn">An Instagram + Facebook senden</button>
                    <span id="so-dispatch-spinner" style="display:none;font-size:0.85rem;color:var(--text-light)">⏳ sende …</span>
                </div>
                <p id="so-dispatch-msg" style="display:none;margin-top:0.6rem;font-size:0.88rem"></p>
            </div>
        </div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const csrf     = <?= json_encode($csrf) ?>;
const mockData = <?= $mockDataJson ?>;
const socialContent = <?= json_encode((object) ($socialContent ?: []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let currentId = <?= $last ? (int)$last['id'] : 'null' ?>;

// Provider automatisch speichern bei Auswahl (kein Extra-Button nötig)
document.getElementById('so-provider').addEventListener('change', () => {
    const provider = document.getElementById('so-provider').value;
    fetch('api/social_provider.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({csrf_token: csrf, provider}),
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const msg = document.getElementById('so-provider-saved');
            msg.style.display = 'inline';
            setTimeout(() => { msg.style.display = 'none'; }, 2000);
        }
    });
});

// Prompt + Fakten pro Anlass laden/speichern (bleiben bis zur nächsten Änderung).
// Prompt: explizit per Button. Fakten: automatisch beim Verlassen des Feldes. Prompt wächst mit Inhalt.
(function() {
    const anlassSel = document.getElementById('so-anlass');
    const promptEl  = document.getElementById('so-prompt');
    const faktenEl  = document.getElementById('so-stichpunkte');

    function autoGrow() {
        promptEl.style.height = 'auto';
        promptEl.style.height = (promptEl.scrollHeight + 2) + 'px';
    }
    function loadForAnlass() {
        const c = socialContent[anlassSel.value] || {};
        promptEl.value = c.prompt || '';
        faktenEl.value = c.fakten || '';
        autoGrow();
    }
    function persist(field, value, msgEl) {
        const anlass = anlassSel.value;
        const body = new URLSearchParams({csrf_token: csrf, anlass});
        body.set(field, value);
        return fetch('api/social_prompt.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body,
        }).then(r => r.json()).then(d => {
            if (d.ok) {
                if (!socialContent[anlass]) socialContent[anlass] = {prompt: '', fakten: ''};
                socialContent[anlass][field] = value;
                if (msgEl) { msgEl.textContent = '✓ gespeichert'; msgEl.style.color = '#16a34a'; msgEl.style.display = 'inline'; setTimeout(() => { msgEl.style.display = 'none'; }, 2000); }
            } else if (msgEl) { msgEl.textContent = '⚠️ ' + (d.message || 'Fehler'); msgEl.style.color = '#dc2626'; msgEl.style.display = 'inline'; }
        }).catch(() => { if (msgEl) { msgEl.textContent = '⚠️ Netzwerkfehler'; msgEl.style.color = '#dc2626'; msgEl.style.display = 'inline'; } });
    }

    anlassSel.addEventListener('change', loadForAnlass);
    promptEl.addEventListener('input', autoGrow);
    faktenEl.addEventListener('blur', () => persist('fakten', faktenEl.value, document.getElementById('so-ft-msg')));
    document.getElementById('so-save-prompt').addEventListener('click', (e) => {
        e.currentTarget.disabled = true;
        persist('prompt', promptEl.value, document.getElementById('so-pr-msg')).finally(() => { e.currentTarget.disabled = false; });
    });
    loadForAnlass(); // Startzustand
})();

// Entwürfe generieren
document.getElementById('so-generate-btn').addEventListener('click', async () => {
    const btn     = document.getElementById('so-generate-btn');
    const spinner = document.getElementById('so-spinner');
    const errEl   = document.getElementById('so-error');
    const provider = document.getElementById('so-provider').value;

    btn.disabled = true;
    spinner.style.display = 'inline';
    errEl.style.display   = 'none';

    try {
        const r = await fetch('api/social_generate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                csrf_token:  csrf,
                provider,
                anlass:      document.getElementById('so-anlass').value,
                stichpunkte: document.getElementById('so-stichpunkte').value,
                prompt:      document.getElementById('so-prompt').value,
                hashtags:    document.getElementById('so-hashtags').value,
            }),
        });
        const d = await r.json();
        if (d.error) {
            errEl.textContent   = d.error;
            errEl.style.display = 'block';
        } else {
            document.getElementById('so-article').value = d.article;
            document.getElementById('so-social').value  = d.social;
            currentId = null; // neuer Datensatz beim nächsten Speichern
        }
    } catch (e) {
        errEl.textContent   = 'Netzwerkfehler.';
        errEl.style.display = 'block';
    } finally {
        btn.disabled         = false;
        spinner.style.display = 'none';
    }
});

// Speichern (draft / approved)
async function saveContent(status) {
    const provider = document.getElementById('so-provider').value;
    const body     = new URLSearchParams({
        csrf_token: csrf,
        article:    document.getElementById('so-article').value,
        social:     document.getElementById('so-social').value,
        status,
        provider,
    });
    if (currentId) body.set('id', currentId);

    const r = await fetch('api/social_save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body,
    });
    const d = await r.json();
    if (d.ok) {
        currentId = d.id;
        const msg = document.getElementById('so-saved-msg');
        msg.style.display = 'inline';
        setTimeout(() => { msg.style.display = 'none'; }, 2000);
    }
}

document.getElementById('so-save-draft').addEventListener('click',    () => saveContent('draft'));
document.getElementById('so-save-approved').addEventListener('click', () => saveContent('approved'));

// Copy-Buttons
function copyText(id) {
    const val = document.getElementById(id).value;
    if (!val) return;
    navigator.clipboard.writeText(val).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = val;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
document.getElementById('so-copy-article').addEventListener('click', () => copyText('so-article'));
document.getElementById('so-copy-social').addEventListener('click',  () => copyText('so-social'));

// Text-Downloads (Body-Text als .txt); das Bild kommt als PNG aus Modul 3
function downloadText(filename, id) {
    const val = document.getElementById(id).value || '';
    if (!val.trim()) return;
    const blob = new Blob([val], {type: 'text/plain;charset=utf-8'});
    const url  = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
document.getElementById('so-dl-article').addEventListener('click', () => downloadText('marktlauf-presse.txt', 'so-article'));
document.getElementById('so-dl-social').addEventListener('click',  () => downloadText('marktlauf-social.txt', 'so-social'));

// Share-Card befüllen und rendern
function fillShareCard(data) {
    const rennen10 = (data.rennen || []).find(r => r.kategorie && r.kategorie.includes('10'));
    document.getElementById('sc-event').textContent =
        data.event.name + ' · ' + (data.event.datum || '');
    document.getElementById('sc-sieger10').textContent =
        rennen10 ? rennen10.sieger.name : '–';
    document.getElementById('sc-sieger10-zeit').textContent =
        rennen10 ? rennen10.sieger.zeit : '';
    document.getElementById('sc-siegerin10').textContent =
        rennen10 && rennen10.siegerin ? rennen10.siegerin.name : '–';
    document.getElementById('sc-siegerin10-zeit').textContent =
        rennen10 && rennen10.siegerin ? rennen10.siegerin.zeit : '';
    document.getElementById('sc-tn').textContent       = data.gesamt.teilnehmer;
    document.getElementById('sc-finisher').textContent = data.gesamt.finisher;
    document.getElementById('sc-highlight').textContent = data.highlight || '';
}

let lastCardDataUrl = null; // wird beim Rendern in Modul 3 gesetzt, für Auto-Posting (Modul 5)

const CARD_FORMATS = {
    square:   { w: 1080, h: 1080, label: 'Quadratisch 1080×1080' },
    portrait: { w: 1080, h: 1350, label: 'Portrait 1080×1350' },
    story:    { w: 1080, h: 1920, label: 'Story 1080×1920' },
};
const DEFAULT_LOGO = <?= json_encode('../assets/images/ATSV_Logo-750x968.png') ?>;
const repoAssets = <?= json_encode($repoAssets, JSON_UNESCAPED_UNICODE) ?>;
let selectedPhotoUrl = '';
let selectedLogos = [{ url: DEFAULT_LOGO, label: 'ATSV-Logo' }];

// Markenfarben aus den CI-Tokens (orga.css :root) lesen — eine Quelle, kein Drift
function cssVar(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}
function hexToRgba(hex, a) {
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
    if (!m) return 'rgba(0,86,42,' + a + ')';
    return 'rgba(' + parseInt(m[1],16) + ',' + parseInt(m[2],16) + ',' + parseInt(m[3],16) + ',' + a + ')';
}
function schemeColors(key) {
    const primary = cssVar('--primary', '#009640');
    const dark    = cssVar('--primary-dark', '#007230');
    const accent  = cssVar('--accent', '#ff6b35');
    if (key === 'dunkel') return ['#0d4a2b', '#062a18'];
    if (key === 'akzent') return [accent, dark];
    return [primary, dark]; // gruen (Marke)
}

// Karte nach Auswahl (Logo, Schema, Anordnung, Foto) stylen
function applyCardStyle(fmt) {
    const card    = document.getElementById('social-share-card');
    const overlay = document.getElementById('so-overlay') || document.getElementById('sc-overlay');
    const bg      = document.getElementById('sc-bg');
    const footer  = card.querySelector('.sc-footer');

    card.style.width  = fmt.w + 'px';
    card.style.height = fmt.h + 'px';

    fillLogos();

    const [c1, c2] = schemeColors(document.getElementById('so-card-scheme').value);
    const hasPhoto = !!selectedPhotoUrl;
    card.classList.toggle('has-photo', hasPhoto);
    if (hasPhoto) {
        bg.src = selectedPhotoUrl;
        bg.style.display = 'block';
        // dunkles Overlay für Lesbarkeit, unten in Markenfarbe auslaufend
        overlay.style.background = 'linear-gradient(160deg, rgba(0,0,0,0.28) 0%, ' + hexToRgba(c2, 0.78) + ' 100%)';
    } else {
        bg.style.display = 'none';
        overlay.style.background = 'linear-gradient(145deg, ' + c1 + ' 0%, ' + c2 + ' 100%)';
    }

    const layout = document.getElementById('so-card-layout').value;
    if (layout === 'zentriert')      { card.style.justifyContent = 'center';       footer.style.marginTop = '0'; }
    else if (layout === 'verteilt')  { card.style.justifyContent = 'space-between'; footer.style.marginTop = '0'; }
    else /* freiraum */              { card.style.justifyContent = 'flex-start';    footer.style.marginTop = 'auto'; }
}

function waitImg(img) {
    return new Promise(function(resolve) {
        if (!img.getAttribute('src') || (img.complete && img.naturalWidth)) { resolve(); return; }
        img.onload = resolve; img.onerror = resolve;
    });
}

// Thumbnails rendern (items: [{url, name}]); Name per textContent -> kein XSS
function renderThumbs(panel, items, onPick, keepOpen) {
    panel.innerHTML = '';
    if (!items.length) { panel.textContent = 'Keine Bilder gefunden.'; return; }
    items.forEach(function(img) {
        const t  = document.createElement('div'); t.className = 'so-thumb';
        const im = document.createElement('img'); im.src = img.url; im.alt = '';
        const nm = document.createElement('span'); nm.textContent = img.name;
        t.appendChild(im); t.appendChild(nm);
        t.addEventListener('click', function() { onPick(img); if (!keepOpen) { panel.style.display = 'none'; } });
        panel.appendChild(t);
    });
}
// Picker aus der Datei-Ablage (Fetch, optional nach Kategorie gefiltert)
function attachPicker(btnId, panelId, onPick, kategorie) {
    const panel = document.getElementById(panelId);
    document.getElementById(btnId).addEventListener('click', async () => {
        if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
        panel.textContent = 'lädt …'; panel.style.display = 'flex';
        try {
            const q = kategorie ? ('?kategorie=' + encodeURIComponent(kategorie)) : '';
            const r = await fetch('api/dateien_images.php' + q);
            const d = await r.json();
            if (!d.ok) { panel.textContent = 'Fehler beim Laden.'; return; }
            renderThumbs(panel, (d.images || []).map(i => ({url: i.url, name: i.name})), onPick);
        } catch (e) { panel.textContent = 'Fehler beim Laden.'; }
    });
}
// Logos: mehrere aus Repo-Assets wählbar → Leiste auf der Karte
function fillLogos() {
    const box = document.getElementById('sc-logos');
    box.innerHTML = '';
    selectedLogos.forEach(function(l) {
        const im = document.createElement('img'); im.src = l.url; im.alt = '';
        box.appendChild(im);
    });
}
function renderLogoChips() {
    const box = document.getElementById('so-logo-chips');
    box.innerHTML = '';
    selectedLogos.forEach(function(l, i) {
        const c = document.createElement('span'); c.className = 'so-chip';
        const t = document.createElement('span'); t.textContent = l.label; c.appendChild(t);
        const x = document.createElement('button'); x.type = 'button'; x.textContent = '✕';
        x.addEventListener('click', function() { selectedLogos.splice(i, 1); renderLogoChips(); });
        c.appendChild(x);
        box.appendChild(c);
    });
}
attachPicker('so-pick-photo', 'so-photo-picker', function(img) {
    selectedPhotoUrl = img.url;
    document.getElementById('so-photo-name').textContent = img.name;
    document.getElementById('so-clear-photo').style.display = 'inline-flex';
}, 'presse');
document.getElementById('so-clear-photo').addEventListener('click', () => {
    selectedPhotoUrl = '';
    document.getElementById('so-photo-name').textContent = 'kein Foto';
    document.getElementById('so-clear-photo').style.display = 'none';
});
// Logo-Picker: mehrere Logos hinzufügen (Panel bleibt offen)
(function() {
    const panel = document.getElementById('so-logo-picker');
    document.getElementById('so-pick-logo').addEventListener('click', function() {
        if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
        panel.style.display = 'flex';
        renderThumbs(panel, repoAssets.map(i => ({url: i.url, name: i.label})), function(img) {
            if (!selectedLogos.some(l => l.url === img.url)) {
                selectedLogos.push({ url: img.url, label: img.name });
                renderLogoChips();
            }
        }, true);
    });
    document.getElementById('so-reset-logo').addEventListener('click', function() {
        selectedLogos = [{ url: DEFAULT_LOGO, label: 'ATSV-Logo' }];
        renderLogoChips();
    });
    renderLogoChips();
})();

document.getElementById('so-render-card').addEventListener('click', async () => {
    const btn    = document.getElementById('so-render-card');
    const errEl  = document.getElementById('so-card-error');
    const fmtKey = document.getElementById('so-card-format').value;
    const fmt    = CARD_FORMATS[fmtKey] || CARD_FORMATS.square;
    btn.disabled = true;
    btn.textContent = '⏳ Rendert …';
    errEl.style.display = 'none';

    fillShareCard(mockData);
    applyCardStyle(fmt);

    const card = document.getElementById('social-share-card');
    // Logos + optionales Hintergrundfoto vorab laden, damit html2canvas sie findet
    const logoImgs = Array.from(document.querySelectorAll('#sc-logos img'));
    await Promise.all([
        ...logoImgs.map(waitImg),
        selectedPhotoUrl ? waitImg(document.getElementById('sc-bg')) : Promise.resolve(),
    ]);

    try {
        const canvas = await html2canvas(card, {
            width:        fmt.w,
            height:       fmt.h,
            scale:        1,
            useCORS:      false,
            allowTaint:   false,
            backgroundColor: '#007230',
            logging:      false,
        });
        const dataUrl = canvas.toDataURL('image/png');
        lastCardDataUrl = dataUrl;
        document.getElementById('so-card-img').src = dataUrl;
        document.getElementById('so-card-caption').textContent = 'Vorschau (' + fmt.label + '):';
        document.getElementById('so-card-preview').style.display = 'block';

        const dlBtn = document.getElementById('so-download-card');
        dlBtn.style.display = 'inline-block';
        dlBtn.onclick = () => {
            const a = document.createElement('a');
            a.href     = dataUrl;
            a.download = 'marktlauf2026-' + fmtKey + '.png';
            a.click();
        };
    } catch (e) {
        errEl.textContent   = 'Render-Fehler: ' + e.message;
        errEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Grafik erzeugen';
    }
});

// Social-Merkfeld (Header + Anleitung, synchron): Doppelklick sperrt & speichert
(function() {
    const fields = Array.from(document.querySelectorAll('.so-merk-text'));
    if (!fields.length) return;
    const token = fields[0].dataset.csrf;

    function setLocked(ta, v) {
        ta.readOnly = v;
        const card = ta.closest('.so-merk-card');
        if (card) card.classList.toggle('locked', v);
        ta.title = v ? '🔒 gesperrt — Doppelklick zum Bearbeiten' : '✏️ Doppelklick sperrt & speichert';
    }
    function setLockedAll(v) { fields.forEach(f => setLocked(f, v)); }
    function syncValue(val) { fields.forEach(f => { f.value = val; }); }

    function save(source) {
        const body = new URLSearchParams();
        body.set('csrf_token', token);
        body.set('merkfeld', source.value);
        source.title = '… speichern';
        fetch('api/social_merkfeld.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: body
        })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d && d.ok) {
                    syncValue(source.value);
                    setLockedAll(true);
                    source.title = '🔒 gespeichert';
                } else {
                    source.title = '⚠️ ' + ((d && d.message) || 'Fehler beim Speichern');
                }
            })
            .catch(function() { source.title = '⚠️ Fehler beim Speichern'; });
    }

    fields.forEach(function(ta) {
        ta.addEventListener('dblclick', function() {
            if (ta.readOnly) { setLocked(ta, false); ta.focus(); }
            else { save(ta); }
        });
    });

    // Startzustand: mit Inhalt = gesperrt, leer = direkt beschreibbar
    setLockedAll(fields[0].value.trim() !== '');
})();

// Vereinsweite Einstellungen speichern (Hashtags, RaceResult-URL)
function saveSetting(key, value, msgEl, btn) {
    if (btn) btn.disabled = true;
    return fetch('api/social_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({csrf_token: csrf, key, value}),
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            msgEl.textContent = 'Gespeichert';
            msgEl.style.color = '#16a34a';
        } else {
            msgEl.textContent = '⚠️ ' + (d.message || 'Fehler');
            msgEl.style.color = '#dc2626';
        }
        msgEl.style.display = 'inline';
        setTimeout(() => { msgEl.style.display = 'none'; }, 2500);
    }).catch(() => {
        msgEl.textContent = '⚠️ Netzwerkfehler';
        msgEl.style.color = '#dc2626';
        msgEl.style.display = 'inline';
    }).finally(() => { if (btn) btn.disabled = false; });
}

document.getElementById('so-save-hashtags').addEventListener('click', (e) => {
    saveSetting('social_hashtags', document.getElementById('so-hashtags').value,
        document.getElementById('so-ht-msg'), e.currentTarget);
});
document.getElementById('so-save-rr').addEventListener('click', (e) => {
    saveSetting('raceresult_api_url', document.getElementById('so-rr-url').value,
        document.getElementById('so-rr-msg'), e.currentTarget);
});

// Newsletter generieren
document.getElementById('so-nl-generate').addEventListener('click', async (e) => {
    const btn     = e.currentTarget;
    const spinner = document.getElementById('so-nl-spinner');
    const errEl   = document.getElementById('so-nl-error');
    const provider = document.getElementById('so-provider').value;
    const fakten  = document.getElementById('so-nl-fakten').value;

    errEl.style.display = 'none';
    if (!fakten.trim()) {
        errEl.textContent = 'Bitte zuerst Fakten/Inhalte eingeben.';
        errEl.style.display = 'block';
        return;
    }
    btn.disabled = true;
    spinner.style.display = 'inline';
    try {
        const r = await fetch('api/newsletter_generate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({csrf_token: csrf, provider, fakten}),
        });
        const d = await r.json();
        if (d.error) {
            errEl.textContent = d.error;
            errEl.style.display = 'block';
        } else {
            const subs = document.getElementById('so-nl-subjects');
            subs.innerHTML = '';
            (d.subjects || []).forEach(s => {
                const li = document.createElement('li');
                const span = document.createElement('span');
                span.textContent = s;
                span.style.flex = '1 1 auto';
                const cp = document.createElement('button');
                cp.className = 'btn btn-small btn-secondary';
                cp.textContent = 'Kopieren';
                cp.addEventListener('click', () => navigator.clipboard.writeText(s));
                li.appendChild(span); li.appendChild(cp);
                subs.appendChild(li);
            });
            document.getElementById('so-nl-preview').srcdoc = d.html || '';
            document.getElementById('so-nl-result').dataset.html = d.html || '';
            document.getElementById('so-nl-result').style.display = 'block';
        }
    } catch (err) {
        errEl.textContent = 'Netzwerkfehler.';
        errEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
    }
});

document.getElementById('so-nl-copy-html').addEventListener('click', () => {
    const html = document.getElementById('so-nl-result').dataset.html || '';
    if (!html) return;
    navigator.clipboard.writeText(html).then(() => {
        const m = document.getElementById('so-nl-copied');
        m.style.display = 'inline';
        setTimeout(() => { m.style.display = 'none'; }, 2000);
    });
});

// Modul 5: Auto-Posting an IG/FB über Make.com (mit Fallback auf manuell)
function showDispatchMsg(text, ok) {
    const msg = document.getElementById('so-dispatch-msg');
    msg.textContent = text;
    msg.style.color = ok ? '#16a34a' : '#dc2626';
    msg.style.display = 'block';
}
document.getElementById('so-dispatch-btn').addEventListener('click', async (e) => {
    const btn     = e.currentTarget;
    const spinner = document.getElementById('so-dispatch-spinner');
    const text    = (document.getElementById('so-social').value || '').trim();
    const channels = [];
    if (document.getElementById('so-ch-ig').checked) channels.push('instagram');
    if (document.getElementById('so-ch-fb').checked) channels.push('facebook');

    document.getElementById('so-dispatch-msg').style.display = 'none';
    if (!text)            { showDispatchMsg('Bitte zuerst einen Social-Text generieren/eingeben (Modul 2).', false); return; }
    if (!channels.length) { showDispatchMsg('Bitte mindestens einen Kanal (Instagram/Facebook) wählen.', false); return; }

    btn.disabled = true;
    spinner.style.display = 'inline';
    try {
        const body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('text', text);
        if (lastCardDataUrl) body.set('image_base64', lastCardDataUrl);
        channels.forEach(c => body.append('channels[]', c));

        const r = await fetch('api/social_dispatch.php', { method: 'POST', body });
        const d = await r.json();
        if (d.ok) {
            showDispatchMsg('✓ ' + (d.message || 'An Make.com übergeben.'), true);
        } else {
            showDispatchMsg('⚠️ ' + (d.message || d.error || 'Versand fehlgeschlagen — bitte manuell posten (Text kopieren, PNG herunterladen).'), false);
        }
    } catch (err) {
        showDispatchMsg('⚠️ Netzwerkfehler — bitte manuell posten (Text kopieren, PNG herunterladen).', false);
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
    }
});

// Burger-Menü (wie alle anderen Orga-Seiten)
const burgerBtn      = document.getElementById('burger-btn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
if (burgerBtn) {
    burgerBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('active');
    });
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
    });
}
</script>
</body>
</html>
