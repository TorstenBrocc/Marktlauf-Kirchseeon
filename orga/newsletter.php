<?php
/**
 * Newsletter — eigene Dashboard-Seite (aus dem Social-Orchestrator herausgelöst).
 *
 * Ablauf: Bausteine (Intro/News/Termine/…) wählen + füllen → KI erzeugt je Block den Inhalt + 3 Betreffzeilen
 * (api/newsletter_generate.php, gemeinsamer llm_client, Marken-Farben aus den
 * Design-Tokens) → Vorschau + Betreff wählen → als Brevo-Kampagnen-ENTWURF anlegen
 * (api/newsletter_push.php). Kein Versand aus dem Dashboard; Prüfen/Senden in Brevo.
 * Ist Brevo nicht konfiguriert, bleibt der Copy-Weg (HTML kopieren, manuell in Brevo).
 *
 * Farben/Typo ausschließlich über die Design-System-Tokens (var(--…) aus css/orga.css
 * → css/base.css) — keine hartkodierten Marken-Hex. Spec: newsletter-engine-spec.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/llm_client.php';
require_once __DIR__ . '/../src/brevo_client.php';
require_once __DIR__ . '/../src/newsletter/blocks.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();

$pdo        = getDbConnection();
$provider   = llmActiveProvider($pdo);
$brevoReady   = brevoConfigured();
$blockCatalog = newsletterBlockCatalog();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Newsletter | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Nur Layout/Struktur — alle Farben & Radien aus den Design-Tokens (var(--…)). */
        .nl-card {
            background: var(--white); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow-card);
            padding: 1.5rem; margin-bottom: 1.25rem; max-width: 780px;
        }
        /* Ergebnis-Karte breiter — trägt die Code+Vorschau-Split-Ansicht. */
        #nl-result { max-width: 1100px; }
        .nl-card h2 { font-size: 1rem; margin: 0 0 1rem; }
        .nl-field { margin-bottom: 1rem; }
        .nl-field label { display: block; font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.35rem; }
        .nl-field textarea, .nl-field input[type="text"], .nl-field select {
            width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.95rem;
            padding: var(--control-pad-y) var(--control-pad-x);
            border: 1px solid var(--border); border-radius: var(--radius); background: var(--white); color: var(--text);
        }
        .nl-field textarea { min-height: 120px; resize: vertical; }
        /* Baukasten: Palette + Block-Karten */
        .nl-palette { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .nl-block { border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg); padding: 0.75rem; margin-bottom: 0.75rem; }
        .nl-block-head { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.5rem; }
        .nl-block-title { font-weight: 600; font-size: 0.9rem; flex: 1 1 auto; }
        .nl-block-head button { border: 1px solid var(--border); background: var(--white); color: var(--text); border-radius: var(--radius); cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0.25rem 0.5rem; }
        .nl-block-head button:hover { border-color: var(--primary); }
        .nl-block textarea { min-height: 70px; }
        .nl-empty { font-size: 0.85rem; color: var(--text-light); margin: 0 0 1rem; }
        .nl-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem; }
        .nl-spinner { font-size: 0.85rem; color: var(--text-light); }
        .nl-msg { font-size: 0.85rem; margin-top: 0.6rem; display: none; }
        .nl-msg.error { display: block; color: var(--error); }
        .nl-msg.ok { display: block; color: var(--success); }
        .nl-subjects { list-style: none; padding: 0; margin: 0 0 1rem; }
        .nl-subjects li { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; }
        .nl-subjects label { flex: 1 1 auto; font-size: 0.95rem; color: var(--text); cursor: pointer; }
        .nl-preview {
            width: 100%; height: 480px; border: 1px solid var(--border);
            border-radius: var(--radius); background: var(--white);
        }
        /* Split-View wie bei den Brief-Vorlagen: Code links, Live-Vorschau rechts. */
        .nl-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; align-items: start; }
        @media (max-width: 900px) { .nl-split { grid-template-columns: 1fr; } }
        .nl-split h3 { font-size: 0.85rem; margin: 0 0 0.5rem; color: var(--text-light); font-weight: 600; }
        .nl-code {
            width: 100%; height: 480px; box-sizing: border-box;
            font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.78rem; line-height: 1.45;
            border: 1px solid var(--border); border-radius: var(--radius); padding: 0.6rem;
            background: var(--bg); color: var(--text); resize: vertical; white-space: pre; overflow: auto; tab-size: 2;
        }
        .nl-hint {
            font-size: 0.82rem; color: var(--text-light);
            border: 1px solid var(--border); border-radius: var(--radius);
            padding: 0.6rem 0.8rem; margin-bottom: 1rem; background: var(--bg);
        }
    </style>
