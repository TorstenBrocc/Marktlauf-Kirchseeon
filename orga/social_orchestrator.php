<?php
/**
 * Social-Media-Orchestrator: KI-Nachbericht aus Raceresult-Ergebnissen.
 * Phase 1: Mock-Daten, manueller Dispatch (Copy/Download).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/llm_client.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();

$pdo      = getDbConnection();
$provider = llmActiveProvider($pdo);

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

        <!-- Section C: Copy / Download (Dispatch Phase 1) -->
        <div class="so-card">
            <h2>Schritt 3 — Veröffentlichen (manuell)</h2>
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

<script>
const csrf    = <?= json_encode($csrf) ?>;
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
