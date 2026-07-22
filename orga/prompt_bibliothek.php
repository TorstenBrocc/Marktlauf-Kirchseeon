<?php
/**
 * Prompt-Bibliothek — Split-View Editor.
 * Links: Liste + Editor mit Formatierungs-Toolbar.
 * Rechts: Gerenderte Vorschau (Markdown-Subset, client-seitig).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';

$user      = getCurrentUserFromGuard();
$isAdmin   = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$kategorien = [
    'raceresult' => 'RaceResult',
    'sponsoren'  => 'Sponsoren-Recherche',
    'social'     => 'Social-Post',
    'newsletter' => 'Newsletter',
    'presse'     => 'Presse',
    'frei'       => 'Frei',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Prompt-Bibliothek | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* ── Layout ─────────────────────────────────────────── */
        .pb-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .pb-layout { grid-template-columns: 1fr; }
            .pb-list-col { order: 2; }
            .pb-editor-col { order: 1; }
        }

        /* ── Liste ──────────────────────────────────────────── */
        .pb-list-col {}
        .pb-filter-bar {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }
        .pb-filter-btn {
            padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.8rem;
            border: 1px solid var(--border); background: var(--white);
            color: var(--text); cursor: pointer; transition: background 0.15s;
        }
        .pb-filter-btn.active {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }
        .pb-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .pb-item {
            background: var(--white); border: 1px solid var(--border);
            border-radius: 8px; padding: 0.75rem 1rem; cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .pb-item:hover { border-color: var(--primary); box-shadow: 0 1px 4px rgba(0,150,64,.12); }
        .pb-item.active { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0,150,64,.2); }
        .pb-item-titel {
            font-weight: 600; font-size: 0.9rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pb-item-meta { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.35rem; align-items: center; }
        .pb-item-vorschau {
            font-size: 0.78rem; color: var(--text-light);
            margin-top: 0.3rem;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .pb-empty { font-size: 0.85rem; color: var(--text-light); padding: 1rem 0; }

        /* ── Badges ─────────────────────────────────────────── */
        .badge {
            display: inline-block; padding: 0.15rem 0.55rem; border-radius: 20px;
            font-size: 0.72rem; font-weight: 600; letter-spacing: 0.02em;
        }
        .badge-raceresult  { background: #d0021b; color: #fff; }
        .badge-sponsoren   { background: #fff3cd; color: #7a5000; }
        .badge-social      { background: #e8f5e9; color: #1b5e20; }
        .badge-newsletter  { background: #f3e5f5; color: #6a1b9a; }
        .badge-presse      { background: #fce4ec; color: #880e4f; }
        .badge-frei        { background: var(--bg); color: var(--text-light); }
        .tag-chip {
            display: inline-block; padding: 0.1rem 0.45rem; border-radius: 12px;
            font-size: 0.7rem; background: var(--bg); border: 1px solid var(--border);
            color: var(--text-light);
        }

        /* ── Editor-Spalte ──────────────────────────────────── */
        .pb-editor-col {}
        .pb-card {
            background: var(--white); border-radius: 8px;
            box-shadow: var(--shadow-card); padding: 1.25rem;
        }
        .pb-meta-row {
            display: grid; grid-template-columns: 1fr auto; gap: 0.75rem;
            align-items: start; margin-bottom: 0.75rem;
        }
        .pb-titel-input {
            width: 100%; padding: 0.5rem 0.6rem; border: 1px solid var(--border);
            border-radius: 6px; font-size: 0.95rem; font-weight: 600;
            box-sizing: border-box;
        }
        .pb-kat-select {
            padding: 0.5rem 0.6rem; border: 1px solid var(--border);
            border-radius: 6px; font-size: 0.875rem; background: var(--white);
        }

        /* Tags */
        .pb-tags-row { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-bottom: 0.75rem; }
        .pb-tag-chip {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.55rem; border-radius: 12px; font-size: 0.78rem;
            background: var(--bg); border: 1px solid var(--border); color: var(--text);
        }
        .pb-tag-remove { cursor: pointer; color: var(--text-light); font-size: 0.85rem; line-height: 1; }
        .pb-tag-remove:hover { color: var(--danger, #c0392b); }
        #pb-tag-input {
            border: none; outline: none; font-size: 0.8rem;
            background: transparent; min-width: 80px; flex: 1;
            padding: 0.2rem 0;
        }
        .pb-tags-wrapper {
            display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center;
            padding: 0.35rem 0.6rem; border: 1px solid var(--border); border-radius: 6px;
            background: var(--white); cursor: text; min-height: 38px;
        }

        /* Toolbar */
        .pb-toolbar {
            display: flex; gap: 0.3rem; flex-wrap: wrap;
            margin-bottom: 0.4rem;
        }
        .pb-tb-btn {
            padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.8rem;
            border: 1px solid var(--border); background: var(--bg);
            color: var(--text); cursor: pointer; font-family: monospace;
        }
        .pb-tb-btn:hover { background: var(--border); }

        /* Split */
        .pb-split {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
        }
        .pb-split > div { min-width: 0; }
        @media (max-width: 700px) { .pb-split { grid-template-columns: 1fr; } }
        .pb-split-label {
            font-size: 0.78rem; color: var(--text-light);
            margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em;
        }
        #pb-editor {
            width: 100%; min-height: 420px; padding: 0.75rem;
            border: 1px solid var(--border); border-radius: 6px;
            font-family: monospace; font-size: 0.85rem; line-height: 1.6;
            box-sizing: border-box; resize: none; overflow: hidden;
            background: var(--bg);
        }
        .pb-kontext-row {
            display: flex; align-items: flex-start; gap: 0.6rem;
            margin-bottom: 0.75rem; padding: 0.6rem 0.75rem;
            background: #fffbf0; border: 1px solid #f0d890;
            border-radius: 6px;
        }
        .pb-kontext-label {
            font-size: 0.78rem; font-weight: 600; color: #7a5000;
            white-space: nowrap; padding-top: 0.15rem;
        }
        #pb-kontext {
            flex: 1; border: none; background: transparent; resize: none;
            font-size: 0.82rem; line-height: 1.5; color: var(--text);
            font-family: inherit; outline: none; min-height: 1.5rem;
            overflow: hidden;
        }
        #pb-preview {
            min-height: 420px; padding: 0.75rem 1rem;
            border: 1px solid var(--border); border-radius: 6px;
            background: var(--white); overflow-y: auto;
            font-size: 0.88rem; line-height: 1.7;
        }

        /* Vorschau-Typographie */
        #pb-preview h1 { font-size: 1.15rem; margin: 0.75rem 0 0.4rem; border-bottom: 2px solid var(--primary); padding-bottom: 0.25rem; color: var(--primary); }
        #pb-preview h2 { font-size: 1rem; margin: 0.75rem 0 0.3rem; color: var(--text); }
        #pb-preview h3 { font-size: 0.9rem; margin: 0.6rem 0 0.25rem; color: var(--text-light); }
        #pb-preview strong { color: var(--text); font-weight: 700; }
        #pb-preview em { font-style: italic; color: var(--text); }
        #pb-preview code {
            font-family: monospace; font-size: 0.82rem;
            background: #f0f7ff; border: 1px solid #c5ddf4;
            border-radius: 4px; padding: 0.1rem 0.4rem; color: #0057b8;
        }
        #pb-preview hr { border: none; border-top: 1px solid var(--border); margin: 1rem 0; }
        #pb-preview ul, #pb-preview ol { padding-left: 1.4rem; margin: 0.4rem 0; }
        #pb-preview li { margin-bottom: 0.2rem; }
        #pb-preview blockquote {
            border-left: 3px solid var(--primary); margin: 0.6rem 0;
            padding: 0.4rem 0.75rem; background: var(--bg); color: var(--text-light);
            font-style: italic;
        }
        #pb-preview p { margin: 0.4rem 0; }

        /* Actions */
        .pb-actions { display: flex; gap: 0.75rem; margin-top: 1rem; align-items: center; flex-wrap: wrap; }
        .pb-copy-btn {
            margin-left: auto;
            display: flex; align-items: center; gap: 0.4rem;
        }
    </style>
</head>
<body>
<?php $activeNav = 'prompt_bibliothek'; require __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <header class="content-header">
        <h1>Prompt-Bibliothek</h1>
        <div style="margin-left:auto">
            <button class="btn btn-primary" id="pb-new-btn">+ Neuer Prompt</button>
        </div>
    </header>

    <div class="pb-layout">

        <!-- ── LISTE ── -->
        <div class="pb-list-col">
            <div class="pb-filter-bar" id="pb-filter-bar">
                <button class="pb-filter-btn active" data-kat="">Alle</button>
                <?php foreach ($kategorien as $key => $label): ?>
                    <button class="pb-filter-btn" data-kat="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="pb-list" id="pb-list">
                <p class="pb-empty">Wird geladen …</p>
            </div>
        </div>

        <!-- ── EDITOR ── -->
        <div class="pb-editor-col">
            <div class="pb-card">
                <div class="pb-meta-row">
                    <input type="text" id="pb-titel" class="pb-titel-input" placeholder="Titel …" maxlength="120">
                    <select id="pb-kat" class="pb-kat-select">
                        <?php foreach ($kategorien as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kontext / Quellen -->
                <div class="pb-kontext-row">
                    <span class="pb-kontext-label">Kontext:</span>
                    <textarea id="pb-kontext" rows="1" placeholder="URLs, Dokumente oder Hinweise, die beim Einsatz dieses Prompts bereitgestellt werden müssen …"></textarea>
                </div>

                <!-- Tag-Eingabe -->
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem">
                    <span style="font-size:0.78rem;font-weight:600;color:var(--text-light);white-space:nowrap">Tags:</span>
                    <div class="pb-tags-wrapper" id="pb-tags-wrapper" style="flex:1;margin-bottom:0">
                        <div id="pb-tags-chips"></div>
                        <input type="text" id="pb-tag-input" placeholder="bspw. regional, überregional oder Krankenkassen für Sponsoren-Scraping">
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="pb-toolbar" style="margin-top:0.6rem">
                    <button class="pb-tb-btn" data-wrap="**" data-wrap-end="**" title="Fett"><b>B</b></button>
                    <button class="pb-tb-btn" data-wrap="*" data-wrap-end="*" title="Kursiv"><i>I</i></button>
                    <button class="pb-tb-btn" data-wrap="`" data-wrap-end="`" title="Code/Platzhalter">{ }</button>
                    <button class="pb-tb-btn" data-prefix="# " title="Überschrift 1">H1</button>
                    <button class="pb-tb-btn" data-prefix="## " title="Überschrift 2">H2</button>
                    <button class="pb-tb-btn" data-prefix="### " title="Überschrift 3">H3</button>
                    <button class="pb-tb-btn" data-prefix="- " title="Liste">—</button>
                    <button class="pb-tb-btn" data-prefix="> " title="Hinweis/Zitat">❝</button>
                    <button class="pb-tb-btn" data-insert="---\n" title="Trennlinie">──</button>
                </div>

                <!-- Split: Editor | Vorschau -->
                <div class="pb-split">
                    <div>
                        <div class="pb-split-label">Eingabe (Markdown)</div>
                        <textarea id="pb-editor" placeholder="Prompt-Text …"></textarea>
                    </div>
                    <div>
                        <div class="pb-split-label">Vorschau</div>
                        <div id="pb-preview"></div>
                    </div>
                </div>

                <div class="pb-actions">
                    <button class="btn btn-primary" id="pb-save-btn">Speichern</button>
                    <button class="btn btn-secondary" id="pb-delete-btn" style="display:none">Löschen</button>
                    <span id="pb-status" style="font-size:0.8rem;color:var(--text-light)"></span>
                    <button class="btn btn-secondary pb-copy-btn" id="pb-copy-btn" title="Prompt in Zwischenablage kopieren">
                        📋 Kopieren
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>
    </div>

<script>
(function () {
    'use strict';

    const CSRF  = <?= json_encode($csrfToken) ?>;
    const KATS  = <?= json_encode($kategorien) ?>;
    const API   = 'api/prompt_crud.php';

    let currentId   = null;
    let currentKat  = '';
    let tags        = [];
    let listCache   = [];

    // ── DOM refs ──────────────────────────────────────────────
    const listEl    = document.getElementById('pb-list');
    const filterBar = document.getElementById('pb-filter-bar');
    const titelEl   = document.getElementById('pb-titel');
    const katEl     = document.getElementById('pb-kat');
    const editor    = document.getElementById('pb-editor');
    const preview   = document.getElementById('pb-preview');
    const saveBtn   = document.getElementById('pb-save-btn');
    const delBtn    = document.getElementById('pb-delete-btn');
    const copyBtn   = document.getElementById('pb-copy-btn');
    const statusEl  = document.getElementById('pb-status');
    const tagsChips = document.getElementById('pb-tags-chips');
    const tagInput  = document.getElementById('pb-tag-input');
    const kontextEl = document.getElementById('pb-kontext');

    // ── Auto-grow Textareas ───────────────────────────────────
    function autoGrow(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }
    editor.addEventListener('input', function() { autoGrow(editor); });
    kontextEl.addEventListener('input', function() { autoGrow(kontextEl); });

    // ── Markdown → HTML (Subset) ──────────────────────────────
    function md2html(src) {
        let s = src
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        // Headings
        s = s.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        s = s.replace(/^## (.+)$/gm,  '<h2>$1</h2>');
        s = s.replace(/^# (.+)$/gm,   '<h1>$1</h1>');
        // HR
        s = s.replace(/^---$/gm, '<hr>');
        // Blockquote
        s = s.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
        // Lists
        s = s.replace(/^- (.+)$/gm, '<li>$1</li>');
        s = s.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
        // Bold / italic / code
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g,     '<em>$1</em>');
        s = s.replace(/`(.+?)`/g,       '<code>$1</code>');
        // Paragraphs: wrap double-newline-separated blocks
        s = s.split(/\n{2,}/).map(function(block) {
            block = block.trim();
            if (!block) return '';
            if (/^<(h[123]|ul|ol|li|hr|blockquote)/.test(block)) return block;
            return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
        }).join('\n');
        return s;
    }

    function refreshPreview() {
        preview.innerHTML = md2html(editor.value);
    }

    editor.addEventListener('input', refreshPreview);


    // ── Tags ──────────────────────────────────────────────────
    function renderTags() {
        tagsChips.innerHTML = '';
        tags.forEach(function(t, i) {
            const chip = document.createElement('span');
            chip.className = 'pb-tag-chip';
            chip.innerHTML =
                '<span>' + t.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</span>' +
                '<span class="pb-tag-remove" data-i="' + i + '">×</span>';
            tagsChips.appendChild(chip);
        });
        tagsChips.querySelectorAll('.pb-tag-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                tags.splice(parseInt(btn.dataset.i), 1);
                renderTags();
            });
        });
    }

    document.getElementById('pb-tags-wrapper').addEventListener('click', function() {
        tagInput.focus();
    });

    tagInput.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' || e.key === ',') && tagInput.value.trim()) {
            e.preventDefault();
            const val = tagInput.value.trim().replace(/,/g,'');
            if (val && !tags.includes(val)) { tags.push(val); renderTags(); }
            tagInput.value = '';
        }
        if (e.key === 'Backspace' && tagInput.value === '' && tags.length) {
            tags.pop(); renderTags();
        }
    });

    // ── Toolbar ───────────────────────────────────────────────
    document.querySelectorAll('.pb-tb-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const start = editor.selectionStart, end = editor.selectionEnd;
            const sel   = editor.value.slice(start, end);

            if (btn.dataset.wrap) {
                const wrapped = btn.dataset.wrap + sel + btn.dataset.wrapEnd;
                editor.value  = editor.value.slice(0, start) + wrapped + editor.value.slice(end);
                editor.selectionStart = editor.selectionEnd = start + wrapped.length;
            } else if (btn.dataset.prefix) {
                const lines  = editor.value.slice(0, start).split('\n');
                const lineStart = start - lines[lines.length-1].length;
                editor.value = editor.value.slice(0, lineStart) + btn.dataset.prefix + editor.value.slice(lineStart);
                const offset = btn.dataset.prefix.length;
                editor.selectionStart = editor.selectionEnd = start + offset;
            } else if (btn.dataset.insert) {
                editor.value = editor.value.slice(0, start) + btn.dataset.insert + editor.value.slice(end);
                editor.selectionStart = editor.selectionEnd = start + btn.dataset.insert.length;
            }
            editor.focus();
            refreshPreview();
        });
    });

    // ── API ───────────────────────────────────────────────────
    function api(body) {
        body.csrf_token = CSRF;
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
            body: JSON.stringify(body)
        }).then(function(r) { return r.json(); });
    }

    // ── Liste laden ───────────────────────────────────────────
    function loadList(kat) {
        listEl.innerHTML = '<p class="pb-empty">Wird geladen …</p>';
        const body = { action: 'list' };
        if (kat) body.kategorie = kat;
        api(body).then(function(data) {
            listCache = data.prompts || [];
            renderList();
        }).catch(function() {
            listEl.innerHTML = '<p class="pb-empty" style="color:var(--danger,red)">Fehler beim Laden.</p>';
        });
    }

    function renderList() {
        if (!listCache.length) {
            listEl.innerHTML = '<p class="pb-empty">Keine Prompts vorhanden.</p>';
            return;
        }
        listEl.innerHTML = '';
        listCache.forEach(function(p) {
            const el = document.createElement('div');
            el.className = 'pb-item' + (p.id === currentId ? ' active' : '');
            el.dataset.id = p.id;
            const katLabel = KATS[p.kategorie] || p.kategorie;
            const tagsHtml = (p.tags || []).map(function(t) {
                return '<span class="tag-chip">' + t.replace(/&/g,'&amp;') + '</span>';
            }).join(' ');
            el.innerHTML =
                '<div class="pb-item-titel">' + p.titel.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</div>' +
                '<div class="pb-item-meta">' +
                    '<span class="badge badge-' + p.kategorie + '">' + katLabel + '</span>' +
                    tagsHtml +
                '</div>' +
                '<div class="pb-item-vorschau">' + (p.vorschau || '').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</div>';
            el.addEventListener('click', function() { openPrompt(p.id); });
            listEl.appendChild(el);
        });
    }

    // ── Prompt öffnen ─────────────────────────────────────────
    function openPrompt(id) {
        api({ action: 'get', id: id }).then(function(data) {
            if (!data.ok) return;
            const p   = data.prompt;
            currentId = p.id;
            titelEl.value    = p.titel;
            katEl.value      = p.kategorie;
            tags             = p.tags || [];
            editor.value     = p.inhalt;
            kontextEl.value  = p.kontext || '';
            renderTags();
            refreshPreview();
            autoGrow(editor);
            autoGrow(kontextEl);
            delBtn.style.display = '';
            setStatus('');
            renderList();
            // scroll editor into view on mobile
            if (window.innerWidth < 900) {
                document.querySelector('.pb-editor-col').scrollIntoView({behavior:'smooth'});
            }
        });
    }

    // ── Neu ───────────────────────────────────────────────────
    document.getElementById('pb-new-btn').addEventListener('click', function() {
        currentId        = null;
        titelEl.value    = '';
        katEl.value      = 'frei';
        tags             = [];
        editor.value     = '';
        kontextEl.value  = '';
        renderTags();
        refreshPreview();
        autoGrow(editor);
        autoGrow(kontextEl);
        delBtn.style.display = 'none';
        setStatus('');
        listEl.querySelectorAll('.pb-item').forEach(function(el) { el.classList.remove('active'); });
        titelEl.focus();
    });

    // ── Speichern ─────────────────────────────────────────────
    saveBtn.addEventListener('click', function() {
        const titel = titelEl.value.trim();
        if (!titel) { setStatus('Bitte Titel eingeben.', true); titelEl.focus(); return; }
        if (!editor.value.trim()) { setStatus('Bitte Inhalt eingeben.', true); editor.focus(); return; }

        // Letzten Tag-Input übernehmen
        if (tagInput.value.trim()) {
            const val = tagInput.value.trim();
            if (!tags.includes(val)) { tags.push(val); }
            tagInput.value = '';
            renderTags();
        }

        saveBtn.disabled = true;
        api({
            action: 'save', id: currentId || 0,
            titel: titel, kategorie: katEl.value,
            tags: tags, inhalt: editor.value,
            kontext: kontextEl.value
        }).then(function(data) {
            if (!data.ok) { setStatus(data.error || 'Fehler', true); return; }
            currentId = data.id;
            delBtn.style.display = '';
            setStatus('Gespeichert ✓');
            loadList(currentKat);
        }).catch(function() { setStatus('Fehler', true); })
          .finally(function() { saveBtn.disabled = false; });
    });

    // ── Löschen ───────────────────────────────────────────────
    delBtn.addEventListener('click', function() {
        if (!currentId) return;
        if (!confirm('Prompt wirklich löschen?')) return;
        api({ action: 'delete', id: currentId }).then(function(data) {
            if (!data.ok) { setStatus(data.error || 'Fehler', true); return; }
            currentId = null;
            titelEl.value = ''; katEl.value = 'frei'; tags = []; editor.value = ''; kontextEl.value = '';
            autoGrow(editor); autoGrow(kontextEl);
            renderTags(); refreshPreview();
            delBtn.style.display = 'none';
            setStatus('Gelöscht.');
            loadList(currentKat);
        });
    });

    // ── Kopieren ──────────────────────────────────────────────
    copyBtn.addEventListener('click', function() {
        if (!editor.value) return;
        navigator.clipboard.writeText(editor.value).then(function() {
            const orig = copyBtn.textContent;
            copyBtn.textContent = '✓ Kopiert';
            setTimeout(function() { copyBtn.textContent = orig; }, 1800);
        });
    });

    // ── Filter ────────────────────────────────────────────────
    filterBar.querySelectorAll('.pb-filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBar.querySelectorAll('.pb-filter-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentKat = btn.dataset.kat;
            loadList(currentKat);
        });
    });

    // ── Status ────────────────────────────────────────────────
    function setStatus(msg, isError) {
        statusEl.textContent = msg;
        statusEl.style.color = isError ? 'var(--danger, #c0392b)' : 'var(--text-light)';
    }

    // ── Sidebar Burger ────────────────────────────────────────
    (function() {
        const burger  = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function() { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', close);
        sidebar.querySelectorAll('.nav-item a').forEach(function(l) { l.addEventListener('click', close); });
    })();

    // ── Init ──────────────────────────────────────────────────
    loadList('');

})();
</script>
</body>
</html>