</head>
<body>
<?php $activeNav = 'newsletter'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <h1>Newsletter</h1>
            <p class="content-subtitle">Bausteine wählen &amp; mit Fakten füllen → KI erzeugt je Block den Inhalt → Vorschau → als Entwurf nach Brevo.</p>
        </header>

        <!-- Schritt 1: Erzeugen -->
        <div class="nl-card">
            <h2>1 · Newsletter erzeugen</h2>
            <div class="nl-field">
                <label for="nl-provider">KI-Anbieter</label>
                <select id="nl-provider">
                    <option value="gemini"  <?= $provider === 'gemini'  ? 'selected' : '' ?>>Google Gemini (Free)</option>
                    <option value="mistral" <?= $provider === 'mistral' ? 'selected' : '' ?>>Mistral Small</option>
                </select>
            </div>
            <div class="nl-field">
                <label>Bausteine — hinzufügen, mit Fakten füllen, per ▲▼ ordnen</label>
                <div class="nl-palette" id="nl-palette">
                    <?php foreach ($blockCatalog as $key => $b): ?>
                        <button type="button" class="btn btn-secondary btn-small" data-add="<?= htmlspecialchars($key) ?>">+ <?= htmlspecialchars($b['label']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div id="nl-blocks"></div>
                <p class="nl-empty" id="nl-blocks-empty">Noch keine Bausteine — oben einen hinzufügen (z. B. Intro).</p>
            </div>
            <div class="nl-actions">
                <button class="btn btn-primary" id="nl-generate">Newsletter generieren</button>
                <span class="nl-spinner" id="nl-gen-spinner" style="display:none">⏳ KI läuft …</span>
            </div>
            <div class="nl-msg error" id="nl-gen-error"></div>
        </div>

        <!-- Schritt 2: Prüfen & nach Brevo -->
        <div class="nl-card" id="nl-result" style="display:none">
            <h2>2 · Prüfen &amp; als Brevo-Entwurf anlegen</h2>

            <div class="nl-field">
                <label>Betreffzeile (Posteingang)</label>
                <ul class="nl-subjects" id="nl-subjects"></ul>
            </div>

            <div class="nl-field">
                <div class="nl-split">
                    <div>
                        <h3>HTML-Code</h3>
                        <textarea class="nl-code" id="nl-code" readonly spellcheck="false" aria-label="Newsletter-HTML-Code"></textarea>
                    </div>
                    <div>
                        <h3>Live-Vorschau</h3>
                        <iframe class="nl-preview" id="nl-preview" title="Newsletter-Vorschau"></iframe>
                    </div>
                </div>
            </div>

            <div class="nl-actions" style="margin-bottom:1rem">
                <button class="btn btn-secondary" id="nl-copy-html">HTML kopieren</button>
                <span class="nl-spinner" id="nl-copied" style="display:none">Kopiert</span>
            </div>

            <?php if (!$brevoReady): ?>
            <div class="nl-hint">
                Brevo ist noch nicht konfiguriert (<code>brevo_api_key</code> / <code>brevo_list_id</code> in
                <code>storage/config.php</code>). Bis dahin greift der manuelle Weg: HTML kopieren und in Brevo
                als Kampagne einfügen.
            </div>
            <?php endif; ?>

            <div class="nl-field">
                <label for="nl-name">Kampagnenname (nur intern in Brevo)</label>
                <input type="text" id="nl-name" value="Marktlauf Newsletter <?= date('Y-m-d') ?>">
            </div>
            <div class="nl-actions">
                <button class="btn btn-primary" id="nl-push">Als Brevo-Entwurf anlegen</button>
                <span class="nl-spinner" id="nl-push-spinner" style="display:none">⏳ Sende an Brevo …</span>
            </div>
            <div class="nl-msg" id="nl-push-msg"></div>
        </div>
    </main>
</div>

<script>
const csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const BLOCK_CATALOG = <?= json_encode($blockCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// --- Baukasten: Bausteine verwalten ---
const blocksEl = document.getElementById('nl-blocks');

function refreshBlocksEmpty() {
    document.getElementById('nl-blocks-empty').style.display = blocksEl.children.length ? 'none' : 'block';
}

function addBlock(type) {
    const meta = BLOCK_CATALOG[type];
    if (!meta) return;
    const card = document.createElement('div');
    card.className = 'nl-block';
    card.dataset.type = type;
    card.innerHTML =
        '<div class="nl-block-head">' +
            '<span class="nl-block-title"></span>' +
            '<button type="button" data-act="up" title="nach oben" aria-label="nach oben">▲</button>' +
            '<button type="button" data-act="down" title="nach unten" aria-label="nach unten">▼</button>' +
            '<button type="button" data-act="del" title="entfernen" aria-label="entfernen">✕</button>' +
        '</div>' +
        '<textarea aria-label="Fakten"></textarea>';
    card.querySelector('.nl-block-title').textContent = meta.label;
    card.querySelector('textarea').placeholder = meta.hint || '';
    blocksEl.appendChild(card);
    refreshBlocksEmpty();
}

blocksEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const card = btn.closest('.nl-block');
    const act = btn.dataset.act;
    if (act === 'del') { card.remove(); refreshBlocksEmpty(); }
    else if (act === 'up' && card.previousElementSibling) { card.parentNode.insertBefore(card, card.previousElementSibling); }
    else if (act === 'down' && card.nextElementSibling) { card.parentNode.insertBefore(card.nextElementSibling, card); }
});

