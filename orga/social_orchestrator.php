<?php
/**
 * Social-Media-Orchestrator: KI-Nachbericht aus Raceresult-Ergebnissen.
 * Phase 1: Mock-Daten, manueller Dispatch (Copy/Download).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/llm_client.php';
require_once __DIR__ . '/../src/raceresult_mock.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();

$pdo      = getDbConnection();
$provider = llmActiveProvider($pdo);

// Mock-Daten für JS-Share-Card
$mockData    = raceResultMock();
$mockDataJson = json_encode($mockData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

// Letzten gespeicherten Entwurf laden (neuester draft/approved)
$last = $pdo->query(
    'SELECT id, llm_text_article, llm_text_social, status, llm_provider, created_at
       FROM post_race_contents
      ORDER BY id DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Social Media | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .so-card {
            background: var(--white); border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        /* Share-Card-Wrapper: skalierter Container damit der 1080px-Div nicht scrollt */
        .share-card-wrap {
            width: 360px; height: 360px; overflow: hidden;
            border: 1px solid var(--border); border-radius: 8px;
            margin-bottom: 1rem; background: #009640;
        }
        /* Die eigentliche Render-Card — 1080×1080px, runterskaliert auf 1/3 */
        #social-share-card {
            width: 1080px; height: 1080px;
            transform: scale(0.3333); transform-origin: top left;
            background: linear-gradient(145deg, #009640 0%, #007230 100%);
            display: flex; flex-direction: column;
            justify-content: space-between; padding: 80px;
            box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #ffffff; position: relative; overflow: hidden;
        }
        .sc-logo { width: 140px; height: auto; position: absolute; top: 60px; right: 60px; }
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
            <p class="content-subtitle">KI-Nachbericht aus Raceresult-Ergebnissen (Phase 1: Mock-Daten)</p>
        </header>

        <!-- Section A: Generieren -->
        <div class="so-card">
            <h2>Schritt 1 — Ergebnisse laden &amp; KI starten</h2>
            <div class="so-provider-row">
                <label for="so-provider">KI-Anbieter:</label>
                <select id="so-provider">
                    <option value="gemini"  <?= $provider === 'gemini'  ? 'selected' : '' ?>>Google Gemini (Free)</option>
                    <option value="mistral" <?= $provider === 'mistral' ? 'selected' : '' ?>>Mistral Small</option>
                </select>
                <button class="btn btn-small btn-secondary" id="so-save-provider">Anbieter speichern</button>
                <span id="so-provider-saved" style="display:none;font-size:.8rem;color:#16a34a">Gespeichert</span>
            </div>
            <p class="so-notice">
                Phase 1 nutzt Mock-Ergebnisdaten des Marktlauf Kirchseeon 2026.
                Der echte Raceresult-Abruf wird nach dem Renntag (20.09.) eingebunden.
            </p>
            <div class="so-actions">
                <button class="btn btn-primary" id="so-generate-btn">
                    Entwürfe generieren
                </button>
                <span class="so-spinner active" id="so-spinner" style="display:none">⏳ KI läuft …</span>
            </div>
            <div id="so-error"></div>
        </div>

        <!-- Section B: Editieren & Speichern -->
        <div class="so-card">
            <h2>Schritt 2 — Entwürfe bearbeiten &amp; freigeben</h2>
            <div class="so-textareas">
                <div>
                    <label for="so-article">Presse-Artikel (Lokalzeitung)</label>
                    <textarea id="so-article" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?=
                        htmlspecialchars($last['llm_text_article'] ?? '')
                    ?></textarea>
                </div>
                <div>
                    <label for="so-social">Social-Media-Post (Instagram / Facebook)</label>
                    <textarea id="so-social" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?=
                        htmlspecialchars($last['llm_text_social'] ?? '')
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

        <!-- Share-Card: versteckter Render-Div (off-layout, aber im DOM) -->
        <div style="position:absolute;left:-9999px;top:-9999px;width:1080px;height:1080px;overflow:hidden" aria-hidden="true">
            <div id="social-share-card">
                <img class="sc-logo" id="sc-logo-img" src="../assets/images/ATSV_Logo-750x968.png" alt="">
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
                <div class="sc-footer">
                    <span class="sc-url">atsv-kirchseeon-marktlauf.de</span>
                    <span class="sc-wordmark">ATSV Kirchseeon</span>
                </div>
            </div>
        </div>

        <!-- Section C: Grafik -->
        <div class="so-card">
            <h2>Schritt 3 — Share-Grafik erzeugen</h2>
            <p class="so-notice">
                Erzeugt eine 1080×1080 px PNG-Grafik mit den Ergebnis-Highlights — ideal für Instagram &amp; Facebook.
                Für beste Ergebnisse zuerst Entwürfe generieren (Schritt 1), dann hier rendern.
            </p>
            <div class="so-actions" style="margin-top:0.75rem">
                <button class="btn btn-secondary" id="so-render-card">Grafik erzeugen</button>
                <button class="btn btn-secondary" id="so-download-card" style="display:none">PNG herunterladen</button>
            </div>
            <div id="so-card-error"></div>
            <div id="so-card-preview">
                <p style="font-size:.82rem;color:var(--text-light);margin:0.5rem 0 0.4rem">Vorschau (1080×1080 px):</p>
                <img id="so-card-img" src="" alt="Share-Card Vorschau">
            </div>
        </div>

        <!-- Section D: Copy / Download (Dispatch Phase 1) -->
        <div class="so-card">
            <h2>Schritt 4 — Veröffentlichen (manuell)</h2>
            <div class="so-actions">
                <button class="btn btn-secondary" id="so-copy-article">Presse-Text kopieren</button>
                <button class="btn btn-secondary" id="so-copy-social">Social-Post kopieren</button>
            </div>
            <p class="so-notice" style="margin-top:0.75rem">
                Auto-Posting (Instagram/Facebook via Meta Graph API) ist für Phase 2 nach dem Renntag geplant.
                Bis dahin: Text kopieren und manuell posten.
            </p>
        </div>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const csrf     = <?= json_encode($csrf) ?>;
const mockData = <?= $mockDataJson ?>;
let currentId = <?= $last ? (int)$last['id'] : 'null' ?>;

// Provider speichern
document.getElementById('so-save-provider').addEventListener('click', () => {
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
            body: new URLSearchParams({csrf_token: csrf, provider}),
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

document.getElementById('so-render-card').addEventListener('click', async () => {
    const btn    = document.getElementById('so-render-card');
    const errEl  = document.getElementById('so-card-error');
    btn.disabled = true;
    btn.textContent = '⏳ Rendert …';
    errEl.style.display = 'none';

    fillShareCard(mockData);

    // Logo vorab laden damit html2canvas es findet
    const logoImg = document.getElementById('sc-logo-img');
    await new Promise(resolve => {
        if (logoImg.complete) { resolve(); } else { logoImg.onload = resolve; logoImg.onerror = resolve; }
    });

    try {
        const canvas = await html2canvas(document.getElementById('social-share-card'), {
            width:        1080,
            height:       1080,
            scale:        1,
            useCORS:      false,
            allowTaint:   false,
            backgroundColor: '#009640',
            logging:      false,
        });
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('so-card-img').src = dataUrl;
        document.getElementById('so-card-preview').style.display = 'block';

        const dlBtn = document.getElementById('so-download-card');
        dlBtn.style.display = 'inline-block';
        dlBtn.onclick = () => {
            const a = document.createElement('a');
            a.href     = dataUrl;
            a.download = 'marktlauf2026-social.png';
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
