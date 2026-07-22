<?php
/**
 * Kampagnen-Poster-Generator (Frei-Editor v2) — Marketing-Qualitaet aus dem Tool.
 * - Vorschau-Feld resizable (Poster waechst mit), Formate (Portrait/Quadrat/Story)
 * - Bloecke frei verschieben (Drag) mit Snapping + Ausrichtungslinien
 * - Groesse per Eck-Handle ODER Regler
 * - Eigene Kacheln (Beschriftung + Logo, skalierbar)
 * - Logos + Sponsoren auf weissen Kacheln, QR, PNG-Export (2x)
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
        .pg-wrap { display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem; align-items: start; }
        @media (max-width: 1000px) { .pg-wrap { grid-template-columns: 1fr; } }
        .pg-row { margin-bottom: 0.65rem; }
        .pg-row label { font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.2rem; display: block; }
        .pg-row input[type=text], .pg-row input[type=url], .pg-row select { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box; background:#fff; }
        .pg-row input + input { margin-top: 0.35rem; }
        .pg-hint { font-size: 0.82rem; color: var(--text-light); }
        .pg-sel-panel { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 0.75rem; margin-bottom: 0.75rem; }
        .pg-sel-panel b { font-size: 0.88rem; }

        .pg-stage { position: relative; width: 400px; max-width: 100%; min-width: 260px; overflow: hidden; border: 1px solid var(--border); border-radius: 10px; background: #eef1ee; resize: horizontal; }
        #pg-poster {
            position: absolute; top: 0; left: 0; transform-origin: top left;
            width: 1080px; height: 1350px; overflow: hidden;
            font-family: 'Montserrat', 'Arial Black', system-ui, sans-serif;
            color: #fff; background: #007230;
        }
        #pg-poster .pg-bg { position: absolute; inset: 0; background-size: cover; background-position: center; z-index: 0; }
        #pg-poster .pg-ov { position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(120deg, rgba(0,86,42,0.92) 0%, rgba(0,118,48,0.78) 46%, rgba(0,118,48,0.15) 100%); }

        .pb { position: absolute; z-index: 2; transform-origin: top left; cursor: grab; }
        .pb.dragging { cursor: grabbing; }

        /* Auswahlrahmen + Resize-Handle + Fanglinien (UI-Overlay, nicht im Export) */
        .pg-selbox { position: absolute; z-index: 8; border: 3px dashed #ff6b35; box-sizing: border-box; display: none; pointer-events: none; }
        .pg-selbox-h { position: absolute; right: -26px; bottom: -26px; width: 52px; height: 52px; background: #fff; border: 5px solid #ff6b35; border-radius: 8px; pointer-events: auto; cursor: nwse-resize; }
        .pg-guide-v { position: absolute; top: 0; width: 0; border-left: 3px dashed #ff8c42; z-index: 9; display: none; }
        .pg-guide-h { position: absolute; left: 0; height: 0; border-top: 3px dashed #ff8c42; z-index: 9; display: none; }

        .pg-lock { background: #fff; border-radius: 18px; padding: 16px 22px; display: flex; align-items: center; gap: 16px; }
        .pg-lock img.pg-l-ml { height: 62px; width: auto; }
        .pg-lock img.pg-l-atsv { height: 74px; width: auto; }
        .pg-coop { background: #fff; border-radius: 18px; padding: 14px 20px; text-align: center; }
        .pg-coop small { display: block; color: #007230; font-weight: 800; font-size: 15px; letter-spacing: 1px; margin-bottom: 6px; }
        .pg-coop img { height: 56px; width: auto; }

        .pg-headline { font-size: 100px; font-weight: 900; line-height: 0.94; letter-spacing: -1px; text-transform: uppercase; width: 820px; text-shadow: 0 2px 18px rgba(0,0,0,0.25); }
        .pg-subline { font-size: 38px; font-weight: 700; width: 720px; }

        .pg-features { display: flex; flex-direction: column; gap: 22px; width: 760px; }
        .pg-feat { display: flex; align-items: center; gap: 20px; }
        .pg-feat .pg-ic { flex: 0 0 66px; width: 66px; height: 66px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center; }
        .pg-feat .pg-ic svg { width: 33px; height: 33px; stroke: #fff; fill: none; stroke-width: 2.2; }
        .pg-feat .pg-ft { font-size: 32px; font-weight: 800; line-height: 1.05; }
        .pg-feat .pg-fs { font-size: 24px; font-weight: 500; opacity: 0.92; }

        .pg-cta { background: #f4b81e; color: #0d3b1e; font-weight: 900; font-size: 42px; padding: 24px 50px; border-radius: 16px; text-transform: uppercase; box-shadow: 0 8px 24px rgba(0,0,0,0.2); white-space: nowrap; }

        .pg-sponsors { display: flex; flex-wrap: wrap; gap: 14px; width: 900px; }
        .pg-sp { background: #fff; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; }
        .pg-sp img { height: 50px; width: auto; max-width: 200px; object-fit: contain; }

        .pg-details { display: flex; gap: 16px; }
        .pg-dcard { background: rgba(255,255,255,0.96); color: #1f2a22; border-radius: 16px; padding: 18px 20px; width: 200px; }
        .pg-dcard svg { width: 32px; height: 32px; stroke: #009640; fill: none; stroke-width: 2.2; margin-bottom: 8px; }
        .pg-dcard b { display: block; font-size: 25px; font-weight: 900; line-height: 1.1; }
        .pg-dcard span { font-size: 20px; font-weight: 500; color: #3a473f; }

        .pg-scan { background: #fff; color: #0d3b1e; border-radius: 20px; padding: 20px; text-align: center; width: 290px; }
        .pg-scan-head { font-weight: 900; font-size: 24px; color: #007230; margin-bottom: 12px; line-height: 1.1; }
        .pg-domain { font-size: 19px; font-weight: 700; color: #007230; margin-top: 12px; }
        #pg-qr { width: 190px; height: 190px; display: block; margin: 0 auto; }

        /* Eigene Kachel */
        .pg-ctile { background: #fff; color: #0d3b1e; border-radius: 16px; padding: 18px 22px; display: flex; align-items: center; gap: 14px; }
        .pg-ctile img { height: 52px; width: auto; max-width: 200px; object-fit: contain; }
        .pg-ct-text { font-size: 30px; font-weight: 800; white-space: nowrap; }
    </style>
</head>
<body>
<?php $activeNav = 'social_media'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Kampagnen-Poster (Frei-Editor)</h1>
            <p class="content-subtitle">Vorschau ziehbar · Blöcke verschieben/skalieren mit Fanglinien · eigene Kacheln · PNG-Export</p>
        </header>

        <p style="margin:0 0 1rem"><a href="social_orchestrator.php">&larr; zur Social-Media-Seite</a></p>
        <p class="pg-hint" style="max-width:820px;margin-bottom:1.25rem">
            <strong>Vorschau vergrößern:</strong> unten rechts am Vorschau-Feld ziehen (Poster wächst mit).
            <strong>Block:</strong> anklicken → ziehen zum Verschieben (mit Fanglinien), <strong>orangenes Eck-Quadrat</strong> ziehen oder Regler links = Größe.
            <strong>Eigene Kachel</strong> per Button hinzufügen (Beschriftung + Logo im Panel links).
        </p>

        <div class="pg-wrap">
            <div class="pg-controls">
                <div class="pg-sel-panel" id="pg-sel-panel" style="display:none">
                    <b id="pg-sel-name">Kein Block ausgewählt</b>
                    <div class="pg-row" style="margin:0.5rem 0 0"><label>Größe: <span id="pg-sel-scale-val">100</span> %</label>
                        <input type="range" id="pg-sel-scale" min="30" max="300" value="100" style="width:100%"></div>
                    <div id="pg-custom-ctrl" style="display:none">
                        <div class="pg-row"><label>Beschriftung</label><input type="text" id="pg-ct-label" value=""></div>
                        <div class="pg-row"><label>Logo auf der Kachel</label><select id="pg-ct-logo"></select></div>
                    </div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                        <button class="btn btn-small btn-secondary" id="pg-deselect" type="button">Auswahl aufheben</button>
                        <button class="btn btn-small btn-secondary" id="pg-del-block" type="button" style="display:none">Kachel löschen</button>
                    </div>
                </div>

                <button class="btn btn-secondary" id="c-add-tile" type="button" style="margin-bottom:0.75rem;width:100%">+ Eigene Kachel hinzufügen</button>

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
                <div class="pg-row"><label>Hintergrundfoto (optional)</label><input type="file" id="c-photo" accept="image/*"> <button class="btn btn-small btn-secondary" id="c-photo-clear" type="button">entfernen</button></div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.6rem">
                    <button class="btn btn-primary" id="c-export" type="button">PNG exportieren</button>
                    <button class="btn btn-secondary" id="c-reset" type="button">Layout zurücksetzen</button>
                </div>
                <p class="pg-hint" id="c-status" style="margin-top:0.5rem"></p>
            </div>

            <div>
                <p class="pg-hint" style="margin:0 0 0.4rem">Vorschau (rechts unten ziehen zum Vergrößern):</p>
                <div class="pg-stage" id="pg-stage">
                    <div id="pg-poster">
                        <div class="pg-bg" id="pg-bg"></div>
                        <div class="pg-ov"></div>

                        <div class="pb" id="b-logo" data-name="Logo (Marktlauf + ATSV)">
                            <div class="pg-lock">
                                <img class="pg-l-ml" src="../assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png" alt="Marktlauf Kirchseeon">
                                <img class="pg-l-atsv" src="../assets/images/ATSV_Logo-750x968.png" alt="ATSV Kirchseeon">
                            </div>
                        </div>
                        <div class="pb" id="b-coop" data-name="Gemeinde-Logo">
                            <div class="pg-coop"><small>IN KOOPERATION MIT</small><img src="../assets/images/Wort-u-Bildmarke-Gemeinde.png" alt="Markt Kirchseeon"></div>
                        </div>
                        <div class="pb" id="b-headline" data-name="Headline"><div class="pg-headline" id="p-headline">ANMELDUNG GEÖFFNET!</div></div>
                        <div class="pb" id="b-subline" data-name="Subline"><div class="pg-subline" id="p-subline">Sichert euch jetzt euren Startplatz!</div></div>
                        <div class="pb" id="b-features" data-name="Feature-Liste"><div class="pg-features" id="p-features"></div></div>
                        <div class="pb" id="b-cta" data-name="Button"><div class="pg-cta" id="p-cta">JETZT ANMELDEN!</div></div>
                        <div class="pb" id="b-sponsors" data-name="Sponsoren" style="display:none"><div class="pg-sponsors" id="p-sponsors"></div></div>
                        <div class="pb" id="b-details" data-name="Info-Kacheln"><div class="pg-details" id="p-details"></div></div>
                        <div class="pb" id="b-scan" data-name="Scan/QR-Kachel">
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
    </main>
</div><!-- /dashboard-layout -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function(){
        var SPONSORS = <?= json_encode($sponsors, JSON_UNESCAPED_SLASHES) ?>;
        var FORMATS = { portrait:{w:1080,h:1350}, square:{w:1080,h:1080}, story:{w:1080,h:1920} };
        var LOGOS = {
            'ATSV': '../assets/images/ATSV_Logo-750x968.png',
            'Marktlauf': '../assets/images/Marktlauf-Logo-Schrift-1180x579%20freigestellt.png',
            'Gemeinde': '../assets/images/Wort-u-Bildmarke-Gemeinde.png'
        };
        SPONSORS.forEach(function(u,i){ LOGOS['Sponsor '+(i+1)] = u; });
        var DEFAULTS = {
            'b-logo':{x:56,y:44,s:1}, 'b-coop':{x:748,y:44,s:1}, 'b-headline':{x:56,y:250,s:1},
            'b-subline':{x:56,y:520,s:1}, 'b-features':{x:56,y:630,s:1}, 'b-cta':{x:56,y:940,s:1},
            'b-sponsors':{x:56,y:1060,s:1}, 'b-details':{x:56,y:1130,s:1}, 'b-scan':{x:730,y:1030,s:1}
        };
        var IC = {
            shoe:'<svg viewBox="0 0 24 24"><path d="M2 17h20v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2z"/><path d="M2 17l1-6 5 1 4 3h8a2 2 0 0 1 2 2"/></svg>',
            watch:'<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="7"/><path d="M12 13V9M9 2h6"/></svg>',
            leaf:'<svg viewBox="0 0 24 24"><path d="M4 20C4 10 12 4 20 4c0 8-6 16-16 16z"/><path d="M4 20c4-6 8-8 12-9"/></svg>',
            cal:'<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg>',
            pin:'<svg viewBox="0 0 24 24"><path d="M12 22s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
            fam:'<svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3 3-5 6-5s6 2 6 5M14 20c0-2 2-4 4-4s4 1 4 3"/></svg>'
        };
        var $ = function(id){ return document.getElementById(id); };
        var poster=$('pg-poster'), stage=$('pg-stage'), selbox=$('pg-selbox'), gv=$('pg-guide-v'), gh=$('pg-guide-h');
        var curW=1080, curH=1350, pos={}, sel=null, customN=0;

        function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function natW(id){ return $(id).offsetWidth; }
        function natH(id){ return $(id).offsetHeight; }
        function rect(id){ var p=pos[id]; return {x:p.x, y:p.y, w:natW(id)*p.s, h:natH(id)*p.s}; }
        function applyBlock(id){ var b=$(id),p=pos[id]; b.style.left=p.x+'px'; b.style.top=p.y+'px'; b.style.transform='scale('+p.s+')'; if(id===sel) updateSelbox(); }
        function applyAll(){ Object.keys(pos).forEach(applyBlock); }

        // ---- Content ----
        function renderFeatures(){
            $('p-features').innerHTML=[['shoe','c-f1t','c-f1s'],['watch','c-f2t','c-f2s'],['leaf','c-f3t','c-f3s']].map(function(f){
                return '<div class="pg-feat"><div class="pg-ic">'+IC[f[0]]+'</div><div><div class="pg-ft">'+esc($(f[1]).value)+'</div><div class="pg-fs">'+esc($(f[2]).value)+'</div></div></div>';
            }).join('');
        }
        function renderDetails(){
            $('p-details').innerHTML=[[IC.cal,'c-date'],[IC.pin,'c-loc'],[IC.fam,'c-fam']].map(function(d){
                var v=esc($(d[1]).value).split('·'); return '<div class="pg-dcard">'+d[0]+'<b>'+(v[0]||'')+'</b><span>'+(v.slice(1).join('·')||'')+'</span></div>';
            }).join('');
        }
        function renderSponsors(){
            var blk=$('b-sponsors');
            if(!$('c-show-sponsors').checked||!SPONSORS.length){ blk.style.display='none'; if(sel==='b-sponsors') deselect(); return; }
            $('p-sponsors').innerHTML=SPONSORS.map(function(u){ return '<div class="pg-sp"><img src="'+u+'" alt=""></div>'; }).join(''); blk.style.display='block';
        }
        function bindText(cid,pid){ $(cid).addEventListener('input',function(){ $(pid).textContent=this.value; if(sel) updateSelbox(); }); }
        bindText('c-headline','p-headline'); bindText('c-subline','p-subline'); bindText('c-cta','p-cta'); bindText('c-domain','p-domain');
        ['c-f1t','c-f1s','c-f2t','c-f2s','c-f3t','c-f3s'].forEach(function(id){ $(id).addEventListener('input',function(){ renderFeatures(); if(sel) updateSelbox(); }); });
        ['c-date','c-loc','c-fam'].forEach(function(id){ $(id).addEventListener('input',function(){ renderDetails(); if(sel) updateSelbox(); }); });
        $('c-show-sponsors').addEventListener('change', renderSponsors);
        renderFeatures(); renderDetails(); renderSponsors();

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

        // ---- Auswahl / Selbox ----
        function updateSelbox(){
            if(!sel){ selbox.style.display='none'; return; }
            var r=rect(sel);
            selbox.style.left=r.x+'px'; selbox.style.top=r.y+'px'; selbox.style.width=r.w+'px'; selbox.style.height=r.h+'px'; selbox.style.display='block';
        }
        function select(id){
            sel=id; updateSelbox();
            $('pg-sel-panel').style.display='block';
            $('pg-sel-name').textContent='Ausgewählt: '+$(id).getAttribute('data-name');
            $('pg-sel-scale').value=Math.round(pos[id].s*100); $('pg-sel-scale-val').textContent=Math.round(pos[id].s*100);
            var isCustom=$(id).getAttribute('data-custom')==='1';
            $('pg-custom-ctrl').style.display=isCustom?'block':'none';
            $('pg-del-block').style.display=isCustom?'inline-flex':'none';
            if(isCustom){ $('pg-ct-label').value=$(id).querySelector('.pg-ct-text').textContent; syncLogoSelect(id); }
        }
        function deselect(){ sel=null; selbox.style.display='none'; $('pg-sel-panel').style.display='none'; }
        $('pg-deselect').addEventListener('click',deselect);
        $('pg-sel-scale').addEventListener('input',function(){ if(!sel) return; pos[sel].s=parseInt(this.value,10)/100; $('pg-sel-scale-val').textContent=this.value; applyBlock(sel); });

        // ---- Snapping ----
        function snap(nx,ny,id){
            var r=rect(id), w=r.w, h=r.h, TH=12;
            var mv=[nx,nx+w/2,nx+w], mh=[ny,ny+h/2,ny+h];
            var cV=[0,curW/2,curW], cH=[0,curH/2,curH];
            Object.keys(pos).forEach(function(o){ if(o===id||$(o).style.display==='none') return; var rr=rect(o); cV.push(rr.x,rr.x+rr.w/2,rr.x+rr.w); cH.push(rr.y,rr.y+rr.h/2,rr.y+rr.h); });
            var ox=nx, oy=ny, gvx=null, ghy=null, bd=TH+1;
            mv.forEach(function(m){ cV.forEach(function(c){ var d=Math.abs(m-c); if(d<bd){ bd=d; ox=nx+(c-m); gvx=c; } }); });
            bd=TH+1;
            mh.forEach(function(m){ cH.forEach(function(c){ var d=Math.abs(m-c); if(d<bd){ bd=d; oy=ny+(c-m); ghy=c; } }); });
            if(gvx!==null){ gv.style.left=gvx+'px'; gv.style.height=curH+'px'; gv.style.display='block'; } else gv.style.display='none';
            if(ghy!==null){ gh.style.top=ghy+'px'; gh.style.width=curW+'px'; gh.style.display='block'; } else gh.style.display='none';
            return {x:Math.round(ox),y:Math.round(oy)};
        }
        function hideGuides(){ gv.style.display='none'; gh.style.display='none'; }

        // ---- Drag (move) + Resize (handle) ----
        var mode=null, st=null;
        function onDown(id,e){ e.preventDefault(); select(id); var sc=poster._scale||1; mode='move'; st={id:id,sx:e.clientX,sy:e.clientY,ox:pos[id].x,oy:pos[id].y,sc:sc}; $(id).classList.add('dragging'); }
        function attach(b){ b.addEventListener('mousedown',function(e){ if(e.target===$('pg-selbox-h')) return; onDown(b.id,e); }); }
        Array.prototype.forEach.call(document.querySelectorAll('.pb'),attach);

        $('pg-selbox-h').addEventListener('mousedown',function(e){ e.preventDefault(); e.stopPropagation(); if(!sel) return; mode='resize'; st={id:sel,sc:poster._scale||1}; });

        window.addEventListener('mousemove',function(e){
            if(!mode||!st) return;
            var sc=poster._scale||1, prect=poster.getBoundingClientRect();
            if(mode==='move'){
                var dx=(e.clientX-st.sx)/st.sc, dy=(e.clientY-st.sy)/st.sc;
                var snapped=snap(st.ox+dx, st.oy+dy, st.id);
                pos[st.id].x=snapped.x; pos[st.id].y=snapped.y; applyBlock(st.id);
            } else if(mode==='resize'){
                var px=(e.clientX-prect.left)/sc, py=(e.clientY-prect.top)/sc;
                var W=natW(st.id), H=natH(st.id);
                var sByW=(px-pos[st.id].x)/W, sByH=(py-pos[st.id].y)/H;
                var ns=Math.max(sByW,sByH); ns=Math.max(0.3,Math.min(3.5,ns));
                pos[st.id].s=ns; applyBlock(st.id);
                $('pg-sel-scale').value=Math.round(ns*100); $('pg-sel-scale-val').textContent=Math.round(ns*100);
            }
        });
        window.addEventListener('mouseup',function(){ if(mode==='move'&&st) $(st.id).classList.remove('dragging'); mode=null; st=null; hideGuides(); });

        // Klick auf leeren Poster-Hintergrund = Auswahl aufheben
        poster.addEventListener('mousedown',function(e){ if(e.target===poster||e.target===$('pg-bg')||e.target.classList.contains('pg-ov')) deselect(); });

        // ---- Eigene Kacheln ----
        function syncLogoSelect(id){
            var sel2=$('pg-ct-logo'), cur=$(id).getAttribute('data-logo')||'';
            sel2.innerHTML='<option value="">— kein Logo —</option>'+Object.keys(LOGOS).map(function(k){ return '<option value="'+k+'"'+(k===cur?' selected':'')+'>'+k+'</option>'; }).join('');
        }
        function addTile(){
            customN++; var id='custom'+customN;
            var b=document.createElement('div'); b.className='pb'; b.id=id; b.setAttribute('data-name','Eigene Kachel '+customN); b.setAttribute('data-custom','1'); b.setAttribute('data-logo','');
            b.innerHTML='<div class="pg-ctile"><img class="pg-ct-img" style="display:none" alt=""><span class="pg-ct-text">Neue Kachel</span></div>';
            poster.appendChild(b);
            pos[id]={x:Math.round(curW/2-140),y:Math.round(curH/2-40),s:1};
            attach(b); applyBlock(id); select(id);
        }
        $('c-add-tile').addEventListener('click',addTile);
        $('pg-ct-label').addEventListener('input',function(){ if(sel&&$(sel).getAttribute('data-custom')==='1'){ $(sel).querySelector('.pg-ct-text').textContent=this.value; updateSelbox(); } });
        $('pg-ct-logo').addEventListener('change',function(){ if(!sel) return; var img=$(sel).querySelector('.pg-ct-img'); var k=this.value; $(sel).setAttribute('data-logo',k);
            if(k&&LOGOS[k]){ img.src=LOGOS[k]; img.style.display='block'; } else { img.removeAttribute('src'); img.style.display='none'; } updateSelbox(); });
        $('pg-del-block').addEventListener('click',function(){ if(!sel) return; if($(sel).getAttribute('data-custom')!=='1') return; var id=sel; deselect(); $(id).remove(); delete pos[id]; });

        // ---- Format + Fit + Vorschau-Resize ----
        function applyFormat(){ var f=FORMATS[$('c-format').value]||FORMATS.portrait; curW=f.w; curH=f.h; poster.style.width=curW+'px'; poster.style.height=curH+'px'; fit(); if(sel) updateSelbox(); }
        function fit(){ var w=stage.clientWidth, sc=w/curW; poster.style.transform='scale('+sc+')'; poster._scale=sc; stage.style.height=(curH*sc)+'px'; }
        $('c-format').addEventListener('change',applyFormat);
        var lastW=0;
        try{ new ResizeObserver(function(){ if(stage.clientWidth!==lastW){ lastW=stage.clientWidth; fit(); if(sel) updateSelbox(); } }).observe(stage); }catch(e){ window.addEventListener('resize',fit); }

        $('c-reset').addEventListener('click',function(){
            Object.keys(pos).forEach(function(id){ if(/^custom/.test(id)){ var el=$(id); if(el) el.remove(); } });
            pos=JSON.parse(JSON.stringify(DEFAULTS)); applyAll(); deselect();
        });

        // ---- Export ----
        $('c-export').addEventListener('click',async function(){
            var s=$('c-status'); s.textContent='⏳ Rendert …';
            var keep=sel; selbox.style.display='none'; hideGuides();
            var sv=poster.style.transform, sh=stage.style.height; poster.style.transform='none';
            try{
                if(document.fonts&&document.fonts.ready) await document.fonts.ready;
                var canvas=await html2canvas(poster,{width:curW,height:curH,scale:2,useCORS:false,backgroundColor:'#007230',logging:false});
                var a=document.createElement('a'); a.download='marktlauf-poster.png'; a.href=canvas.toDataURL('image/png'); a.click();
                s.textContent='✓ Exportiert ('+curW+'×'+curH+').';
            }catch(err){ s.textContent='⚠️ Fehler beim Export: '+err; }
            finally{ poster.style.transform=sv; stage.style.height=sh; sel=keep; if(sel) updateSelbox(); }
        });

        // Init
        pos=JSON.parse(JSON.stringify(DEFAULTS)); applyAll(); applyFormat(); updateQr(); lastW=stage.clientWidth;

        var bg=$('burger-btn'), sb=$('sidebar'), ov=$('sidebar-overlay');
        if(bg){ bg.addEventListener('click',function(){ sb.classList.toggle('open'); ov.classList.toggle('active'); });
                ov.addEventListener('click',function(){ sb.classList.remove('open'); ov.classList.remove('active'); }); }
    })();
    </script>
</body>
</html>