document.getElementById('nl-palette').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-add]');
    if (btn) addBlock(btn.dataset.add);
});

function collectBlocks() {
    return [...blocksEl.querySelectorAll('.nl-block')].map((card) => ({
        type: card.dataset.type,
        fakten: card.querySelector('textarea').value,
    }));
}

// Ein Start-Baustein zur Orientierung.
addBlock('intro');

// --- Schritt 1: generieren ---
document.getElementById('nl-generate').addEventListener('click', async (e) => {
    const btn      = e.currentTarget;
    const spinner  = document.getElementById('nl-gen-spinner');
    const errEl    = document.getElementById('nl-gen-error');
    const provider = document.getElementById('nl-provider').value;
    const blocks   = collectBlocks();

    errEl.className = 'nl-msg error';
    errEl.style.display = 'none';
    if (!blocks.some((b) => b.fakten.trim())) {
        errEl.textContent = 'Bitte mindestens einen Baustein mit Fakten füllen.';
        errEl.style.display = 'block';
        return;
    }
    btn.disabled = true;
    spinner.style.display = 'inline';
    try {
        const r = await fetch('api/newsletter_generate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({csrf_token: csrf, provider, blocks: JSON.stringify(blocks)}),
        });
        const d = await r.json();
        if (d.error) {
            errEl.textContent = d.error;
            errEl.style.display = 'block';
        } else {
            renderResult(d);
        }
    } catch (err) {
        errEl.textContent = 'Netzwerkfehler.';
        errEl.style.display = 'block';
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
    }
});

function renderResult(d) {
    const subs = document.getElementById('nl-subjects');
    subs.innerHTML = '';
    (d.subjects || []).forEach((s, i) => {
        const li = document.createElement('li');
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'nl-subject';
        radio.value = s;
        radio.id = 'nl-subj-' + i;
        if (i === 0) radio.checked = true;
        const label = document.createElement('label');
        label.setAttribute('for', radio.id);
        label.textContent = s;
        li.appendChild(radio);
        li.appendChild(label);
        subs.appendChild(li);
    });
    const result = document.getElementById('nl-result');
    result.dataset.html = d.html || '';
    document.getElementById('nl-preview').srcdoc = d.html || '';
    document.getElementById('nl-code').value = d.html || '';
    // Push-Meldung aus einem früheren Lauf zurücksetzen.
    const pushMsg = document.getElementById('nl-push-msg');
    pushMsg.className = 'nl-msg';
    pushMsg.style.display = 'none';
    result.style.display = 'block';
    result.scrollIntoView({behavior: 'smooth', block: 'start'});
}

// --- HTML kopieren (Fallback-/Zusatzweg) ---
document.getElementById('nl-copy-html').addEventListener('click', () => {
    const html = document.getElementById('nl-result').dataset.html || '';
    if (!html) return;
    navigator.clipboard.writeText(html).then(() => {
        const m = document.getElementById('nl-copied');
        m.style.display = 'inline';
        setTimeout(() => { m.style.display = 'none'; }, 2000);
    });
});

// --- Schritt 2: als Brevo-Entwurf anlegen ---
document.getElementById('nl-push').addEventListener('click', async (e) => {
    const btn     = e.currentTarget;
    const spinner = document.getElementById('nl-push-spinner');
    const msg     = document.getElementById('nl-push-msg');
    const html    = document.getElementById('nl-result').dataset.html || '';
    const name    = document.getElementById('nl-name').value.trim();
    const chosen  = document.querySelector('input[name="nl-subject"]:checked');
    const subject = chosen ? chosen.value : '';

    msg.className = 'nl-msg';
    msg.style.display = 'none';
    if (!subject || !html) {
        msg.className = 'nl-msg error';
        msg.textContent = 'Bitte einen Betreff wählen (und zuerst generieren).';
        msg.style.display = 'block';
        return;
    }
    btn.disabled = true;
    spinner.style.display = 'inline';
    try {
        const r = await fetch('api/newsletter_push.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({csrf_token: csrf, subject, name, html}),
        });
        const d = await r.json();
        if (d.ok) {
            msg.className = 'nl-msg ok';
            msg.textContent = d.message || 'Entwurf in Brevo angelegt.';
        } else {
            msg.className = 'nl-msg error';
            msg.textContent = d.message || d.error || 'Anlegen fehlgeschlagen — bitte HTML kopieren und manuell in Brevo einfügen.';
        }
        msg.style.display = 'block';
    } catch (err) {
        msg.className = 'nl-msg error';
        msg.textContent = 'Netzwerkfehler — bitte HTML kopieren und manuell in Brevo einfügen.';
        msg.style.display = 'block';
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
