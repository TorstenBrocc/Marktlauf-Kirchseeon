<?php
/**
 * Kampagnen-Poster-Generator (Frei-Editor v5) — Marketing-Qualitaet aus dem Tool.
 * Stufe 5:
 * - Einzelne Elemente statt Sammel-Bloecke (Logos einzeln, Info-Kacheln einzeln)
 * - Icon-Bibliothek + Bild/Logo-Bibliothek: pro Element setzen/tauschen/skalieren, eigenes Bild hochladen
 * - Kachel-Hintergrund pro Element an/aus ("Schrift & Kachel trennen")
 * - Zoom (Buttons + Strg/Cmd+Mausrad), Arbeitsflaeche/Pasteboard, Gruppieren, justierbarer Verlauf
 * - PNG-Export (2x, nur Poster-Bereich)
 */
declare(strict_types=1);
require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
$pdo     = getDbConnection();
$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

$sponsors = [];
foreach (glob(__DIR__ . '/../assets/images/sponsoren/*.{png,jpg,jpeg,webp,PNG,JPG}', GLOB_BRACE) ?: [] as $f) {
    $sponsors[] = '../assets/images/sponsoren/' . rawurlencode(basename($f));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kampagnen-Poster | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        .pg-wrap { display: grid; grid-template-columns: 360px 1fr; gap: 1.5rem; align-items: start; }
        @media (max-width: 1000px) { .pg-wrap { grid-template-columns: 1fr; } }
        .pg-row { margin-bottom: 0.65rem; }
        .pg-row label { font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.2rem; display: block; }
        .pg-row input[type=text], .pg-row input[type=url], .pg-row select { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; background:#fff; }
        .pg-row input[type=range] { width: 100%; }
        .pg-row input + input { margin-top: 0.35rem; }
        .pg-hint { font-size: 0.82rem; color: var(--text-light); }
        .pg-sel-panel { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.75rem; }
        .pg-sel-panel b.pg-sel-name { font-size: 0.88rem; }
        .pg-grad { border: 1px solid var(--border); border-radius: 8px; padding: 0.6rem 0.7rem; margin: 0.6rem 0; background:#fafbfa; }
        .pg-grad > label { display:flex; align-items:center; gap:0.4rem; font-weight:700; font-size:0.9rem; margin-bottom:0.5rem; }
        .pg-grad input[type=range] { width: 100%; }
        .pg-icon-prev { display:inline-flex; vertical-align:middle; margin-left:0.4rem; width:26px; height:26px; }
        .pg-icon-prev svg { width:26px; height:26px; stroke:#007230; fill:none; stroke-width:2.2; }
        .pg-feat-icons { display:flex; gap:0.4rem; }
        .pg-feat-icons select { font-size:0.82rem; }

        /* Zoom-Leiste */
        .pg-zoombar { display:flex; align-items:center; gap:0.4rem; margin:0 0 0.5rem; }
        .pg-zoombar .btn { padding:0.25rem 0.6rem; }
        .pg-zoombar .pg-zoom-val { min-width:52px; text-align:center; font-weight:700; font-size:0.85rem; }

        /* Arbeitsflaeche (Pasteboard) + geclipptes Poster-Artboard */
        .pg-stage { position: relative; width: 620px; max-width: 100%; min-width: 300px; overflow: hidden; border: 1px solid var(--border); border-radius: 10px; background: #cfd4cf; resize: horizontal; }
        #pg-canvas { position: relative; }
        #pg-scene { position: absolute; top: 0; left: 0; transform-origin: top left; background: #cfd4cf;
            background-image: linear-gradient(45deg,#c7ccc7 25%,transparent 25%,transparent 75%,#c7ccc7 75%),linear-gradient(45deg,#c7ccc7 25%,transparent 25%,transparent 75%,#c7ccc7 75%);
            background-size: 40px 40px; background-position: 0 0, 20px 20px; }
        #pg-art { position: absolute; overflow: hidden;
            font-family: 'Montserrat', 'Arial Black', system-ui, sans-serif; color:#fff; background: #007230;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.18), 0 14px 50px rgba(0,0,0,0.28); }
        #pg-art .pg-bg { position: absolute; inset: 0; background-size: cover; background-position: center; z-index: 0; }
        #pg-art .pg-ov { position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(120deg, rgba(0,86,42,0.92) 0%, rgba(0,118,48,0.78) 46%, rgba(0,118,48,0.15) 100%); }

        .pb { position: absolute; z-index: 2; transform-origin: top left; cursor: grab;
            font-family: 'Montserrat', 'Arial Black', system-ui, sans-serif; color:#fff; }
        .pb.dragging { cursor: grabbing; }
        .pb.pg-grouped::after { content:''; position:absolute; inset:-4px; border:2px dotted rgba(255,140,66,0.55); border-radius:6px; pointer-events:none; z-index:7; }

        /* Auswahlrahmen + Resize-Handle + Fanglinien (UI-Overlay, nicht im Export) */
        .pg-selbox { position: absolute; z-index: 8; border: 3px dashed #ff6b35; box-sizing: border-box; display: none; pointer-events: none; }
        .pg-selbox-h { position: absolute; right: -26px; bottom: -26px; width: 52px; height: 52px; background: #fff; border: 5px solid #ff6b35; border-radius: 8px; pointer-events: auto; cursor: nwse-resize; }
        .pg-guide-v { position: absolute; width: 0; border-left: 3px dashed #ff8c42; z-index: 9; display: none; }
        .pg-guide-h { position: absolute; height: 0; border-top: 3px dashed #ff8c42; z-index: 9; display: none; }

        /* Logo-Kachel (einzeln, Bild tauschbar + skalierbar) */
        .pg-logo-tile { background: #fff; border-radius: 18px; padding: 16px 22px; display: flex; align-items: center; justify-content: center; }
        .pg-logo-tile img { height: 96px; width: auto; max-width: 420px; object-fit: contain; display: block; }
        .pg-logo-tile.pg-notile { background: transparent; box-shadow: none; padding: 0; }

        .pg-coop { background: #fff; border-radius: 18px; padding: 14px 20px; text-align: center; }
        .pg-coop small { display: block; color: #007230; font-weight: 800; font-size: 15px; letter-spacing: 1px; margin-bottom: 6px; }
        .pg-coop img { height: 56px; width: auto; }
        .pg-coop.pg-notile { background: transparent; box-shadow: none; padding: 0; }
        .pg-coop.pg-notile small { color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,0.3); }

        .pg-headline { font-size: 100px; font-weight: 900; line-height: 0.94; letter-spacing: -1px; text-transform: uppercase; width: 820px; text-shadow: 0 2px 18px rgba(0,0,0,0.25); }
        .pg-subline { font-size: 38px; font-weight: 700; width: 720px; }

        .pg-features { display: flex; flex-direction: column; gap: 22px; width: 760px; }
        .pg-feat { display: flex; align-items: center; gap: 20px; }
        .pg-feat .pg-ic { flex: 0 0 66px; width: 66px; height: 66px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center; }
        .pg-feat .pg-ic svg { width: 33px; height: 33px; stroke: #fff; fill: none; stroke-width: 2.2; }
        .pg-feat .pg-ic:empty { display:none; }
        .pg-feat .pg-ft { font-size: 32px; font-weight: 800; line-height: 1.05; }
        .pg-feat .pg-fs { font-size: 24px; font-weight: 500; opacity: 0.92; }

        .pg-cta { background: #f4b81e; color: #0d3b1e; font-weight: 900; font-size: 42px; padding: 24px 50px; border-radius: 16px; text-transform: uppercase; box-shadow: 0 8px 24px rgba(0,0,0,0.2); white-space: nowrap; }

        .pg-sponsors { display: flex; flex-wrap: wrap; gap: 14px; width: 900px; }
        .pg-sp { background: #fff; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; }
        .pg-sp img { height: 50px; width: auto; max-width: 200px; object-fit: contain; }

        /* Info-Kachel (einzeln) */
        .pg-dcard { background: rgba(255,255,255,0.96); color: #1f2a22; border-radius: 16px; padding: 18px 20px; width: 220px; box-sizing: border-box; }
        .pg-dcard svg { width: 34px; height: 34px; stroke: #009640; fill: none; stroke-width: 2.2; margin-bottom: 8px; }
        .pg-dcard b { display: block; font-size: 25px; font-weight: 900; line-height: 1.12; }
        .pg-dcard span { display:block; font-size: 20px; font-weight: 500; color: #3a473f; margin-top: 4px; line-height: 1.15; }
        .pg-dcard.pg-notile { background: transparent; box-shadow: none; padding: 0; color: #fff; }
        .pg-dcard.pg-notile b { color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.28); }
        .pg-dcard.pg-notile span { color: #eaf3ec; }
        .pg-dcard.pg-notile svg { stroke: #fff; }

        .pg-scan { background: #fff; color: #0d3b1e; border-radius: 20px; padding: 20px; text-align: center; width: 290px; }
        .pg-scan-head { font-weight: 900; font-size: 24px; color: #007230; margin-bottom: 12px; line-height: 1.1; }
        .pg-domain { font-size: 19px; font-weight: 700; color: #007230; margin-top: 12px; }
        #pg-qr { width: 190px; height: 190px; display: block; margin: 0 auto; }

        /* Eigene Kachel */
        .pg-ctile { background: #fff; color: #0d3b1e; border-radius: 16px; padding: 18px 22px; display: flex; align-items: center; gap: 14px; }
        .pg-ctile .pg-ct-ic { display:inline-flex; }
        .pg-ctile .pg-ct-ic svg { width: 40px; height: 40px; stroke: #009640; fill:none; stroke-width: 2.2; }
        .pg-ctile .pg-ct-ic:empty { display:none; }
        .pg-ctile img { height: 52px; width: auto; max-width: 220px; object-fit: contain; }
        .pg-ct-text { font-size: 30px; font-weight: 800; white-space: nowrap; }
        .pg-ctile.pg-notile { background: transparent; box-shadow: none; padding: 0; color:#fff; }
        .pg-ctile.pg-notile .pg-ct-text { color:#fff; text-shadow: 0 2px 10px rgba(0,0,0,0.28); }
        .pg-ctile.pg-notile .pg-ct-ic svg { stroke:#fff; }
    </style>
</head>
<body>
<?php $activeNav = 'social_media'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Kampagnen-Poster (Frei-Editor)</h1>
            <p class="content-subtitle">Einzelne Elemente · Icons/Logos tauschbar · Kachel-Hintergrund an/aus · Zoom · Arbeitsfläche · Verlauf · PNG-Export</p>
        </header>

        <p style="margin:0 0 1rem"><a href="social_orchestrator.php">&larr; zur Social-Media-Seite</a>
            <a href="../docs/poster-generator.md" target="_blank" rel="noopener" style="margin-left:1rem">ℹ️ Doku &amp; Ausbaustufen</a></p>
        <p class="pg-hint" style="max-width:860px;margin-bottom:1.25rem">
            <strong>Element anklicken</strong> → im Panel links erscheinen die passenden Optionen (Icon/Bild tauschen, skalieren, <strong>Kachel-Hintergrund an/aus</strong> = Schrift &amp; Kachel trennen).
            <strong>Ziehen</strong> zum Verschieben (mit Fanglinien), <strong>orangenes Eck-Quadrat</strong> = Größe.
            <strong>Gruppieren:</strong> <strong>Shift-Klick</strong> für Mehrfachauswahl → „🔗 Gruppieren".
            <strong>Arbeitsfläche:</strong> Elemente neben das Poster ziehen = ablegen (nicht im Export).
            <strong>Zoom:</strong> Leiste über der Vorschau oder <strong>Strg/Cmd + Mausrad</strong>.
        </p>

        <div class="pg-wrap">
            <div class="pg-controls">
                <div class="pg-sel-panel" id="pg-sel-panel" style="display:none">
                    <b class="pg-sel-name" id="pg-sel-name">Kein Element ausgewählt</b>

                    <div class="pg-row" id="pg-scale-row" style="margin:0.5rem 0 0"><label>Größe: <span id="pg-sel-scale-val">100</span> %</label>
                        <input type="range" id="pg-sel-scale" min="20" max="400" value="100"></div>

                    <div class="pg-row" id="pg-text-row" style="display:none"><label>Beschriftung</label><input type="text" id="pg-el-text" value=""></div>

                    <div class="pg-row" id="pg-icon-row" style="display:none"><label>Icon <span class="pg-icon-prev" id="pg-el-icon-prev"></span></label><select id="pg-el-icon"></select></div>

                    <div class="pg-row" id="pg-img-row" style="display:none"><label>Bild / Logo</label><select id="pg-el-img"></select>
                        <input type="file" id="pg-el-img-file" accept="image/*" style="display:none"></div>

                    <div class="pg-row" id="pg-cap-row" style="display:none"><label>Über-Text</label><input type="text" id="pg-el-cap" value=""></div>

                    <div class="pg-row" id="pg-tile-row" style="display:none;margin-top:0.3rem"><label style="display:flex;align-items:center;gap:0.4rem;margin:0"><input type="checkbox" id="pg-el-tile"> weiße Kachel als Hintergrund</label></div>

                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.5rem">
                        <button class="btn btn-small btn-secondary" id="pg-group" type="button" style="display:none">🔗 Gruppieren</button>
                        <button class="btn btn-small btn-secondary" id="pg-ungroup" type="button" style="display:none">Gruppierung lösen</button>
                        <button class="btn btn-small btn-secondary" id="pg-deselect" type="button">Auswahl aufheben</button>
                        <button class="btn btn-small btn-secondary" id="pg-del-block" type="button" style="display:none">Element löschen</button>
                    </div>
                    <p class="pg-hint" style="margin:0.5rem 0 0">Mehrfachauswahl: <b>Shift-Klick</b>. Neben das Poster ziehen = ablegen (nicht im Export).</p>
                </div>

                <button class="btn btn-secondary" id="c-add-tile" type="button" style="margin-bottom:0.75rem;width:100%">+ Eigenes Element (Text/Icon/Logo)</button>

                <div class="pg-row"><label>Format</label>
                    <select id="c-format">
                        <option value="portrait">Portrait 1080×1350 (Feed)</option>
                        <option value="square">Quadratisch 1080×1080</option>
                        <option value="story">Story 1080×1920</option>
                    </select>
                </div>
                <div class="pg-row"><label>Headline</label><input type="text" id="c-headline" value="ANMELDUNG GEÖFFNET!"></div>
                <div class="pg-row"><label>Subline</label><input type="text" id="c-subline" value="Sichert euch jetzt euren Startplatz!"></div>
                <div class="pg-row"><label>Button-Text</label><input type="text" id="c-cta" value="JETZT ANMELDEN!"></div>

                <div class="pg-row"><label>Feature 1 (Titel / Zusatz / Icon)</label><input type="text" id="c-f1t" value="Für alle Altersklassen"><input type="text" id="c-f1s" value="Bambini, Schüler, Jugend, Erwachsene"><div class="pg-feat-icons" style="margin-top:0.35rem"><select id="c-f1i"></select></div></div>
                <div class="pg-row"><label>Feature 2 (Titel / Zusatz / Icon)</label><input type="text" id="c-f2t" value="Verschiedene Distanzen"><input type="text" id="c-f2s" value="500 m bis 10 km"><div class="pg-feat-icons" style="margin-top:0.35rem"><select id="c-f2i"></select></div></div>
                <div class="pg-row"><label>Feature 3 (Titel / Zusatz / Icon)</label><input type="text" id="c-f3t" value="Gemeinsam für Umwelt & Energie"><input type="text" id="c-f3s" value="Jeder Schritt zählt!"><div class="pg-feat-icons" style="margin-top:0.35rem"><select id="c-f3i"></select></div></div>

                <div class="pg-row"><label>Datum-Kachel (Titel · Zusatz)</label><input type="text" id="c-date" value="Sonntag 20.09.2026 · Start 10:00 Uhr"></div>
                <div class="pg-row"><label>Ort-Kachel (Titel · Zusatz)</label><input type="text" id="c-loc" value="JEK, Westring 6 · Kirchseeon"></div>
                <div class="pg-row"><label>Familien-Kachel (Titel · Zusatz)</label><input type="text" id="c-fam" value="Für die ganze Familie · Sport, Spaß & Gemeinschaft"></div>
                <div class="pg-row"><label>Domain</label><input type="text" id="c-domain" value="atsv-kirchseeon-marktlauf.de"></div>
                <div class="pg-row"><label><input type="checkbox" id="c-show-sponsors"> Sponsoren-Kacheln anzeigen</label></div>
                <div class="pg-row"><label>QR-Ziel-URL</label><input type="url" id="c-qr-url" value="https://atsv-kirchseeon-marktlauf.de/#anmeldung"></div>

                <div class="pg-grad">
                    <label><input type="checkbox" id="c-grad-on" checked> Marken-Verlauf (Hintergrund)</label>
                    <div class="pg-row" style="margin-bottom:0.5rem"><label>Winkel: <span id="c-grad-angle-val">120</span>°</label><input type="range" id="c-grad-angle" min="0" max="360" value="120"></div>
                    <div style="display:flex;gap:0.6rem">
                        <div class="pg-row" style="flex:1;margin-bottom:0.5rem"><label>Farbe 1</label><input type="color" id="c-grad-c1" value="#00562a" style="width:100%;height:34px;padding:2px;border:1px solid var(--border);border-radius:6px;background:#fff"></div>
                        <div class="pg-row" style="flex:1;margin-bottom:0.5rem"><label>Farbe 2</label><input type="color" id="c-grad-c2" value="#007230" style="width:100%;height:34px;padding:2px;border:1px solid var(--border);border-radius:6px;background:#fff"></div>
                    </div>
                    <div class="pg-row" style="margin-bottom:0"><label>Foto-Durchsicht am Rand: <span id="c-grad-fade-val">15</span> %</label><input type="range" id="c-grad-fade" min="0" max="90" value="15"></div>
                </div>

                <div class="pg-row"><label>Hintergrundfoto (optional)</label><input type="file" id="c-photo" accept="image/*"> <button class="btn btn-small btn-secondary" id="c-photo-clear" type="button">entfernen</button></div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.6rem">
                    <button class="btn btn-primary" id="c-export" type="button">PNG exportieren</button>
                    <button class="btn btn-secondary" id="c-reset" type="button">Layout zurücksetzen</button>
                </div>
                <p class="pg-hint" id="c-status" style="margin-top:0.5rem"></p>
            </div>

            <div>
                <div class="pg-zoombar">
                    <span class="pg-hint" style="margin-right:0.3rem">Zoom:</span>
                    <button class="btn btn-small btn-secondary" id="c-zoom-out" type="button">−</button>
                    <span class="pg-zoom-val" id="c-zoom-val">100 %</span>
                    <button class="btn btn-small btn-secondary" id="c-zoom-in" type="button">+</button>
                    <button class="btn btn-small btn-secondary" id="c-zoom-fit" type="button">Einpassen</button>
                    <span class="pg-hint" style="margin-left:0.4rem">(Strg/Cmd + Mausrad)</span>
                </div>
                <p class="pg-hint" style="margin:0 0 0.4rem">Vorschau + Arbeitsfläche (rechts unten ziehen zum Vergrößern):</p>
                <div class="pg-stage" id="pg-stage">
                  <div id="pg-canvas">
                    <div id="pg-scene">
                        <div id="pg-art">
                            <div class="pg-bg" id="pg-bg"></div>
                            <div class="pg-ov" id="pg-ov"></div>
                        </div>

                        <div class="pb" id="b-logo1" data-name="Logo Marktlauf" data-kind="logo"><div class="pg-logo-tile"><img class="pg-logo-img" alt="Marktlauf"></div></div>
                        <div class="pb" id="b-logo2" data-name="Logo ATSV" data-kind="logo"><div class="pg-logo-tile"><img class="pg-logo-img" alt="ATSV"></div></div>
                        <div class="pb" id="b-coop" data-name="Gemeinde-Logo" data-kind="coop"><div class="pg-coop"><small class="pg-coop-cap">IN KOOPERATION MIT</small><img class="pg-coop-img" alt="Gemeinde"></div></div>

                        <div class="pb" id="b-headline" data-name="Headline" data-kind="text"><div class="pg-headline" id="p-headline">ANMELDUNG GEÖFFNET!</div></div>
                        <div class="pb" id="b-subline" data-name="Subline" data-kind="text"><div class="pg-subline" id="p-subline">Sichert euch jetzt euren Startplatz!</div></div>
                        <div class="pb" id="b-features" data-name="Feature-Liste" data-kind="features"><div class="pg-features" id="p-features"></div></div>
                        <div class="pb" id="b-cta" data-name="Button" data-kind="text"><div class="pg-cta" id="p-cta">JETZT ANMELDEN!</div></div>
                        <div class="pb" id="b-sponsors" data-name="Sponsoren" data-kind="sponsors" style="display:none"><div class="pg-sponsors" id="p-sponsors"></div></div>

                        <div class="pb" id="b-date" data-name="Kachel: Datum" data-kind="dcard"><div class="pg-dcard"></div></div>
                        <div class="pb" id="b-loc" data-name="Kachel: Ort" data-kind="dcard"><div class="pg-dcard"></div></div>
                        <div class="pb" id="b-fam" data-name="Kachel: Familie" data-kind="dcard"><div class="pg-dcard"></div></div>

                        <div class="pb" id="b-scan" data-name="Scan/QR-Kachel" data-kind="scan">
                            <div class="pg-scan">
                                <div class="pg-scan-head">JETZT SCANNEN<br>&amp; ANMELDEN!</div>
                                <img id="pg-qr" alt="">
                                <div class="pg-domain" id="p-domain">atsv-kirchseeon-marktlauf.de</div>
                            </div>
                        </div>

                        <div class="pg-guide-v" id="pg-guide-v"></div>
                        <div class="pg-guide-h" id="pg-guide-h"></div>
                        <div class="pg-selbox" id="pg-selbox"><div class="pg-selbox-h" id="pg-selbox-h"></div></div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
    </main>
</div><!-- /dashboard-layout -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function(){
        var SPONSORS = <?= json_encode($sponsors, JSON_UNESCAPED_SLASHES) ?>;
        var FORMATS = { portrait:{w:1080,h:1350}, square:{w:1080,h:1080}, story:{w:1080,h:1920} };
        var PAD = 340; // Arbeitsflaeche rings um das Poster (in Poster-Pixeln)

        // ---- Bild-/Logo-Bibliothek (Key -> URL). Eigene Uploads werden hier ergaenzt. ----
        var LOGOS = {
            'Marktlauf': '../assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png',
            'ATSV': '../assets/images/ATSV_Logo-750x968.png',
            'Gemeinde': '../assets/images/Wort-u-Bildmarke-Gemeinde.png'
        };
        SPONSORS.forEach(function(u,i){ LOGOS['Sponsor '+(i+1)] = u; });
        var uploadN = 0;

        // ---- Icon-Bibliothek ----
        var ICONS = {
            shoe:'<svg viewBox="0 0 24 24"><path d="M2 17h20v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2z"/><path d="M2 17l1-6 5 1 4 3h8a2 2 0 0 1 2 2"/></svg>',
            run:'<svg viewBox="0 0 24 24"><circle cx="15" cy="4" r="2"/><path d="M9 20l2-5 3 2 1 3M11 15l-2-4 3-3 3 2 2 1M4 12l3-1"/></svg>',
            medal:'<svg viewBox="0 0 24 24"><circle cx="12" cy="15" r="5"/><path d="M9 2l3 6M15 2l-3 6M10.5 15h3"/></svg>',
            trophy:'<svg viewBox="0 0 24 24"><path d="M7 4h10v5a5 5 0 0 1-10 0V4zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3M9 18h6M8 21h8M12 14v4"/></svg>',
            flag:'<svg viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>',
            stopwatch:'<svg viewBox="0 0 24 24"><circle cx="12" cy="14" r="7"/><path d="M12 14V10M9 3h6M18 8l1.5-1.5"/></svg>',
            clock:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg>',
            calendar:'<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg>',
            pin:'<svg viewBox="0 0 24 24"><path d="M12 22s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
            map:'<svg viewBox="0 0 24 24"><path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/></svg>',
            family:'<svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3 3-5 6-5s6 2 6 5M14 20c0-2 2-4 4-4s4 1 4 3"/></svg>',
            people:'<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.5 3-6 6-6s6 2.5 6 6M16 5a3 3 0 0 1 0 6M17 20c0-2.5-1-4.5-3-5.5"/></svg>',
            heart:'<svg viewBox="0 0 24 24"><path d="M12 21C5 15 3 11 3 8a4.5 4.5 0 0 1 9-1 4.5 4.5 0 0 1 9 1c0 3-2 7-9 13z"/></svg>',
            star:'<svg viewBox="0 0 24 24"><path d="M12 3l2.9 6 6.1.9-4.5 4.3 1.1 6.1L12 17.8 6.4 20.3 7.5 14.2 3 9.9 9.1 9z"/></svg>',
            leaf:'<svg viewBox="0 0 24 24"><path d="M4 20C4 10 12 4 20 4c0 8-6 16-16 16z"/><path d="M4 20c4-6 8-8 12-9"/></svg>',
            tree:'<svg viewBox="0 0 24 24"><path d="M12 2l5 7h-3l4 6H6l4-6H7l5-7zM12 15v6"/></svg>',
            ticket:'<svg viewBox="0 0 24 24"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4zM14 6v12"/></svg>',
            euro:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15 8a4 4 0 0 0-4 8M7 11h6M7 13h5"/></svg>',
            info:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
            home:'<svg viewBox="0 0 24 24"><path d="M3 11l9-7 9 7M5 10v10h14V10"/></svg>',
            bike:'<svg viewBox="0 0 24 24"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M6 17l4-8h5l3 8M9 6h3l2 3"/></svg>',
            mountain:'<svg viewBox="0 0 24 24"><path d="M3 20l6-11 4 6 2-3 6 8z"/></svg>',
            sun:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/></svg>',
            target:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/></svg>',
            check:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>',
            bolt:'<svg viewBox="0 0 24 24"><path d="M13 2L4 14h6l-1 8 9-12h-6z"/></svg>'
        };
        var ICON_LIST = [['','— kein Icon —'],['shoe','Schuh'],['run','Läufer'],['bike','Fahrrad'],['medal','Medaille'],['trophy','Pokal'],['flag','Ziel/Flagge'],['stopwatch','Stoppuhr'],['clock','Uhr'],['calendar','Kalender'],['pin','Ort'],['map','Karte'],['home','Haus'],['family','Familie'],['people','Personen'],['heart','Herz'],['star','Stern'],['leaf','Blatt'],['tree','Baum'],['mountain','Berg'],['sun','Sonne'],['ticket','Ticket'],['euro','Euro'],['info','Info'],['target','Ziel'],['check','Haken'],['bolt','Energie']];

        // Standard-Positionen in POSTER-Koordinaten (0,0 = obere linke Poster-Ecke)
        var DEFAULTS = {
            'b-logo1':{x:44,y:56,s:1}, 'b-logo2':{x:360,y:44,s:1}, 'b-coop':{x:748,y:44,s:1},
            'b-headline':{x:56,y:250,s:1}, 'b-subline':{x:56,y:520,s:1}, 'b-features':{x:56,y:632,s:1},
            'b-cta':{x:56,y:940,s:1}, 'b-sponsors':{x:56,y:1050,s:1},
            'b-date':{x:56,y:1140,s:1}, 'b-loc':{x:290,y:1140,s:1}, 'b-fam':{x:524,y:1140,s:1},
            'b-scan':{x:748,y:1030,s:1}
        };
        // Editier-Metadaten je Element (Icon/Bild/Kachel/Über-Text)
        function baseMeta(){ return {
            'b-logo1':{img:'Marktlauf',tile:true},
            'b-logo2':{img:'ATSV',tile:true},
            'b-coop':{img:'Gemeinde',tile:true,cap:'IN KOOPERATION MIT'},
            'b-date':{icon:'calendar',tile:true},
            'b-loc':{icon:'pin',tile:true},
            'b-fam':{icon:'family',tile:true}
        }; }
        var meta = baseMeta();
        var featIcons = ['shoe','stopwatch','leaf'];

        var $ = function(id){ return document.getElementById(id); };
        var scene=$('pg-scene'), art=$('pg-art'), stage=$('pg-stage'), canvas=$('pg-canvas'),
            selbox=$('pg-selbox'), gv=$('pg-guide-v'), gh=$('pg-guide-h');
        var curW=1080, curH=1350, sceneW=0, sceneH=0, pos={}, selIds=[], sel=null, customN=0;
        var groupOf={}, groupSeq=0, fitScale=1, zoom=1;

        function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function imgUrl(k){ return (k&&LOGOS[k])?LOGOS[k]:''; }
        function kindOf(id){ return $(id).getAttribute('data-kind'); }
        function natW(id){ return $(id).offsetWidth; }
        function natH(id){ return $(id).offsetHeight; }
        // pos ist in SZENEN-Koordinaten (inkl. PAD-Offset). Poster-Ecke oben-links = (PAD,PAD).
        function rect(id){ var p=pos[id]; return {x:p.x, y:p.y, w:natW(id)*p.s, h:natH(id)*p.s}; }
        function applyBlock(id){ var b=$(id),p=pos[id]; b.style.left=p.x+'px'; b.style.top=p.y+'px'; b.style.transform='scale('+p.s+')'; }
        function applyAll(){ Object.keys(pos).forEach(applyBlock); if(selIds.length) updateSelbox(); }
        function bbox(ids){ var x0=1e9,y0=1e9,x1=-1e9,y1=-1e9; ids.forEach(function(id){ var r=rect(id); x0=Math.min(x0,r.x); y0=Math.min(y0,r.y); x1=Math.max(x1,r.x+r.w); y1=Math.max(y1,r.y+r.h); }); return {x:x0,y:y0,w:x1-x0,h:y1-y0}; }

        // ---- Element-Rendering ----
        function renderLogo(id){ var m=meta[id], el=$(id).querySelector('.pg-logo-tile'), img=el.querySelector('.pg-logo-img');
            var u=imgUrl(m.img); if(u){ img.src=u; img.style.display='block'; } else { img.removeAttribute('src'); img.style.display='none'; }
            el.classList.toggle('pg-notile', !m.tile); }
        function renderCoop(id){ var m=meta[id], el=$(id).querySelector('.pg-coop'), img=el.querySelector('.pg-coop-img'), cap=el.querySelector('.pg-coop-cap');
            var u=imgUrl(m.img); if(u){ img.src=u; img.style.display='inline-block'; } else { img.removeAttribute('src'); img.style.display='none'; }
            cap.textContent=m.cap||''; cap.style.display=(m.cap? 'block':'none'); el.classList.toggle('pg-notile', !m.tile); }
        function renderDcard(id){ var m=meta[id], el=$(id).querySelector('.pg-dcard');
            var src={'b-date':'c-date','b-loc':'c-loc','b-fam':'c-fam'}[id];
            var parts=($( src ).value||'').split('·');
            var ic=(m.icon&&ICONS[m.icon])?ICONS[m.icon]:'';
            el.innerHTML=ic+'<b>'+esc((parts[0]||'').trim())+'</b><span>'+esc(parts.slice(1).join('·').trim())+'</span>';
            el.classList.toggle('pg-notile', !m.tile); }
        function renderFeatures(){
            $('p-features').innerHTML=[[featIcons[0],'c-f1t','c-f1s'],[featIcons[1],'c-f2t','c-f2s'],[featIcons[2],'c-f3t','c-f3s']].map(function(f){
                var ic=(f[0]&&ICONS[f[0]])?ICONS[f[0]]:'';
                return '<div class="pg-feat"><div class="pg-ic">'+ic+'</div><div><div class="pg-ft">'+esc($(f[1]).value)+'</div><div class="pg-fs">'+esc($(f[2]).value)+'</div></div></div>';
            }).join('');
        }
        function renderCustom(id){ var m=meta[id]||{}, el=$(id).querySelector('.pg-ctile');
            el.querySelector('.pg-ct-ic').innerHTML=(m.icon&&ICONS[m.icon])?ICONS[m.icon]:'';
            var img=el.querySelector('.pg-ct-img'), u=imgUrl(m.img);
            if(u){ img.src=u; img.style.display='inline-block'; } else { img.removeAttribute('src'); img.style.display='none'; }
            el.classList.toggle('pg-notile', !m.tile); }
        function renderEl(id){ var k=kindOf(id);
            if(k==='logo') renderLogo(id); else if(k==='coop') renderCoop(id); else if(k==='dcard') renderDcard(id);
            else if(k==='features') renderFeatures(); else if(k==='custom') renderCustom(id); }
        function renderStatic(){
            $('p-headline').textContent=$('c-headline').value; $('p-subline').textContent=$('c-subline').value;
            $('p-cta').textContent=$('c-cta').value; $('p-domain').textContent=$('c-domain').value;
        }
        function renderSponsors(){
            var blk=$('b-sponsors');
            if(!$('c-show-sponsors').checked||!SPONSORS.length){ blk.style.display='none'; if(selIds.indexOf('b-sponsors')!==-1) deselect(); return; }
            $('p-sponsors').innerHTML=SPONSORS.map(function(u){ return '<div class="pg-sp"><img src="'+u+'" alt=""></div>'; }).join(''); blk.style.display='block';
        }
        function renderAll(){ renderStatic(); ['b-logo1','b-logo2','b-coop','b-date','b-loc','b-fam'].forEach(renderEl);
            Object.keys(meta).forEach(function(id){ if(/^custom/.test(id)&&$(id)) renderCustom(id); });
            renderFeatures(); renderSponsors(); }

        // ---- Content-Bindings ----
        function bindStatic(cid,fn){ $(cid).addEventListener('input',function(){ fn(); if(selIds.length) updateSelbox(); }); }
        bindStatic('c-headline',function(){ $('p-headline').textContent=$('c-headline').value; });
        bindStatic('c-subline',function(){ $('p-subline').textContent=$('c-subline').value; });
        bindStatic('c-cta',function(){ $('p-cta').textContent=$('c-cta').value; });
        bindStatic('c-domain',function(){ $('p-domain').textContent=$('c-domain').value; });
        ['c-f1t','c-f1s','c-f2t','c-f2s','c-f3t','c-f3s'].forEach(function(id){ $(id).addEventListener('input',function(){ renderFeatures(); if(selIds.length) updateSelbox(); }); });
        [['c-date','b-date'],['c-loc','b-loc'],['c-fam','b-fam']].forEach(function(m){ $(m[0]).addEventListener('input',function(){ renderDcard(m[1]); if(selIds.length) updateSelbox(); }); });
        $('c-show-sponsors').addEventListener('change', renderSponsors);

        // Icon-Selects fuer Features (linkes Panel)
        function fillIconSelect(seln, cur){ seln.innerHTML=ICON_LIST.map(function(o){ return '<option value="'+o[0]+'"'+(o[0]===cur?' selected':'')+'>'+o[1]+'</option>'; }).join(''); }
        [['c-f1i',0],['c-f2i',1],['c-f3i',2]].forEach(function(m){ var s=$(m[0]); fillIconSelect(s,featIcons[m[1]]); s.addEventListener('change',function(){ featIcons[m[1]]=this.value; renderFeatures(); if(selIds.length) updateSelbox(); }); });

        // ---- Marken-Verlauf ----
        function hexRgb(h){ h=(h||'').replace('#',''); return parseInt(h.substr(0,2),16)+','+parseInt(h.substr(2,2),16)+','+parseInt(h.substr(4,2),16); }
        function applyGrad(){
            var ov=$('pg-ov');
            if(!$('c-grad-on').checked){ ov.style.background='none'; return; }
            var a=$('c-grad-angle').value, c1=hexRgb($('c-grad-c1').value), c2=hexRgb($('c-grad-c2').value), fade=parseInt($('c-grad-fade').value,10)/100;
            ov.style.background='linear-gradient('+a+'deg, rgba('+c1+',0.92) 0%, rgba('+c2+',0.78) 46%, rgba('+c2+','+fade+') 100%)';
        }
        $('c-grad-on').addEventListener('change',applyGrad);
        $('c-grad-c1').addEventListener('input',applyGrad);
        $('c-grad-c2').addEventListener('input',applyGrad);
        $('c-grad-angle').addEventListener('input',function(){ $('c-grad-angle-val').textContent=this.value; applyGrad(); });
        $('c-grad-fade').addEventListener('input',function(){ $('c-grad-fade-val').textContent=this.value; applyGrad(); });

        $('c-photo').addEventListener('change',function(e){ var f=e.target.files[0]; if(!f) return; var r=new FileReader(); r.onload=function(){ $('pg-bg').style.backgroundImage='url('+r.result+')'; }; r.readAsDataURL(f); });
        $('c-photo-clear').addEventListener('click',function(){ $('pg-bg').style.backgroundImage=''; $('c-photo').value=''; });

        function updateQr(){
            var url=($('c-qr-url').value||'').trim(), img=$('pg-qr'); if(!url){ img.removeAttribute('src'); return; }
            try{ var qr=qrcode(0,'H'); qr.addData(url); qr.make(); var n=qr.getModuleCount(),cell=8,q=2,size=(n+q*2)*cell;
                var cv=document.createElement('canvas'); cv.width=cv.height=size; var x=cv.getContext('2d');
                x.fillStyle='#fff'; x.fillRect(0,0,size,size); x.fillStyle='#000';
                for(var r=0;r<n;r++)for(var c=0;c<n;c++) if(qr.isDark(r,c)) x.fillRect((c+q)*cell,(r+q)*cell,cell,cell);
                img.src=cv.toDataURL('image/png');
            }catch(e){ img.removeAttribute('src'); }
        }
        $('c-qr-url').addEventListener('input',updateQr);

        // ---- Gruppen ----
        function membersOfGroup(gid){ return Object.keys(groupOf).filter(function(id){ return groupOf[id]===gid && $(id); }); }
        function expand(id){ var g=groupOf[id]; return g?membersOfGroup(g):[id]; }
        function currentGroup(){ if(!selIds.length) return null; var g=groupOf[selIds[0]]; if(!g) return null;
            for(var i=0;i<selIds.length;i++) if(groupOf[selIds[i]]!==g) return null; return g; }
        function refreshGroupMarks(){ Array.prototype.forEach.call(document.querySelectorAll('.pb'),function(b){ b.classList.toggle('pg-grouped', !!groupOf[b.id]); }); }

        // ---- Auswahl / Selbox / Panel ----
        function updateSelbox(){ if(!selIds.length){ selbox.style.display='none'; return; }
            var b=bbox(selIds); selbox.style.left=b.x+'px'; selbox.style.top=b.y+'px'; selbox.style.width=b.w+'px'; selbox.style.height=b.h+'px'; selbox.style.display='block'; }
        function show(rowId,cond){ $(rowId).style.display=cond?'block':'none'; }
        function buildImgSelect(cur){
            var opts='<option value="">— kein Bild —</option>'+Object.keys(LOGOS).map(function(k){ return '<option value="'+k+'"'+(k===cur?' selected':'')+'>'+k+'</option>'; }).join('');
            opts+='<option value="__up__">⬆ Eigenes Bild hochladen…</option>';
            $('pg-el-img').innerHTML=opts;
        }
        function showPanel(){
            var n=selIds.length, panel=$('pg-sel-panel');
            if(!n){ panel.style.display='none'; return; }
            panel.style.display='block';
            var grp=currentGroup(), one=(n===1)?selIds[0]:null, k=one?kindOf(one):null;
            $('pg-sel-name').textContent = (n===1)?('Ausgewählt: '+$(one).getAttribute('data-name'))
                : (grp?('Gruppe · '+n+' Elemente'):(n+' Elemente ausgewählt'));
            show('pg-scale-row', n===1);
            if(n===1){ $('pg-sel-scale').value=Math.round(pos[one].s*100); $('pg-sel-scale-val').textContent=Math.round(pos[one].s*100); }
            // kind-spezifische Zeilen
            var isCustom=(k==='custom');
            show('pg-text-row', isCustom);
            show('pg-icon-row', k==='dcard'||isCustom);
            show('pg-img-row', k==='logo'||k==='coop'||isCustom);
            show('pg-cap-row', k==='coop');
            show('pg-tile-row', k==='logo'||k==='coop'||k==='dcard'||isCustom);
            if(n===1){
                var m=meta[one]||{};
                if($('pg-icon-row').style.display!=='none'){ fillIconSelect($('pg-el-icon'), m.icon||''); $('pg-el-icon-prev').innerHTML=(m.icon&&ICONS[m.icon])?ICONS[m.icon]:''; }
                if($('pg-img-row').style.display!=='none'){ buildImgSelect(m.img||''); }
                if($('pg-cap-row').style.display!=='none'){ $('pg-el-cap').value=m.cap||''; }
                if($('pg-tile-row').style.display!=='none'){ $('pg-el-tile').checked=!!m.tile; }
                if(isCustom){ $('pg-el-text').value=$(one).querySelector('.pg-ct-text').textContent; }
            }
            $('pg-group').style.display=(n>1 && !grp)?'inline-flex':'none';
            $('pg-ungroup').style.display=(grp)?'inline-flex':'none';
            $('pg-del-block').style.display=isCustom?'inline-flex':'none';
        }
        function setSel(ids){ selIds=ids.filter(function(id){ return $(id) && $(id).style.display!=='none'; });
            sel=(selIds.length===1)?selIds[0]:null; updateSelbox(); showPanel(); }
        function toggle(id){ var grp=expand(id), isIn=selIds.indexOf(id)!==-1;
            if(isIn){ selIds=selIds.filter(function(x){ return grp.indexOf(x)===-1; }); }
            else { grp.forEach(function(x){ if(selIds.indexOf(x)===-1) selIds.push(x); }); }
            setSel(selIds); }
        function deselect(){ selIds=[]; sel=null; selbox.style.display='none'; $('pg-sel-panel').style.display='none'; }
        $('pg-deselect').addEventListener('click',deselect);
        $('pg-sel-scale').addEventListener('input',function(){ if(selIds.length!==1) return; pos[selIds[0]].s=parseInt(this.value,10)/100; $('pg-sel-scale-val').textContent=this.value; applyBlock(selIds[0]); updateSelbox(); });

        // Element-Editoren (Selektions-Panel)
        $('pg-el-text').addEventListener('input',function(){ if(sel&&kindOf(sel)==='custom'){ $(sel).querySelector('.pg-ct-text').textContent=this.value; updateSelbox(); } });
        $('pg-el-icon').addEventListener('change',function(){ if(!sel) return; meta[sel]=meta[sel]||{}; meta[sel].icon=this.value; $('pg-el-icon-prev').innerHTML=(this.value&&ICONS[this.value])?ICONS[this.value]:''; renderEl(sel); updateSelbox(); });
        $('pg-el-img').addEventListener('change',function(){ if(!sel) return; if(this.value==='__up__'){ this.value=meta[sel]&&meta[sel].img?meta[sel].img:''; $('pg-el-img-file').click(); return; } meta[sel]=meta[sel]||{}; meta[sel].img=this.value; renderEl(sel); updateSelbox(); });
        $('pg-el-img-file').addEventListener('change',function(e){ var f=e.target.files[0]; if(!f||!sel) return; var r=new FileReader(); r.onload=function(){ var key='Eigenes Bild '+(++uploadN); LOGOS[key]=r.result; meta[sel]=meta[sel]||{}; meta[sel].img=key; buildImgSelect(key); renderEl(sel); updateSelbox(); }; r.readAsDataURL(f); this.value=''; });
        $('pg-el-cap').addEventListener('input',function(){ if(!sel) return; meta[sel]=meta[sel]||{}; meta[sel].cap=this.value; renderEl(sel); updateSelbox(); });
        $('pg-el-tile').addEventListener('change',function(){ if(!sel) return; meta[sel]=meta[sel]||{}; meta[sel].tile=this.checked; renderEl(sel); updateSelbox(); });

        $('pg-group').addEventListener('click',function(){ if(selIds.length<2) return; var gid='g'+(++groupSeq); selIds.forEach(function(id){ groupOf[id]=gid; }); refreshGroupMarks(); showPanel(); });
        $('pg-ungroup').addEventListener('click',function(){ selIds.forEach(function(id){ delete groupOf[id]; }); refreshGroupMarks(); showPanel(); });

        // ---- Snapping (bbox-basiert) ----
        function snap(nx,ny,w,h,ex){
            var TH=12; var mv=[nx,nx+w/2,nx+w], mh=[ny,ny+h/2,ny+h];
            var cV=[PAD,PAD+curW/2,PAD+curW], cH=[PAD,PAD+curH/2,PAD+curH];
            Object.keys(pos).forEach(function(o){ if(ex.indexOf(o)!==-1||$(o).style.display==='none') return; var rr=rect(o); cV.push(rr.x,rr.x+rr.w/2,rr.x+rr.w); cH.push(rr.y,rr.y+rr.h/2,rr.y+rr.h); });
            var ox=nx, oy=ny, gvx=null, ghy=null, bd=TH+1;
            mv.forEach(function(m){ cV.forEach(function(c){ var d=Math.abs(m-c); if(d<bd){ bd=d; ox=nx+(c-m); gvx=c; } }); });
            bd=TH+1;
            mh.forEach(function(m){ cH.forEach(function(c){ var d=Math.abs(m-c); if(d<bd){ bd=d; oy=ny+(c-m); ghy=c; } }); });
            if(gvx!==null){ gv.style.left=gvx+'px'; gv.style.top=PAD+'px'; gv.style.height=curH+'px'; gv.style.display='block'; } else gv.style.display='none';
            if(ghy!==null){ gh.style.top=ghy+'px'; gh.style.left=PAD+'px'; gh.style.width=curW+'px'; gh.style.display='block'; } else gh.style.display='none';
            return {x:Math.round(ox),y:Math.round(oy)};
        }
        function hideGuides(){ gv.style.display='none'; gh.style.display='none'; }

        // ---- Drag (move) + Resize (handle) ----
        var mode=null, st=null;
        function onDown(id,e){
            e.preventDefault();
            if(e.shiftKey){ toggle(id); return; }
            if(selIds.indexOf(id)===-1) setSel(expand(id));
            var sc=scene._scale||1; mode='move';
            var b=bbox(selIds), orig={}; selIds.forEach(function(x){ orig[x]={x:pos[x].x,y:pos[x].y}; });
            st={sx:e.clientX,sy:e.clientY,sc:sc,orig:orig,bx:b.x,by:b.y,bw:b.w,bh:b.h,ids:selIds.slice()};
            selIds.forEach(function(x){ $(x).classList.add('dragging'); });
        }
        function attach(b){ b.addEventListener('mousedown',function(e){ if(e.target===$('pg-selbox-h')) return; onDown(b.id,e); }); }
        Array.prototype.forEach.call(document.querySelectorAll('.pb'),attach);

        $('pg-selbox-h').addEventListener('mousedown',function(e){ e.preventDefault(); e.stopPropagation(); if(!selIds.length) return;
            mode='resize'; var b=bbox(selIds), orig={}; selIds.forEach(function(x){ orig[x]={x:pos[x].x,y:pos[x].y,s:pos[x].s}; });
            st={sc:scene._scale||1,b0:b,orig:orig,ids:selIds.slice()};
        });

        window.addEventListener('mousemove',function(e){
            if(!mode||!st) return;
            var sc=scene._scale||1;
            if(mode==='move'){
                var dx=(e.clientX-st.sx)/st.sc, dy=(e.clientY-st.sy)/st.sc;
                var sn=snap(st.bx+dx, st.by+dy, st.bw, st.bh, st.ids);
                var adx=sn.x-st.bx, ady=sn.y-st.by;
                st.ids.forEach(function(x){ pos[x].x=st.orig[x].x+adx; pos[x].y=st.orig[x].y+ady; applyBlock(x); });
                updateSelbox();
            } else if(mode==='resize'){
                var srect=scene.getBoundingClientRect();
                var px=(e.clientX-srect.left)/sc, py=(e.clientY-srect.top)/sc;
                var k=Math.max((px-st.b0.x)/st.b0.w,(py-st.b0.y)/st.b0.h);
                if(!isFinite(k)||k<=0) k=0.05;
                st.ids.forEach(function(x){ var o=st.orig[x]; var ns=Math.max(0.2,Math.min(4,o.s*k));
                    pos[x].s=ns; pos[x].x=st.b0.x+(o.x-st.b0.x)*k; pos[x].y=st.b0.y+(o.y-st.b0.y)*k; applyBlock(x); });
                updateSelbox();
                if(selIds.length===1){ $('pg-sel-scale').value=Math.round(pos[selIds[0]].s*100); $('pg-sel-scale-val').textContent=Math.round(pos[selIds[0]].s*100); }
            }
        });
        window.addEventListener('mouseup',function(){ if(mode==='move'&&st){ st.ids.forEach(function(x){ if($(x)) $(x).classList.remove('dragging'); }); } mode=null; st=null; hideGuides(); });

        // Klick auf leere Flaeche = Auswahl aufheben
        scene.addEventListener('mousedown',function(e){ var t=e.target;
            if((t===scene||t===art||t===$('pg-bg')||t===$('pg-ov')) && !e.shiftKey) deselect(); });

        // ---- Eigene Elemente ----
        function addTile(){
            customN++; var id='custom'+customN;
            var b=document.createElement('div'); b.className='pb'; b.id=id; b.setAttribute('data-name','Eigenes Element '+customN); b.setAttribute('data-kind','custom');
            b.innerHTML='<div class="pg-ctile"><span class="pg-ct-ic"></span><img class="pg-ct-img" style="display:none" alt=""><span class="pg-ct-text">Neuer Text</span></div>';
            scene.appendChild(b);
            meta[id]={tile:true,icon:'',img:''};
            pos[id]={x:Math.round(PAD+curW/2-120),y:Math.round(PAD+curH/2-30),s:1};
            attach(b); renderCustom(id); applyBlock(id); setSel([id]);
        }
        $('c-add-tile').addEventListener('click',addTile);
        $('pg-del-block').addEventListener('click',function(){ if(!sel||kindOf(sel)!=='custom') return; var id=sel; deselect(); delete groupOf[id]; delete meta[id]; $(id).remove(); delete pos[id]; });

        // ---- Format + Zoom + Fit ----
        function refit(){ fitScale = stage.clientWidth / sceneW; }
        function applyView(){
            var ds = fitScale*zoom;
            scene.style.transform='scale('+ds+')'; scene._scale=ds;
            canvas.style.width=(sceneW*ds)+'px'; canvas.style.height=(sceneH*ds)+'px';
            var over = ds>fitScale+0.001;
            stage.style.height=(sceneH*ds + (over?18:0))+'px';
            stage.style.overflowX = over?'auto':'hidden'; stage.style.overflowY='hidden';
            $('c-zoom-val').textContent=Math.round(zoom*100)+' %';
        }
        function setZoom(z){ zoom=Math.max(0.5,Math.min(4,z)); applyView(); }
        function applyFormat(){
            var f=FORMATS[$('c-format').value]||FORMATS.portrait; curW=f.w; curH=f.h;
            sceneW=curW+2*PAD; sceneH=curH+2*PAD;
            scene.style.width=sceneW+'px'; scene.style.height=sceneH+'px';
            art.style.left=PAD+'px'; art.style.top=PAD+'px'; art.style.width=curW+'px'; art.style.height=curH+'px';
            refit(); applyView(); if(selIds.length) updateSelbox();
        }
        $('c-format').addEventListener('change',applyFormat);
        $('c-zoom-in').addEventListener('click',function(){ setZoom(zoom+0.25); });
        $('c-zoom-out').addEventListener('click',function(){ setZoom(zoom-0.25); });
        $('c-zoom-fit').addEventListener('click',function(){ setZoom(1); stage.scrollLeft=0; });
        stage.addEventListener('wheel',function(e){ if(!e.ctrlKey&&!e.metaKey) return; e.preventDefault(); setZoom(zoom*(e.deltaY<0?1.1:0.9)); }, {passive:false});
        var lastW=0;
        try{ new ResizeObserver(function(){ if(stage.clientWidth!==lastW){ lastW=stage.clientWidth; refit(); applyView(); if(selIds.length) updateSelbox(); } }).observe(stage); }catch(e){ window.addEventListener('resize',function(){ refit(); applyView(); }); }

        function baseDefaults(){ var o={}; Object.keys(DEFAULTS).forEach(function(k){ var d=DEFAULTS[k]; o[k]={x:d.x+PAD,y:d.y+PAD,s:d.s}; }); return o; }
        $('c-reset').addEventListener('click',function(){
            Object.keys(pos).forEach(function(id){ if(/^custom/.test(id)){ var el=$(id); if(el) el.remove(); } });
            pos=baseDefaults(); groupOf={}; meta=baseMeta(); featIcons=['shoe','stopwatch','leaf'];
            [['c-f1i',0],['c-f2i',1],['c-f3i',2]].forEach(function(m){ fillIconSelect($(m[0]),featIcons[m[1]]); });
            refreshGroupMarks(); renderAll(); applyAll(); deselect();
        });

        // ---- Export (nur Poster-Bereich) ----
        $('c-export').addEventListener('click',async function(){
            var s=$('c-status'); s.textContent='⏳ Rendert …';
            selbox.style.display='none'; hideGuides();
            var marks=Array.prototype.slice.call(document.querySelectorAll('.pb.pg-grouped'));
            marks.forEach(function(b){ b.classList.remove('pg-grouped'); });
            var sv=scene.style.transform, sh=stage.style.height, sox=stage.style.overflowX; scene.style.transform='none';
            try{
                if(document.fonts&&document.fonts.ready) await document.fonts.ready;
                var full=await html2canvas(scene,{scale:2,useCORS:false,backgroundColor:null,logging:false});
                var out=document.createElement('canvas'); out.width=curW*2; out.height=curH*2;
                var cx=out.getContext('2d'); cx.fillStyle='#007230'; cx.fillRect(0,0,out.width,out.height);
                cx.drawImage(full, PAD*2, PAD*2, curW*2, curH*2, 0, 0, curW*2, curH*2);
                var a=document.createElement('a'); a.download='marktlauf-poster.png'; a.href=out.toDataURL('image/png'); a.click();
                s.textContent='✓ Exportiert ('+curW+'×'+curH+').';
            }catch(err){ s.textContent='⚠️ Fehler beim Export: '+err; }
            finally{ scene.style.transform=sv; stage.style.height=sh; stage.style.overflowX=sox; marks.forEach(function(b){ b.classList.add('pg-grouped'); }); if(selIds.length) updateSelbox(); }
        });

        // Init
        pos=baseDefaults(); renderAll(); applyFormat(); applyAll(); updateQr(); lastW=stage.clientWidth;

        var bg=$('burger-btn'), sb=$('sidebar'), ov=$('sidebar-overlay');
        if(bg){ bg.addEventListener('click',function(){ sb.classList.toggle('open'); ov.classList.toggle('active'); });
                ov.addEventListener('click',function(){ sb.classList.remove('open'); ov.classList.remove('active'); }); }
    })();
    </script>
</body>
</html>
