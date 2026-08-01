<?php
/**
 * Grafik-Vorlagen-Tool (dashboard-native, Option 2 aus grafik-vorlagen-tool-spec.md).
 * Nicht-Techniker-Weg: Vorlage waehlen -> Text tippen + Bild waehlen -> PNG-Export.
 * Kein Freeform-Editor (das bleibt poster_generator.php als Fallback).
 *
 * Erste Vorlage: "Anmeldung geoeffnet". Render-Pipeline = snapDOM (dpr:1), 1:1 zu
 * social_orchestrator.php. Fonts self-hosted (assets/fonts/, kein CDN im Prod-Code).
 *
 * Palette: bewusst die waermere PLAKAT-Palette (Proof "Anmeldung geoeffnet", abgenommen
 * 2026-07-31), NICHT die Website-CI-Tokens (#009640). Der Palette-Kanon fuers Gesamtbild
 * ist laut Spec §3 noch offen -> hier lokal als .vt-card-Custom-Props (eine Stelle).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

// Logo-Assets (fix in v1; langfristig tauschbar = Folge-Schritt laut Spec §3/§4).
$logoWortmarke = '../assets/images/Marktlauf-Logo-Schrift-1180x579 freigestellt.png';
$logoAtsv      = '../assets/images/ATSV_Logo-750x968.png';
$logoGemeinde  = '../assets/images/Wort-u-Bildmarke-Gemeinde.png';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Grafik-Vorlagen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* --- Self-hosted Fonts (Strato-robust, kein externes CDN) --- */
        @font-face {
            font-family: 'Fredoka'; font-style: normal; font-weight: 500 700;
            font-display: swap; src: url(../assets/fonts/fredoka-latin.woff2) format('woff2');
        }
        @font-face {
            font-family: 'Poppins'; font-style: normal; font-weight: 400;
            font-display: swap; src: url(../assets/fonts/poppins-400-latin.woff2) format('woff2');
        }
        @font-face {
            font-family: 'Poppins'; font-style: normal; font-weight: 600;
            font-display: swap; src: url(../assets/fonts/poppins-600-latin.woff2) format('woff2');
        }

        /* --- Werkzeug-Layout: Steuerung links, Vorschau rechts --- */
        .vt-split { display: grid; grid-template-columns: minmax(320px, 420px) 1fr; gap: 1.25rem; align-items: start; }
        @media (max-width: 1000px) { .vt-split { grid-template-columns: 1fr; } }
        .vt-panel { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.25rem; }
        .vt-panel h2 { font-size: 1rem; margin: 0 0 0.9rem; }
        .vt-field { margin-bottom: 0.85rem; }
        .vt-field label { display: block; font-size: 0.82rem; color: var(--text-light); margin-bottom: 0.25rem; }
        .vt-field input[type="text"], .vt-field select {
            width: 100%; padding: var(--control-pad-y) var(--control-pad-x); border: 1px solid var(--border);
            border-radius: var(--radius); font-size: 0.9rem; box-sizing: border-box; font-family: inherit;
        }
        .vt-hint { font-size: 0.78rem; color: var(--text-light); margin: 0.2rem 0 0; line-height: 1.45; }
        .vt-row { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .vt-seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .vt-seg label { margin: 0; padding: 0.4rem 0.75rem; font-size: 0.85rem; cursor: pointer; color: var(--text); background: var(--white); }
        .vt-seg input { display: none; }
        .vt-seg input:checked + label { background: var(--primary); color: #fff; }
        .vt-photo-picker { display: none; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; max-height: 220px; overflow-y: auto; }
        .vt-thumb { width: 84px; cursor: pointer; text-align: center; }
        .vt-thumb img { width: 84px; height: 84px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); display: block; }
        .vt-thumb span { display: block; font-size: 0.68rem; color: var(--text-light); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .vt-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
        .vt-error { display: none; color: var(--error); font-size: 0.85rem; margin-top: 0.5rem; }

        /* --- Vorschau-Bereich --- */
        .vt-preview-wrap { position: sticky; top: 1rem; }
        #vt-card-img { max-width: 100%; width: auto; max-height: 70vh; border-radius: 8px; box-shadow: var(--shadow-card); display: block; }
        .vt-preview-empty { color: var(--text-light); font-size: 0.9rem; padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border); border-radius: 8px; }
        .vt-caption { font-size: 0.82rem; color: var(--text-light); margin: 0 0 0.5rem; }

        /* --- Off-screen Render-Buehne (echte 1080px, NICHT display:none) --- */
        .vt-stage { position: absolute; left: -9999px; top: 0; width: 1080px; overflow: visible; }

        /* --- Die Vorlage "Anmeldung geoeffnet" (Plakat-Palette, eine SSOT-Stelle) --- */
        .vt-card {
            --vt-green:      #2f8f3f;
            --vt-green-dark: #1f6b2c;
            --vt-gold:       #f4b81e;
            --vt-teal:       #0e6f88;
            --vt-ink:        #10331a;
            width: 1080px; height: 1350px; position: relative; overflow: hidden;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #fff; display: flex; flex-direction: column;
        }
        .vt-card .vt-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
        .vt-card.has-photo .vt-bg { display: block; }
        .vt-card .vt-overlay { position: absolute; inset: 0;
            background: linear-gradient(150deg, var(--vt-green) 0%, var(--vt-green-dark) 100%); }
        .vt-card.has-photo .vt-overlay {
            background: linear-gradient(160deg, rgba(0,0,0,0.30) 0%, rgba(16,51,26,0.82) 100%); }
        .vt-card .vt-inner { position: relative; z-index: 2; display: flex; flex-direction: column;
            height: 100%; padding: 72px 72px 64px; }

        .vt-card .vt-logos { display: flex; align-items: center; gap: 34px; }
        .vt-card .vt-logos img { height: 96px; width: auto; object-fit: contain; }
        .vt-card .vt-logos img.vt-logo-mark { height: 118px; }

        .vt-card .vt-body { flex: 1 1 auto; display: flex; flex-direction: column; justify-content: center; }
        .vt-card .vt-eyebrow { font-family: 'Fredoka', sans-serif; font-weight: 600; font-size: 34px;
            letter-spacing: 3px; text-transform: uppercase; color: var(--vt-gold); margin-bottom: 14px; }
        .vt-card .vt-headline { font-family: 'Fredoka', sans-serif; font-weight: 700; font-size: 118px;
            line-height: 0.98; letter-spacing: -1px; margin: 0 0 34px; text-shadow: 0 4px 18px rgba(0,0,0,0.25); }
        .vt-card .vt-features { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 40px; }
        .vt-card .vt-chip { font-family: 'Fredoka', sans-serif; font-weight: 600; font-size: 34px;
            background: rgba(255,255,255,0.16); border: 2px solid rgba(255,255,255,0.4);
            border-radius: 999px; padding: 12px 30px; }
        .vt-card .vt-cta { align-self: flex-start; font-family: 'Fredoka', sans-serif; font-weight: 700;
            font-size: 46px; color: var(--vt-ink); background: var(--vt-gold);
            border-radius: 18px; padding: 20px 44px; box-shadow: 0 10px 26px rgba(0,0,0,0.28); }

        .vt-card .vt-footer { display: flex; align-items: flex-end; justify-content: space-between; gap: 32px; }
        .vt-card .vt-info { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 38px; line-height: 1.5; }
        .vt-card .vt-info .vt-info-line { display: flex; align-items: center; gap: 14px; }
        .vt-card .vt-coop { font-size: 24px; color: rgba(255,255,255,0.85); }
        .vt-card .vt-coop img { height: 76px; width: auto; display: block; margin-top: 8px;
            background: #fff; border-radius: 10px; padding: 8px; }
        .vt-card .vt-qr { text-align: center; }
        .vt-card .vt-qr img { width: 190px; height: 190px; background: #fff; border-radius: 14px; padding: 12px; display: block; }
        .vt-card .vt-qr span { display: block; font-family: 'Fredoka', sans-serif; font-weight: 600; font-size: 26px; margin-top: 10px; }
        .vt-card .vt-sponsor { font-size: 22px; color: rgba(255,255,255,0.8); margin-top: 18px; text-align: center; }
    </style>
</head>
<body>
<?php $activeNav = 'vorlagen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Grafik-Vorlagen</h1>
            </header>

            <p class="vt-hint" style="margin-bottom:1rem;max-width:760px;">
                Fertige Vorlage befuellen &amp; als Bild exportieren &mdash; ohne Design-Kenntnisse.
                Erste Vorlage: <strong>&bdquo;Anmeldung geoeffnet&ldquo;</strong>. Weitere Anlaesse folgen.
                Fuer freie Plakate bleibt der <a href="poster_generator.php">Plakat-Generator</a>.
            </p>

            <div class="vt-split">
                <!-- ============ Steuerung ============ -->
                <div class="vt-panel">
                    <h2>1 &middot; Vorlage befuellen</h2>

                    <div class="vt-field">
                        <label for="vt-format">Format</label>
                        <select id="vt-format">
                            <option value="portrait">Portrait 1080&times;1350 (Feed)</option>
                            <option value="square">Quadratisch 1080&times;1080</option>
                            <option value="story">Story 1080&times;1920</option>
                        </select>
                    </div>

                    <div class="vt-field">
                        <label>Hintergrund</label>
                        <div class="vt-seg" id="vt-bg-mode">
                            <input type="radio" name="bgmode" id="bg-gradient" value="gradient" checked>
                            <label for="bg-gradient">Farbverlauf</label>
                            <input type="radio" name="bgmode" id="bg-photo" value="photo">
                            <label for="bg-photo">Foto</label>
                        </div>
                        <div id="vt-photo-block" style="display:none;margin-top:0.5rem;">
                            <div class="vt-row">
                                <button type="button" class="btn btn-secondary" id="vt-pick-photo">Foto aus Ablage waehlen</button>
                                <button type="button" class="btn btn-secondary" id="vt-clear-photo" style="display:none;">Foto entfernen</button>
                            </div>
                            <span class="vt-hint" id="vt-photo-name">kein Foto gewaehlt</span>
                            <div class="vt-photo-picker" id="vt-photo-picker"></div>
                            <form method="post" action="api/file_upload.php" enctype="multipart/form-data" style="margin-top:0.6rem;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="bereich" value="orga">
                                <input type="hidden" name="kategorie" value="presse">
                                <input type="hidden" name="redirect_after" value="vorlagen.php">
                                <div class="vt-row">
                                    <input type="file" name="datei" accept="image/png,image/jpeg" required style="font-size:0.85rem;">
                                    <button type="submit" class="btn btn-secondary">Hochladen</button>
                                </div>
                                <span class="vt-hint">Neues Foto landet in der Datei-Ablage (Kategorie Presse) und ist danach oben waehlbar.</span>
                            </form>
                        </div>
                    </div>

                    <div class="vt-field">
                        <label for="vt-eyebrow">Ueberzeile</label>
                        <input type="text" id="vt-eyebrow" maxlength="40" value="Marktlauf 2026">
                    </div>
                    <div class="vt-field">
                        <label for="vt-headline">Schlagzeile</label>
                        <input type="text" id="vt-headline" maxlength="40" value="Anmeldung geoeffnet">
                    </div>
                    <div class="vt-field">
                        <label for="vt-features">Distanzen / Badges (mit Komma trennen)</label>
                        <input type="text" id="vt-features" maxlength="80" value="5 km, 10 km, Bambini">
                    </div>
                    <div class="vt-field">
                        <label for="vt-cta">Aktions-Button (CTA)</label>
                        <input type="text" id="vt-cta" maxlength="30" value="Jetzt anmelden">
                    </div>
                    <div class="vt-field">
                        <label for="vt-datum">Datum</label>
                        <input type="text" id="vt-datum" maxlength="40" placeholder="z. B. So. 12. Juli 2026">
                    </div>
                    <div class="vt-field">
                        <label for="vt-ort">Ort</label>
                        <input type="text" id="vt-ort" maxlength="40" value="Kirchseeon">
                    </div>
                    <div class="vt-field">
                        <label for="vt-sponsor">Sponsorzeile (optional)</label>
                        <input type="text" id="vt-sponsor" maxlength="80" placeholder="z. B. Praesentiert von …">
                    </div>

                    <h2 style="margin-top:1.3rem;">2 &middot; QR-Code (optional)</h2>
                    <div class="vt-field">
                        <label for="vt-qr-url">Ziel-Link (z. B. Anmeldeseite)</label>
                        <input type="text" id="vt-qr-url" placeholder="https://…">
                        <span class="vt-hint">Leer lassen = kein QR-Code auf der Grafik.</span>
                    </div>
                    <div class="vt-field">
                        <label for="vt-qr-label">QR-Beschriftung</label>
                        <input type="text" id="vt-qr-label" maxlength="24" value="Jetzt anmelden">
                    </div>

                    <div class="vt-actions">
                        <button type="button" class="btn btn-primary" id="vt-render">Grafik erzeugen</button>
                        <button type="button" class="btn btn-secondary" id="vt-download" style="display:none;">PNG herunterladen</button>
                    </div>
                    <div class="vt-error" id="vt-error"></div>
                </div>

                <!-- ============ Vorschau ============ -->
                <div class="vt-preview-wrap">
                    <p class="vt-caption" id="vt-caption">Vorschau erscheint nach &bdquo;Grafik erzeugen&ldquo;.</p>
                    <div class="vt-preview-empty" id="vt-preview-empty">Noch keine Vorschau &mdash; links befuellen und &bdquo;Grafik erzeugen&ldquo; klicken.</div>
                    <img id="vt-card-img" alt="Vorschau der erzeugten Grafik" style="display:none;">
                </div>
            </div>

            <!-- Off-screen Render-Buehne (echte Pixel; darf nicht display:none sein) -->
            <div class="vt-stage" aria-hidden="true">
                <div class="vt-card" id="vt-card">
                    <img class="vt-bg" id="vt-bg" alt="">
                    <div class="vt-overlay"></div>
                    <div class="vt-inner">
                        <div class="vt-logos">
                            <img class="vt-logo-mark" id="vt-logo-mark" src="<?= htmlspecialchars($logoWortmarke) ?>" alt="">
                            <img id="vt-logo-atsv" src="<?= htmlspecialchars($logoAtsv) ?>" alt="">
                        </div>
                        <div class="vt-body">
                            <div class="vt-eyebrow" id="c-eyebrow"></div>
                            <h1 class="vt-headline" id="c-headline"></h1>
                            <div class="vt-features" id="c-features"></div>
                            <div class="vt-cta" id="c-cta"></div>
                        </div>
                        <div class="vt-footer">
                            <div>
                                <div class="vt-info" id="c-info">
                                    <div class="vt-info-line" id="c-datum-line"><span>📅</span><span id="c-datum"></span></div>
                                    <div class="vt-info-line" id="c-ort-line"><span>📍</span><span id="c-ort"></span></div>
                                </div>
                                <div class="vt-sponsor" id="c-sponsor"></div>
                            </div>
                            <div class="vt-coop">In Kooperation mit
                                <img id="vt-logo-gemeinde" src="<?= htmlspecialchars($logoGemeinde) ?>" alt="">
                            </div>
                            <div class="vt-qr" id="c-qr" style="display:none;">
                                <img id="c-qr-img" alt="">
                                <span id="c-qr-label"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/snapdom.js"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function() {
        const FORMATS = {
            square:   { w: 1080, h: 1080, label: 'Quadratisch 1080×1080' },
            portrait: { w: 1080, h: 1350, label: 'Portrait 1080×1350' },
            story:    { w: 1080, h: 1920, label: 'Story 1080×1920' },
        };
        const $ = id => document.getElementById(id);
        const card = $('vt-card');
        let lastDataUrl = null;
        let selectedPhotoUrl = '';

        // --- Foto-Modus umschalten ---
        document.querySelectorAll('input[name="bgmode"]').forEach(r => {
            r.addEventListener('change', () => {
                $('vt-photo-block').style.display = (r.value === 'photo' && r.checked) ? 'block' : ($('bg-photo').checked ? 'block' : 'none');
            });
        });

        // --- Foto-Picker aus der Datei-Ablage (same-origin -> snapDOM-tauglich) ---
        const picker = $('vt-photo-picker');
        $('vt-pick-photo').addEventListener('click', async () => {
            if (picker.style.display === 'flex') { picker.style.display = 'none'; return; }
            picker.textContent = 'laedt …'; picker.style.display = 'flex';
            try {
                const r = await fetch('api/dateien_images.php');
                const d = await r.json();
                picker.innerHTML = '';
                const imgs = (d.images || []);
                if (!imgs.length) { picker.textContent = 'Keine Bilder in der Ablage.'; return; }
                imgs.forEach(img => {
                    const t = document.createElement('div'); t.className = 'vt-thumb';
                    const im = document.createElement('img'); im.src = img.url; im.alt = '';
                    const nm = document.createElement('span'); nm.textContent = img.name;
                    t.appendChild(im); t.appendChild(nm);
                    t.addEventListener('click', () => {
                        selectedPhotoUrl = img.url;
                        $('vt-photo-name').textContent = img.name;
                        $('vt-clear-photo').style.display = 'inline-flex';
                        picker.style.display = 'none';
                    });
                    picker.appendChild(t);
                });
            } catch (e) { picker.textContent = 'Fehler beim Laden.'; }
        });
        $('vt-clear-photo').addEventListener('click', () => {
            selectedPhotoUrl = '';
            $('vt-photo-name').textContent = 'kein Foto gewaehlt';
            $('vt-clear-photo').style.display = 'none';
        });

        // --- Slots aus den Eingaben in die Karte schreiben ---
        function fillCard() {
            $('c-eyebrow').textContent  = $('vt-eyebrow').value.trim();
            $('c-headline').textContent = $('vt-headline').value.trim();
            $('c-cta').textContent      = $('vt-cta').value.trim();
            $('c-cta').style.display    = $('vt-cta').value.trim() ? 'inline-block' : 'none';

            const feat = $('vt-features').value.split(',').map(s => s.trim()).filter(Boolean);
            const fbox = $('c-features'); fbox.innerHTML = '';
            feat.forEach(f => { const c = document.createElement('span'); c.className = 'vt-chip'; c.textContent = f; fbox.appendChild(c); });

            const datum = $('vt-datum').value.trim();
            const ort   = $('vt-ort').value.trim();
            $('c-datum').textContent = datum;
            $('c-ort').textContent   = ort;
            $('c-datum-line').style.display = datum ? 'flex' : 'none';
            $('c-ort-line').style.display   = ort ? 'flex' : 'none';

            const sponsor = $('vt-sponsor').value.trim();
            $('c-sponsor').textContent = sponsor;
            $('c-sponsor').style.display = sponsor ? 'block' : 'none';

            // Hintergrund
            const usePhoto = $('bg-photo').checked && selectedPhotoUrl;
            card.classList.toggle('has-photo', !!usePhoto);
            if (usePhoto) { $('vt-bg').src = selectedPhotoUrl; } else { $('vt-bg').removeAttribute('src'); }
        }

        // --- QR erzeugen (self-hosted qrcode.js, gleiche Mechanik wie Social) ---
        function applyQr() {
            const url = $('vt-qr-url').value.trim();
            const wrap = $('c-qr'), img = $('c-qr-img');
            if (!url || typeof qrcode !== 'function') { wrap.style.display = 'none'; return; }
            try {
                const qr = qrcode(0, 'M'); qr.addData(url); qr.make();
                const count = qr.getModuleCount(), cell = 10, quiet = 2, size = (count + quiet * 2) * cell;
                const cv = document.createElement('canvas'); cv.width = cv.height = size;
                const ctx = cv.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, size, size);
                ctx.fillStyle = '#000';
                for (let r = 0; r < count; r++) for (let c = 0; c < count; c++) if (qr.isDark(r, c)) ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell);
                img.src = cv.toDataURL('image/png');
                $('c-qr-label').textContent = $('vt-qr-label').value.trim() || 'Jetzt anmelden';
                wrap.style.display = 'flex';
            } catch (e) { wrap.style.display = 'none'; }
        }

        function waitImg(img) {
            return new Promise(resolve => {
                if (!img.getAttribute('src') || (img.complete && img.naturalWidth)) { resolve(); return; }
                img.onload = resolve; img.onerror = resolve;
            });
        }

        // --- Rendern (snapDOM, dpr:1 zwingend; Fonts eingebettet) ---
        $('vt-render').addEventListener('click', async () => {
            const btn = $('vt-render'), err = $('vt-error');
            const fmt = FORMATS[$('vt-format').value] || FORMATS.portrait;
            btn.disabled = true; btn.textContent = '⏳ Rendert …'; err.style.display = 'none';

            fillCard();
            applyQr();
            card.style.width = fmt.w + 'px';
            card.style.height = fmt.h + 'px';

            try {
                // Fonts vor dem Rastern laden, sonst rendert snapDOM den Fallback.
                await Promise.all([
                    document.fonts.load('700 100px Fredoka'),
                    document.fonts.load('600 40px Poppins'),
                    document.fonts.load('400 40px Poppins'),
                ]);
                await document.fonts.ready;

                // Bilder (Logos, optionales Foto, QR) vorab laden, damit snapDOM sie findet.
                await Promise.all([
                    waitImg($('vt-logo-mark')), waitImg($('vt-logo-atsv')), waitImg($('vt-logo-gemeinde')),
                    (card.classList.contains('has-photo')) ? waitImg($('vt-bg')) : Promise.resolve(),
                    waitImg($('c-qr-img')),
                ]);

                const canvas = await snapdom.toCanvas(card, {
                    width: fmt.w, height: fmt.h, scale: 1, dpr: 1,
                    backgroundColor: '#1f6b2c', embedFonts: true,
                });
                lastDataUrl = canvas.toDataURL('image/png');
                $('vt-card-img').src = lastDataUrl;
                $('vt-card-img').style.display = 'block';
                $('vt-preview-empty').style.display = 'none';
                $('vt-caption').textContent = 'Vorschau (' + fmt.label + '):';
                $('vt-download').style.display = 'inline-block';
            } catch (e) {
                err.textContent = 'Render-Fehler: ' + (e && e.message ? e.message : e);
                err.style.display = 'block';
            } finally {
                btn.disabled = false; btn.textContent = 'Grafik erzeugen';
            }
        });

        $('vt-download').addEventListener('click', () => {
            if (!lastDataUrl) return;
            const a = document.createElement('a');
            a.href = lastDataUrl;
            a.download = 'marktlauf2026-anmeldung-' + $('vt-format').value + '.png';
            a.click();
        });
    })();

    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function() { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) { link.addEventListener('click', closeSidebar); });
    })();
    </script>
</body>
</html>
