<?php
/**
 * Grafik-Vorlagen-Tool (dashboard-native, Option 2 aus grafik-vorlagen-tool-spec.md).
 * Nicht-Techniker-Weg: Vorlage befuellen -> Text tippen + Bild waehlen -> PNG-Export.
 *
 * Erste Vorlage: "Anmeldung geoeffnet" (IG Portrait 1080x1350). Das Layout ist ein
 * MASSGETREUER Port des abgenommenen Proofs:
 *   intern/design-referenzen/proof-anmeldung-portrait.html  (+ proof-anmeldung.png)
 * Koordinaten/Groessen/Farben 1:1 uebernommen; nur die Text-/Bild-Slots sind befuellbar.
 * Render-Pipeline = snapDOM (dpr:1, embedFonts). Fonts self-hosted (assets/fonts/, kein CDN).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

// Assets (deploybar, ueber Prod-URL geladen). Wortmarke + Wappen sind gruen -> weisse Leiste.
$logoWortmarke = '../assets/images/marktlauf-wordmark.png';
$logoAtsv      = '../assets/images/ATSV_Logo-750x968.png';
$logoGemeinde  = '../assets/images/Wort-u-Bildmarke-Gemeinde.png';
$runner        = '../assets/images/laeufer.png';
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
        @font-face { font-family:'Fredoka'; font-style:normal; font-weight:500 700; font-display:swap; src:url(../assets/fonts/fredoka-latin.woff2) format('woff2'); }
        @font-face { font-family:'Poppins'; font-style:normal; font-weight:400; font-display:swap; src:url(../assets/fonts/poppins-400-latin.woff2) format('woff2'); }
        @font-face { font-family:'Poppins'; font-style:normal; font-weight:600; font-display:swap; src:url(../assets/fonts/poppins-600-latin.woff2) format('woff2'); }

        /* --- Werkzeug-Layout: Steuerung links, Vorschau rechts --- */
        .vt-split { display: grid; grid-template-columns: minmax(320px, 430px) 1fr; gap: 1.25rem; align-items: start; }
        @media (max-width: 1000px) { .vt-split { grid-template-columns: 1fr; } }
        .vt-panel { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.25rem; }
        .vt-panel h2 { font-size: 1rem; margin: 0 0 0.9rem; }
        .vt-panel h3 { font-size: 0.82rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; margin: 1.2rem 0 0.6rem; }
        .vt-field { margin-bottom: 0.85rem; }
        .vt-field label { display: block; font-size: 0.82rem; color: var(--text-light); margin-bottom: 0.25rem; }
        .vt-field input[type="text"], .vt-field select {
            width: 100%; padding: var(--control-pad-y) var(--control-pad-x); border: 1px solid var(--border);
            border-radius: var(--radius); font-size: 0.9rem; box-sizing: border-box; font-family: inherit;
        }
        .vt-two { display: grid; grid-template-columns: 74px 1fr; gap: 0.5rem; align-items: start; }
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

        /* --- Vorschau --- */
        .vt-preview-wrap { position: sticky; top: 1rem; }
        #vt-card-img { max-width: 100%; width: auto; max-height: 74vh; border-radius: 8px; box-shadow: var(--shadow-card); display: block; }
        .vt-preview-empty { color: var(--text-light); font-size: 0.9rem; padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border); border-radius: 8px; }
        .vt-caption { font-size: 0.82rem; color: var(--text-light); margin: 0 0 0.5rem; }

        /* --- Off-screen Render-Buehne (echte 1080px, NICHT display:none) --- */
        .vt-stage { position: absolute; left: -9999px; top: 0; width: 1080px; overflow: visible; }

        /* ============================================================
           Vorlage "Anmeldung geoeffnet" — 1:1 Port des Proofs
           (intern/design-referenzen/proof-anmeldung-portrait.html)
           ============================================================ */
        .poster {
            width: 1080px; height: 1350px; position: relative; overflow: hidden; color: #fff;
            background: linear-gradient(152deg, #1f7a3a 0%, #2f8f3f 38%, #57bd46 72%, #8ac63f 100%);
            font-family: 'Poppins', sans-serif;
        }
        .poster .bgphoto { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
        .poster .bgphoto-overlay { position: absolute; inset: 0; display: none;
            background: linear-gradient(160deg, rgba(0,0,0,0.28) 0%, rgba(16,51,26,0.80) 100%); }
        .poster.has-photo .bgphoto, .poster.has-photo .bgphoto-overlay { display: block; }
        .poster.has-photo .runner { display: none; }

        .poster .runner { position: absolute; right: 20px; bottom: 20px; width: 713px; height: 1162px;
            object-fit: contain; object-position: right bottom; filter: drop-shadow(0 8px 24px rgba(0,0,0,.18)); }
        .poster .logobar { position: absolute; top: 44px; left: 52px; display: flex; align-items: center; gap: 18px;
            background: #fff; border-radius: 22px; padding: 16px 24px; box-shadow: 0 10px 30px rgba(0,0,0,.16); z-index: 3; }
        .poster .logobar img.mark { height: 64px; width: auto; display: block; }
        .poster .logobar .sep { width: 2px; height: 56px; background: #e2e6de; }
        .poster .logobar img.atsv { height: 70px; width: auto; display: block; }
        .poster .koop { position: absolute; top: 44px; right: 52px; background: #fff; border-radius: 22px; padding: 14px 22px;
            box-shadow: 0 10px 30px rgba(0,0,0,.16); text-align: left; max-width: 360px; z-index: 3; }
        .poster .koop .kk { font-weight: 700; font-size: 15px; letter-spacing: 2px; color: #2f8f3f; text-transform: uppercase; margin-bottom: 8px; }
        .poster .koop img { height: 58px; width: auto; display: block; }
        .poster .hero { position: absolute; left: 56px; top: 230px; width: 660px; z-index: 2; }
        .poster .hero h1 { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 104px;
            line-height: .92; letter-spacing: -1px; text-transform: uppercase; text-shadow: 0 6px 20px rgba(0,0,0,.18); }
        .poster .hero .sub { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 600; font-size: 38px;
            line-height: 1.15; margin-top: 22px; color: #eafff0; }
        .poster .feat { position: absolute; left: 60px; top: 648px; width: 600px; display: flex; flex-direction: column; gap: 26px; z-index: 2; }
        .poster .frow { display: flex; align-items: center; gap: 22px; }
        .poster .fic { flex: 0 0 auto; width: 74px; height: 74px; border-radius: 50%; border: 3px solid rgba(255,255,255,.85);
            display: flex; align-items: center; justify-content: center; }
        .poster .fic svg { width: 38px; height: 38px; stroke: #fff; fill: none; stroke-width: 2.2; }
        .poster .ft b { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 600; font-size: 30px; color: #f4b81e; display: block; line-height: 1.1; }
        .poster .ft span { font-weight: 400; font-size: 23px; color: #eafff0; line-height: 1.2; }
        .poster .cta { position: absolute; left: 60px; top: 975px; width: 520px; background: #f4b81e; border-radius: 18px;
            padding: 24px 0; text-align: center; box-shadow: 0 12px 30px rgba(0,0,0,.20); z-index: 2; }
        .poster .cta span { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 40px; color: #1f7a3a; letter-spacing: .5px; text-transform: uppercase; }
        .poster .info { position: absolute; left: 56px; bottom: 56px; display: flex; gap: 22px; align-items: stretch; z-index: 2; }
        .poster .card { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.28); border-radius: 20px; padding: 22px 26px; max-width: 250px; }
        .poster .card .ic { font-size: 26px; margin-bottom: 10px; display: block; }
        .poster .card .big { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 34px; line-height: 1; }
        .poster .card .lbl { font-weight: 500; font-size: 20px; color: #eafff0; line-height: 1.25; margin-top: 4px; }
        .poster .qr { position: absolute; right: 56px; bottom: 56px; background: #fff; border-radius: 22px; padding: 22px; text-align: center;
            box-shadow: 0 12px 30px rgba(0,0,0,.20); width: 300px; z-index: 3; }
        .poster .qr .qh { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 26px; color: #1f7a3a; line-height: 1.05; margin-bottom: 14px; }
        .poster .qr img { width: 220px; height: 220px; display: block; margin: 0 auto; }
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
                Erste Vorlage: <strong>&bdquo;Anmeldung geoeffnet&ldquo;</strong> (Instagram Portrait 1080&times;1350).
                Fuer freie Plakate bleibt der <a href="poster_generator.php">Plakat-Generator</a>.
            </p>

            <div class="vt-split">
                <!-- ============ Steuerung ============ -->
                <div class="vt-panel">
                    <h2>1 &middot; Vorlage befuellen</h2>

                    <div class="vt-field">
                        <label>Hintergrund</label>
                        <div class="vt-seg" id="vt-bg-mode">
                            <input type="radio" name="bgmode" id="bg-gradient" value="gradient" checked>
                            <label for="bg-gradient">Grafik (Verlauf + L&auml;ufer)</label>
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
                                <span class="vt-hint">Neues Foto landet in der Datei-Ablage (Kategorie Presse) und ist danach oben waehlbar. Bei Foto tritt der L&auml;ufer zurueck.</span>
                            </form>
                        </div>
                    </div>

                    <h3>Kopf</h3>
                    <div class="vt-field">
                        <label for="vt-headline">Schlagzeile</label>
                        <input type="text" id="vt-headline" maxlength="40" value="Anmeldung geoeffnet!">
                    </div>
                    <div class="vt-field">
                        <label for="vt-sub">Unterzeile</label>
                        <input type="text" id="vt-sub" maxlength="70" value="Sichert euch jetzt euren Startplatz!">
                    </div>

                    <h3>Drei Punkte</h3>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f1t" maxlength="40" value="Fuer alle Altersklassen" aria-label="Punkt 1 Titel">
                        <input type="text" id="vt-f1s" maxlength="60" value="Bambini, Schueler, Jugend, Erwachsene" aria-label="Punkt 1 Text">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f2t" maxlength="40" value="Verschiedene Distanzen" aria-label="Punkt 2 Titel">
                        <input type="text" id="vt-f2s" maxlength="60" value="500 m bis 10 km" aria-label="Punkt 2 Text">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f3t" maxlength="40" value="Gemeinsam fuer Umwelt &amp; Energie" aria-label="Punkt 3 Titel">
                        <input type="text" id="vt-f3s" maxlength="60" value="Jeder Schritt zaehlt!" aria-label="Punkt 3 Text">
                    </div>
                    <span class="vt-hint">Links der goldene Titel, rechts der Untertext. Icons sind fest.</span>

                    <h3>Aktion &amp; Infos</h3>
                    <div class="vt-field">
                        <label for="vt-cta">Aktions-Button</label>
                        <input type="text" id="vt-cta" maxlength="30" value="Jetzt anmelden!">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-datum" maxlength="20" value="20.09.2026" aria-label="Datum gross">
                        <input type="text" id="vt-datum-zusatz" maxlength="40" value="Sonntag, Start 10:00 Uhr" aria-label="Datum Zusatz">
                    </div>
                    <div class="vt-field">
                        <label for="vt-ort">Ort</label>
                        <input type="text" id="vt-ort" maxlength="60" value="JEK, Westring 6, Kirchseeon">
                    </div>

                    <h3>QR-Code (optional)</h3>
                    <div class="vt-field">
                        <label for="vt-qr-url">Ziel-Link (z. B. Anmeldeseite)</label>
                        <input type="text" id="vt-qr-url" placeholder="https://…">
                        <span class="vt-hint">Leer lassen = keine QR-Karte auf der Grafik.</span>
                    </div>
                    <div class="vt-field">
                        <label for="vt-qr-label">QR-Beschriftung</label>
                        <input type="text" id="vt-qr-label" maxlength="30" value="Jetzt scannen &amp; anmelden!">
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
                <div class="poster" id="vt-card">
                    <img class="bgphoto" id="vt-bg" alt="">
                    <div class="bgphoto-overlay"></div>
                    <img class="runner" id="vt-runner" src="<?= htmlspecialchars($runner) ?>" alt="">
                    <div class="logobar">
                        <img class="mark" src="<?= htmlspecialchars($logoWortmarke) ?>" alt="Marktlauf Kirchseeon" id="vt-mark">
                        <div class="sep"></div>
                        <img class="atsv" src="<?= htmlspecialchars($logoAtsv) ?>" alt="ATSV Kirchseeon 1906" id="vt-atsv">
                    </div>
                    <div class="koop">
                        <div class="kk">In Kooperation mit</div>
                        <img src="<?= htmlspecialchars($logoGemeinde) ?>" alt="Markt Kirchseeon" id="vt-gemeinde">
                    </div>
                    <div class="hero">
                        <h1 id="c-headline"></h1>
                        <div class="sub" id="c-sub"></div>
                    </div>
                    <div class="feat">
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><path d="M2 17h16l3-3-2-2-4 1-3-4-4 2v6z"/><path d="M2 17v2h19"/></svg></div>
                            <div class="ft"><b id="c-f1t"></b><span id="c-f1s"></span></div>
                        </div>
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9M9 2h6"/></svg></div>
                            <div class="ft"><b id="c-f2t"></b><span id="c-f2s"></span></div>
                        </div>
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><path d="M11 20A7 7 0 0 1 4 13c0-5 7-9 7-9s7 4 7 9a7 7 0 0 1-7 7z"/><path d="M11 20v-9"/></svg></div>
                            <div class="ft"><b id="c-f3t"></b><span id="c-f3s"></span></div>
                        </div>
                    </div>
                    <div class="cta"><span id="c-cta"></span></div>
                    <div class="info">
                        <div class="card"><span class="ic">📅</span><div class="big" id="c-datum"></div><div class="lbl" id="c-datum-zusatz"></div></div>
                        <div class="card" id="c-ort-card"><span class="ic">📍</span><div class="lbl" id="c-ort"></div></div>
                    </div>
                    <div class="qr" id="c-qr" style="display:none;">
                        <div class="qh" id="c-qr-label"></div>
                        <img id="c-qr-img" alt="">
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/snapdom.js"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function() {
        const $ = id => document.getElementById(id);
        const card = $('vt-card');
        let lastDataUrl = null;
        let selectedPhotoUrl = '';

        document.querySelectorAll('input[name="bgmode"]').forEach(r => {
            r.addEventListener('change', () => { $('vt-photo-block').style.display = $('bg-photo').checked ? 'block' : 'none'; });
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
            $('c-headline').textContent = $('vt-headline').value.trim();
            $('c-sub').textContent      = $('vt-sub').value.trim();
            $('c-f1t').textContent = $('vt-f1t').value.trim(); $('c-f1s').textContent = $('vt-f1s').value.trim();
            $('c-f2t').textContent = $('vt-f2t').value.trim(); $('c-f2s').textContent = $('vt-f2s').value.trim();
            $('c-f3t').textContent = $('vt-f3t').value.trim(); $('c-f3s').textContent = $('vt-f3s').value.trim();
            $('c-cta').textContent = $('vt-cta').value.trim();
            $('c-datum').textContent = $('vt-datum').value.trim();
            $('c-datum-zusatz').textContent = $('vt-datum-zusatz').value.trim();
            $('c-ort').textContent = $('vt-ort').value.trim();
            $('c-ort-card').style.display = $('vt-ort').value.trim() ? 'block' : 'none';

            const usePhoto = $('bg-photo').checked && selectedPhotoUrl;
            card.classList.toggle('has-photo', !!usePhoto);
            if (usePhoto) { $('vt-bg').src = selectedPhotoUrl; } else { $('vt-bg').removeAttribute('src'); }
        }

        // --- QR erzeugen (self-hosted qrcode.js) ---
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
                $('c-qr-label').textContent = $('vt-qr-label').value.trim() || 'Jetzt scannen & anmelden!';
                wrap.style.display = 'block';
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
            btn.disabled = true; btn.textContent = '⏳ Rendert …'; err.style.display = 'none';

            fillCard();
            applyQr();

            try {
                await Promise.all([
                    document.fonts.load('700 100px Fredoka'),
                    document.fonts.load('600 40px Poppins'),
                    document.fonts.load('400 40px Poppins'),
                ]);
                await document.fonts.ready;

                await Promise.all([
                    waitImg($('vt-mark')), waitImg($('vt-atsv')), waitImg($('vt-gemeinde')),
                    card.classList.contains('has-photo') ? waitImg($('vt-bg')) : waitImg($('vt-runner')),
                    waitImg($('c-qr-img')),
                ]);

                const canvas = await snapdom.toCanvas(card, {
                    width: 1080, height: 1350, scale: 1, dpr: 1,
                    backgroundColor: '#1f7a3a', embedFonts: true,
                });
                lastDataUrl = canvas.toDataURL('image/png');
                $('vt-card-img').src = lastDataUrl;
                $('vt-card-img').style.display = 'block';
                $('vt-preview-empty').style.display = 'none';
                $('vt-caption').textContent = 'Vorschau (Portrait 1080×1350):';
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
            a.download = 'marktlauf2026-anmeldung-portrait.png';
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
