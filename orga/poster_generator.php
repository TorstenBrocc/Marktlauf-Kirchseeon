<?php
/**
 * Kampagnen-Poster-Generator (Entwurf) — Marketing-Qualitaet aus dem Tool.
 * Formate (Portrait/Quadrat/Story), editierbare Inhalte, Logos + Sponsoren auf
 * weissen Kacheln, QR per Klick platzierbar, Export als PNG (2x).
 */
declare(strict_types=1);
require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
$pdo     = getDbConnection();
$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

// Sponsorenlogos automatisch aus dem Ordner (variabel)
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
        .pg-row { margin-bottom: 0.7rem; }
        .pg-row label { font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.2rem; display: block; }
        .pg-row input[type=text], .pg-row input[type=url], .pg-row select { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; background:#fff; }
        .pg-row input + input { margin-top: 0.35rem; }
        .pg-hint { font-size: 0.82rem; color: var(--text-light); }

        /* ---- Vorschau (fixe Breite, damit nichts abschneidet) ---- */
        .pg-stage { position: relative; width: 100%; max-width: 400px; overflow: hidden; border: 1px solid var(--border); border-radius: 10px; background: #eef1ee; }
        #pg-poster {
            position: absolute; top: 0; left: 0; transform-origin: top left;
            width: 1080px; height: 1350px; overflow: hidden;
            font-family: 'Montserrat', 'Arial Black', system-ui, sans-serif;
            color: #fff; background: #007230; cursor: crosshair;
            padding: 60px; box-sizing: border-box; display: flex; flex-direction: column;
        }
        #pg-poster .pg-bg { position: absolute; inset: 0; background-size: cover; background-position: center; z-index: 0; }
        #pg-poster .pg-ov { position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(120deg, rgba(0,86,42,0.92) 0%, rgba(0,118,48,0.78) 46%, rgba(0,118,48,0.15) 100%); }
        #pg-poster > *:not(.pg-bg):not(.pg-ov) { position: relative; z-index: 2; }

        .pg-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
        .pg-lock { background: #fff; border-radius: 18px; padding: 16px 22px; display: flex; align-items: center; gap: 16px; }
        .pg-lock img.pg-l-ml { height: 62px; width: auto; }
        .pg-lock img.pg-l-atsv { height: 74px; width: auto; }
        .pg-coop { background: #fff; border-radius: 18px; padding: 14px 20px; text-align: center; }
        .pg-coop small { display: block; color: #007230; font-weight: 800; font-size: 15px; letter-spacing: 1px; margin-bottom: 6px; }
        .pg-coop img { height: 56px; width: auto; }

        .pg-headline { font-size: 112px; font-weight: 900; line-height: 0.94; letter-spacing: -1px; margin: 34px 0 0; text-transform: uppercase; max-width: 80%; text-shadow: 0 2px 18px rgba(0,0,0,0.25); }
        .pg-subline { font-size: 38px; font-weight: 700; margin: 20px 0 0; max-width: 72%; }

        .pg-features { margin: 34px 0 0; display: flex; flex-direction: column; gap: 22px; max-width: 76%; }
        .pg-feat { display: flex; align-items: center; gap: 20px; }
        .pg-feat .pg-ic { flex: 0 0 68px; width: 68px; height: 68px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center; }
        .pg-feat .pg-ic svg { width: 34px; height: 34px; stroke: #fff; fill: none; stroke-width: 2.2; }
        .pg-feat .pg-ft { font-size: 32px; font-weight: 800; line-height: 1.05; }
        .pg-feat .pg-fs { font-size: 24px; font-weight: 500; opacity: 0.92; }

        .pg-cta { align-self: flex-start; background: #f4b81e; color: #0d3b1e; font-weight: 900; font-size: 42px; padding: 24px 50px; border-radius: 16px; margin: 34px 0 0; text-transform: uppercase; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }

        /* Sponsoren-Strip (weisse Kacheln, variabel) */
        .pg-sponsors { display: flex; flex-wrap: wrap; gap: 14px; margin: 30px 0 0; }
        .pg-sp { background: #fff; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; }
        .pg-sp img { height: 52px; width: auto; max-width: 200px; object-fit: contain; }

        .pg-bottom { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; padding-top: 30px; }
        .pg-details { display: flex; gap: 16px; flex-wrap: wrap; }
        .pg-dcard { background: rgba(255,255,255,0.96); color: #1f2a22; border-radius: 16px; padding: 18px 20px; width: 200px; }
        .pg-dcard svg { width: 32px; height: 32px; stroke: #009640; fill: none; stroke-width: 2.2; margin-bottom: 8px; }
        .pg-dcard b { display: block; font-size: 25px; font-weight: 900; line-height: 1.1; }
        .pg-dcard span { font-size: 20px; font-weight: 500; color: #3a473f; }

        .pg-scan { background: #fff; color: #0d3b1e; border-radius: 20px; padding: 20px; text-align: center; width: 290px; }
        .pg-scan-head { font-weight: 900; font-size: 24px; color: #007230; margin-bottom: 12px; line-height: 1.1; }
        .pg-domain { font-size: 19px; font-weight: 700; color: #007230; margin-top: 12px; }
        #pg-qr { width: 190px; height: 190px; display: block; margin: 0 auto; }

        .pg-qr-float { position: absolute; z-index: 5; background: #fff; padding: 12px; border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,0.3); display: none; }
        .pg-qr-float img { display: block; width: 100%; height: 100%; }
    </style>
</head>
<body>
<?php $activeNav = 'social_media'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Kampagnen-Poster „Anmeldung geöffnet"</h1>
            <p class="content-subtitle">On-Brand-Poster mit editierbaren Inhalten, Logos/Sponsoren auf Kacheln, Klick-QR &amp; PNG-Export (Entwurf)</p>
        </header>

        <p style="margin:0 0 1rem"><a href="social_orchestrator.php">&larr; zur Social-Media-Seite</a></p>
        <p class="pg-hint" style="max-width:760px;margin-bottom:1.25rem">
            <strong>Entwurf.</strong> Format wählen, Texte anpassen, Foto hochladen, Sponsoren ein-/ausblenden,
            QR per <strong>Klick ins Poster</strong> platzieren → PNG exportieren.
            Schrift (Montserrat) &amp; Platzhalter-Foto werden später gegen eure Marken-Assets getauscht.
        </p>

        <div class="pg-wrap">
            <!-- STEUERUNG -->
            <div class="pg-controls">
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
                <div class="pg-row"><label>Feature 1 (Titel / Zusatz)</label><input type="text" id="c-f1t" value="Für alle Altersklassen"><input type="text" id="c-f1s" value="Bambini, Schüler, Jugend, Erwachsene"></div>
                <div class="pg-row"><label>Feature 2 (Titel / Zusatz)</label><input type="text" id="c-f2t" value="Verschiedene Distanzen"><input type="text" id="c-f2s" value="500 m bis 10 km"></div>
                <div class="pg-row"><label>Feature 3 (Titel / Zusatz)</label><input type="text" id="c-f3t" value="Gemeinsam für Umwelt & Energie"><input type="text" id="c-f3s" value="Jeder Schritt zählt!"></div>
                <div class="pg-row"><label>Datum</label><input type="text" id="c-date" value="Sonntag 20.09.2026 · Start 10:00 Uhr"></div>
                <div class="pg-row"><label>Ort</label><input type="text" id="c-loc" value="JEK, Westring 6 Kirchseeon"></div>
                <div class="pg-row"><label>Familie</label><input type="text" id="c-fam" value="Sport, Spaß & Gemeinschaft für die ganze Familie!"></div>
                <div class="pg-row"><label>Domain</label><input type="text" id="c-domain" value="atsv-kirchseeon-marktlauf.de"></div>
                <div class="pg-row"><label><input type="checkbox" id="c-show-sponsors"> Sponsoren-Kacheln anzeigen</label></div>
                <div class="pg-row"><label>QR-Ziel-URL</label><input type="url" id="c-qr-url" value="https://atsv-kirchseeon-marktlauf.de/#anmeldung"></div>
                <div class="pg-row"><label>QR-Größe: <span id="c-qr-size-val">220</span> px</label><input type="range" id="c-qr-size" min="140" max="420" value="220" style="width:100%"></div>
                <div class="pg-row"><label>Hintergrundfoto (optional)</label><input type="file" id="c-photo" accept="image/*"> <button class="btn btn-small btn-secondary" id="c-photo-clear" type="button">entfernen</button></div>
                <button class="btn btn-primary" id="c-export" type="button" style="margin-top:0.6rem">Poster als PNG exportieren</button>
                <p class="pg-hint" id="c-status" style="margin-top:0.5rem"></p>
            </div>

            <!-- VORSCHAU -->
            <div>
                <p class="pg-hint" style="margin:0 0 0.4rem">👉 Ins Poster klicken, um den QR zu platzieren:</p>
                <div class="pg-stage" id="pg-stage">
                    <div id="pg-poster">
                        <div class="pg-bg" id="pg-bg"></div>
                        <div class="pg-ov"></div>
                        <div class="pg-top">
                            <div class="pg-lock">
                                <img class="pg-l-ml" src="../assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png" alt="Marktlauf Kirchseeon">
                                <img class="pg-l-atsv" src="../assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon">
                            </div>
                            <div class="pg-coop"><small>IN KOOPERATION MIT</small><img src="../assets/images/Wort-u-Bildmarke-Gemeinde.png" alt="Markt Kirchseeon"></div>
                        </div>
                        <h1 class="pg-headline" id="p-headline">ANMELDUNG GEÖFFNET!</h1>
                        <div class="pg-subline" id="p-subline">Sichert euch jetzt euren Startplatz!</div>
                        <div class="pg-features" id="p-features"></div>
                        <div class="pg-cta" id="p-cta">JETZT ANMELDEN!</div>
                        <div class="pg-sponsors" id="p-sponsors" style="display:none"></div>
                        <div class="pg-bottom">
                            <div class="pg-details" id="p-details"></div>
                            <div class="pg-scan">
                                <div class="pg-scan-head">JETZT SCANNEN<br>&amp; ANMELDEN!</div>
                                <img id="pg-qr" alt="">
                                <div class="pg-domain" id="p-domain">atsv-kirchseeon-marktlauf.de</div>
                            </div>
                        </div>
                        <div class="pg-qr-float" id="pg-qr-float"><img id="pg-qr-float-img" alt=""></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div><!-- /dashboard-layout (von _sidebar.php geoeffnet) -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function(){
        var SPONSORS = <?= json_encode($sponsors, JSON_UNESCAPED_SLASHES) ?>;
        var FORMATS = { portrait:{w:1080,h:1350}, square:{w:1080,h:1080}, story:{w:1080,h:1920} };
        var IC = {
            shoe:  '<svg viewBox="0 0 24 24"><path d="M2 17h20v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2z"/><path d="M2 17l1-6 5 1 4 3h8a2 2 0 0 1 2 2"/></svg>',
            watch: '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="7"/><path d="M12 13V9M9 2h6"/></svg>',
            leaf:  '<svg viewBox="0 0 24 24"><path d="M4 20C4 10 12 4 20 4c0 8-6 16-16 16z"/><path d="M4 20c4-6 8-8 12-9"/></svg>',
            cal:   '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg>',
            pin:   '<svg viewBox="0 0 24 24"><path d="M12 22s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
            fam:   '<svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3 3-5 6-5s6 2 6 5M14 20c0-2 2-4 4-4s4 1 4 3"/></svg>'
        };
        var $ = function(id){ return document.getElementById(id); };
        var poster = $('pg-poster'), stage = $('pg-stage');
        var curW = 1080, curH = 1350;
        var qrX = 0.72, qrY = 0.80;

        function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function renderFeatures(){
            $('p-features').innerHTML =
                [['shoe','c-f1t','c-f1s'],['watch','c-f2t','c-f2s'],['leaf','c-f3t','c-f3s']].map(function(f){
                    return '<div class="pg-feat"><div class="pg-ic">'+IC[f[0]]+'</div><div><div class="pg-ft">'+
                        esc($(f[1]).value)+'</div><div class="pg-fs">'+esc($(f[2]).value)+'</div></div></div>';
                }).join('');
        }
        function renderDetails(){
            $('p-details').innerHTML =
                [[IC.cal,'c-date'],[IC.pin,'c-loc'],[IC.fam,'c-fam']].map(function(d){
                    var v = esc($(d[1]).value).split('·');
                    return '<div class="pg-dcard">'+d[0]+'<b>'+(v[0]||'')+'</b><span>'+(v.slice(1).join('·')||'')+'</span></div>';
                }).join('');
        }
        function renderSponsors(){
            var box=$('p-sponsors');
            if(!$('c-show-sponsors').checked || !SPONSORS.length){ box.style.display='none'; box.innerHTML=''; return; }
            box.innerHTML = SPONSORS.map(function(u){ return '<div class="pg-sp"><img src="'+u+'" alt=""></div>'; }).join('');
            box.style.display='flex';
        }
        function bindText(cid, pid){ var el=$(cid); el.addEventListener('input', function(){ $(pid).textContent = el.value; }); }
        bindText('c-headline','p-headline'); bindText('c-subline','p-subline');
        bindText('c-cta','p-cta'); bindText('c-domain','p-domain');
        ['c-f1t','c-f1s','c-f2t','c-f2s','c-f3t','c-f3s'].forEach(function(id){ $(id).addEventListener('input', renderFeatures); });
        ['c-date','c-loc','c-fam'].forEach(function(id){ $(id).addEventListener('input', renderDetails); });
        $('c-show-sponsors').addEventListener('change', renderSponsors);
        renderFeatures(); renderDetails(); renderSponsors();

        // Foto
        $('c-photo').addEventListener('change', function(e){
            var f = e.target.files[0]; if(!f) return;
            var r = new FileReader(); r.onload = function(){ $('pg-bg').style.backgroundImage = 'url('+r.result+')'; }; r.readAsDataURL(f);
        });
        $('c-photo-clear').addEventListener('click', function(){ $('pg-bg').style.backgroundImage=''; $('c-photo').value=''; });

        // QR
        function qrDataUrl(text){
            var qr = qrcode(0,'H'); qr.addData(text); qr.make();
            var n = qr.getModuleCount(), cell=8, q=2, size=(n+q*2)*cell;
            var cv=document.createElement('canvas'); cv.width=cv.height=size;
            var x=cv.getContext('2d'); x.fillStyle='#fff'; x.fillRect(0,0,size,size); x.fillStyle='#000';
            for(var r=0;r<n;r++)for(var c=0;c<n;c++) if(qr.isDark(r,c)) x.fillRect((c+q)*cell,(r+q)*cell,cell,cell);
            return cv.toDataURL('image/png');
        }
        function updateQr(){
            var url=($('c-qr-url').value||'').trim(); var size=parseInt($('c-qr-size').value,10);
            $('c-qr-size-val').textContent=size;
            var fl=$('pg-qr-float'), fimg=$('pg-qr-float-img'), boxImg=$('pg-qr');
            if(!url){ fl.style.display='none'; boxImg.removeAttribute('src'); return; }
            var data; try{ data=qrDataUrl(url); }catch(e){ fl.style.display='none'; return; }
            boxImg.src=data; fimg.src=data;
            fl.style.width=size+'px'; fl.style.height=size+'px';
            fl.style.left=(qrX*curW - size/2)+'px'; fl.style.top=(qrY*curH - size/2)+'px';
            fl.style.display='block';
        }
        $('c-qr-url').addEventListener('input', updateQr);
        $('c-qr-size').addEventListener('input', updateQr);

        poster.addEventListener('click', function(e){
            var scale = poster._scale || 1;
            var rect = poster.getBoundingClientRect();
            qrX = ((e.clientX-rect.left)/scale)/curW;
            qrY = ((e.clientY-rect.top)/scale)/curH;
            updateQr();
        });

        // Format + Skalierung
        function applyFormat(){
            var fmt = FORMATS[$('c-format').value] || FORMATS.portrait;
            curW = fmt.w; curH = fmt.h;
            poster.style.width = curW+'px'; poster.style.height = curH+'px';
            fit(); updateQr();
        }
        function fit(){
            var w = stage.clientWidth; var scale = w/curW;
            poster.style.transform='scale('+scale+')'; poster._scale=scale;
            stage.style.height=(curH*scale)+'px';
        }
        $('c-format').addEventListener('change', applyFormat);
        window.addEventListener('resize', fit);

        // Export
        $('c-export').addEventListener('click', async function(){
            var st=$('c-status'); st.textContent='⏳ Rendert …';
            var savedTransform=poster.style.transform, savedH=stage.style.height;
            poster.style.transform='none';
            try{
                if(document.fonts && document.fonts.ready) await document.fonts.ready;
                var canvas = await html2canvas(poster,{width:curW,height:curH,scale:2,useCORS:false,backgroundColor:'#007230',logging:false});
                var a=document.createElement('a'); a.download='marktlauf-poster.png'; a.href=canvas.toDataURL('image/png'); a.click();
                st.textContent='✓ Exportiert ('+curW+'×'+curH+').';
            }catch(err){ st.textContent='⚠️ Fehler beim Export: '+err; }
            finally{ poster.style.transform=savedTransform; stage.style.height=savedH; }
        });

        applyFormat(); updateQr();

        var b=$('burger-btn'), sb=$('sidebar'), ov=$('sidebar-overlay');
        if(b){ b.addEventListener('click',function(){ sb.classList.toggle('open'); ov.classList.toggle('active'); });
               ov.addEventListener('click',function(){ sb.classList.remove('open'); ov.classList.remove('active'); }); }
    })();
    </script>
</body>
</html>
