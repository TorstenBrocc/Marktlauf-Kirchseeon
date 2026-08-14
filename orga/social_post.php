<?php
/**
 * Post-Detail (Stepper): Text -> Gegenpruefung -> Grafik -> Versand.
 * Spec: intern/social-fahrplan-redesign-spec.md (Schnitt 2).
 * Einstieg immer ueber den Fahrplan (?fahrplan=ID); beim ersten Oeffnen wird
 * das Post-Objekt angelegt und am Fahrplan-Eintrag verankert.
 * Grafik (Schnitt 3) und Versand (Schnitt 4) zeigen bis dahin den Orchestrator-Weg.
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/llm_client.php';
require_once __DIR__ . '/../src/social_anlaesse.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrf    = generateCsrfToken();
$pdo     = getDbConnection();

$fahrplanId = (int) ($_GET['fahrplan'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT f.*, u.name AS zustaendig_name
       FROM social_fahrplan f
  LEFT JOIN users u ON u.id = f.zustaendig_user_id
      WHERE f.id = :id'
);
$stmt->execute(['id' => $fahrplanId]);
$eintrag = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$eintrag) {
    header('Location: social_fahrplan.php');
    exit;
}

$anlaesse   = socialAnlaesse();
$anlassKey  = $eintrag['anlass_key'];
$anlassDef  = $anlaesse[$anlassKey] ?? ['ui' => $anlassKey, 'prompt' => $anlassKey, 'gruppe' => ''];
$mitPresse  = !empty($anlassDef['presse']);

// Post-Objekt beim ersten Oeffnen anlegen und am Fahrplan-Eintrag verankern
$postId = (int) ($eintrag['post_id'] ?? 0);
if ($postId === 0) {
    $pdo->prepare(
        "INSERT INTO post_race_contents (anlass_key, status, erstellt_von) VALUES (:anlass, 'draft', :uid)"
    )->execute(['anlass' => $anlassKey, 'uid' => $user['id']]);
    $postId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE social_fahrplan SET post_id = :pid WHERE id = :id')
        ->execute(['pid' => $postId, 'id' => $fahrplanId]);
}
$post = $pdo->prepare('SELECT * FROM post_race_contents WHERE id = :id');
$post->execute(['id' => $postId]);
$post = $post->fetch(PDO::FETCH_ASSOC);

$provider = llmActiveProvider($pdo);

// Je Anlass gespeicherte Fakten/Prompt + Hashtags (Vereins-Einstellung)
$fakten = '';
$prompt = '';
$hashtags = '';
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('social_prompts', 'social_hashtags')");
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if ($k === 'social_hashtags') { $hashtags = (string) ($v ?? ''); }
        if ($k === 'social_prompts') {
            $store = json_decode((string) ($v ?? ''), true);
            $entry = is_array($store) ? ($store[$anlassKey] ?? []) : [];
            if (is_string($entry)) { $entry = ['prompt' => $entry]; }
            if (is_array($entry)) {
                $prompt = (string) ($entry['prompt'] ?? '');
                $fakten = (string) ($entry['fakten'] ?? '');
            }
        }
    }
} catch (PDOException $e) {
    // Einstellungen evtl. leer
}

// Vorbelegung: gespeicherte Nutzerwerte gewinnen, sonst die Fakten-Defaults des Anlass-Katalogs
if (trim($fakten) === '') {
    $fakten = (string) ($anlassDef['fakten'] ?? '');
}
if (trim($hashtags) === '') {
    $hashtags = socialHashtagsDefault();
}

$schrittText    = trim((string) ($post['llm_text_social'] ?? '')) !== '';
$schrittGeprueft = $post['geprueft_am'] !== null;
$bildPfad       = trim((string) ($post['bild_pfad'] ?? ''));
$schrittGrafik  = $bildPfad !== '';
$schrittVersand = ($post['status'] ?? '') === 'gesendet';
$wartetAufStichtag = ($post['status'] ?? '') === 'approved'
    && $eintrag['zieldatum'] !== null && $eintrag['zieldatum'] > date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Post: <?= htmlspecialchars($anlassDef['ui']) ?> | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .sp-zurueck { font-size: 0.85rem; color: var(--primary-dark); text-decoration: none; }
        .sp-zurueck:hover { text-decoration: underline; }
        .sp-meta { font-size: 0.85rem; color: var(--text-light); }
        .sp-stepper { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 0.9rem 0 1.1rem; }
        .sp-step {
            font-size: 0.8rem; padding: 0.2rem 0.7rem; border-radius: 999px;
            border: 1px solid var(--border); color: var(--text-light); white-space: nowrap;
        }
        .sp-step.done { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .sp-step-line { color: var(--text-light); }
        .sp-feld { margin-bottom: 0.9rem; }
        .sp-feld label { display: block; font-size: 0.82rem; color: var(--text-light); margin-bottom: 0.3rem; }
        .sp-feld textarea {
            width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.9rem;
            line-height: 1.55; padding: 0.6rem 0.7rem; border: 1px solid var(--border);
            border-radius: 6px; resize: vertical; min-height: 70px;
        }
        .sp-feld textarea.gross { min-height: 200px; }
        .sp-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 860px) { .sp-grid2 { grid-template-columns: 1fr; } }
        .sp-zeile { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .sp-zeile select {
            padding: 0.35rem 0.6rem; border: 1px solid var(--border); border-radius: 6px;
            font-size: 0.85rem; background: var(--white); font-family: inherit;
        }
        .sp-hinweis { font-size: 0.82rem; color: var(--text-light); }
        .sp-review-box {
            display: none; margin-top: 0.9rem; padding: 0.75rem 0.9rem; font-size: 0.88rem;
            background: var(--bg); border-left: 3px solid var(--border); border-radius: 6px;
            white-space: pre-wrap; line-height: 1.5;
        }
        .sp-msg { display: none; font-size: 0.85rem; }
        .sp-platzhalter { font-size: 0.88rem; color: var(--text-light); }
        .sp-platzhalter a { color: var(--primary-dark); }
    </style>
</head>
<body>
<?php $activeNav = 'social_fahrplan'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">
        <header class="content-header">
            <a class="sp-zurueck" href="social_fahrplan.php">← Zurück zum Fahrplan</a>
            <h1 style="margin-top:0.4rem"><?= htmlspecialchars($anlassDef['ui']) ?></h1>
            <p class="sp-meta">
                <?= $eintrag['zieldatum'] ? 'fällig ' . htmlspecialchars(date('d.m.Y', strtotime($eintrag['zieldatum']))) : 'ohne Termin' ?>
                · zuständig <?= $eintrag['zustaendig_name'] ? htmlspecialchars($eintrag['zustaendig_name']) : '—' ?>
                <?php if ($post['status'] === 'approved'): ?> · <strong style="color:#065f46">freigegeben</strong><?php endif; ?>
            </p>
            <div class="sp-stepper">
                <span class="sp-step <?= $schrittText ? 'done' : '' ?>" id="sp-step-text"><?= $schrittText ? '✓ ' : '' ?>1 Text</span>
                <span class="sp-step-line">—</span>
                <span class="sp-step <?= $schrittGeprueft ? 'done' : '' ?>" id="sp-step-geprueft"><?= $schrittGeprueft ? '✓ ' : '' ?>2 Geprüft</span>
                <span class="sp-step-line">—</span>
                <span class="sp-step <?= $schrittGrafik ? 'done' : '' ?>"><?= $schrittGrafik ? '✓ ' : '' ?>3 Grafik</span>
                <span class="sp-step-line">—</span>
                <span class="sp-step <?= $schrittVersand ? 'done' : '' ?>"><?= $schrittVersand ? '✓ ' : '' ?>4 Versand</span>
            </div>
        </header>

        <div class="hd-card">
            <h2>1 · Text</h2>
            <div class="sp-grid2">
                <div class="sp-feld">
                    <label for="sp-fakten">Fakten / Stichpunkte <span style="font-weight:400">(je Thema gespeichert)</span> <span class="sp-msg" id="sp-ft-msg"></span></label>
                    <textarea id="sp-fakten" placeholder="z. B. Datum, Uhrzeit, Distanzen, Besonderheiten …"><?= htmlspecialchars($fakten) ?></textarea>
                </div>
                <div class="sp-feld">
                    <label for="sp-prompt">Eigene Anweisung <span style="font-weight:400">(je Thema gespeichert, optional)</span> <span class="sp-msg" id="sp-pr-msg"></span></label>
                    <textarea id="sp-prompt" placeholder="z. B. „locker formulieren, Frage am Ende“"><?= htmlspecialchars($prompt) ?></textarea>
                </div>
            </div>
            <div class="sp-zeile" style="margin-bottom:0.9rem">
                <button class="btn btn-primary" id="sp-generieren"><?= $schrittText ? 'Neu formulieren' : 'Entwürfe generieren' ?></button>
                <label class="sp-hinweis" style="display:inline-flex;align-items:center;gap:0.35rem">
                    <input type="checkbox" id="sp-mit-merkfeld"> Notiz (Merkfeld) mitgeben
                </label>
                <select id="sp-provider" title="KI-Anbieter">
                    <option value="gemini"  <?= $provider === 'gemini'  ? 'selected' : '' ?>>Gemini</option>
                    <option value="mistral" <?= $provider === 'mistral' ? 'selected' : '' ?>>Mistral</option>
                </select>
                <span class="sp-hinweis" id="sp-spinner" style="display:none">⏳ KI läuft …</span>
            </div>
            <p class="sp-hinweis" style="margin:0 0 0.9rem">Hashtags (werden automatisch angehängt):
                <code style="background:var(--bg);padding:0.1rem 0.4rem;border-radius:4px"><?= htmlspecialchars($hashtags) ?></code>
                · <a href="einstellungen.php#social-section" style="color:var(--primary-dark)">ändern</a></p>
            <div class="sp-msg" id="sp-fehler" style="color:#dc2626"></div>
            <div class="<?= $mitPresse ? 'sp-grid2' : '' ?>">
                <div class="sp-feld">
                    <label for="sp-social">Social-Post (Instagram / Facebook)</label>
                    <textarea id="sp-social" class="gross" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?= htmlspecialchars($post['llm_text_social'] ?? '') ?></textarea>
                </div>
                <?php if ($mitPresse): ?>
                <div class="sp-feld">
                    <label for="sp-artikel">Presse-Artikel (Lokalzeitung)</label>
                    <textarea id="sp-artikel" class="gross" placeholder="Entwurf erscheint nach dem KI-Aufruf …"><?= htmlspecialchars($post['llm_text_article'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>
            </div>
            <div class="sp-zeile">
                <button class="btn btn-secondary" id="sp-speichern">Speichern</button>
                <button class="btn btn-primary" id="sp-freigeben">Freigeben</button>
                <span class="sp-msg" id="sp-save-msg" style="color:#16a34a">Gespeichert.</span>
            </div>
        </div>

        <div class="hd-card">
            <h2>2 · Gegenprüfung</h2>
            <div class="sp-zeile">
                <button class="btn btn-secondary" id="sp-pruefen">Mit KI gegenprüfen</button>
                <span class="sp-hinweis" id="sp-pruef-spinner" style="display:none">⏳ prüft …</span>
                <?php if ($schrittGeprueft): ?>
                <span class="sp-hinweis">zuletzt geprüft <?= htmlspecialchars(date('d.m.Y H:i', strtotime($post['geprueft_am']))) ?> (<?= htmlspecialchars($post['geprueft_provider'] ?? '') ?>)</span>
                <?php endif; ?>
            </div>
            <div class="sp-review-box" id="sp-review-box" <?= $post['geprueft_ergebnis'] ? 'style="display:block"' : '' ?>><?= htmlspecialchars($post['geprueft_ergebnis'] ?? '') ?></div>
        </div>

        <div class="hd-card" id="sp-grafik-card">
            <h2>3 · Grafik</h2>
            <div id="sp-grafik-status">
                <?php if ($schrittGrafik): ?>
                <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                    <img src="../<?= htmlspecialchars($bildPfad) ?>" alt="Grafik dieses Posts"
                         style="max-width:240px;border-radius:8px;border:1px solid var(--border)">
                    <p class="sp-hinweis" style="margin:0">Grafik hängt am Post — der Versand nutzt sie.</p>
                </div>
                <?php else: ?>
                <p class="sp-platzhalter" style="margin:0 0 0.7rem">Noch keine Grafik — Editor öffnen, Layout ist
                    zum Thema vorgewählt, „Für Post übernehmen" speichert sie direkt hier.</p>
                <?php endif; ?>
            </div>
            <div class="sp-zeile" style="margin-top:0.7rem">
                <button type="button" class="btn btn-primary btn-small" id="sp-grafik-toggle"><?= $schrittGrafik ? 'Grafik ändern' : 'Grafik erstellen' ?></button>
            </div>
            <div id="sp-grafik-embed-wrap" style="display:none;margin-top:0.9rem;border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--white)">
                <iframe id="sp-grafik-embed" title="Grafik-Editor" loading="lazy"
                        style="width:100%;border:0;display:block;min-height:640px"
                        data-src="vorlagen.php?embed=1&amp;post=<?= (int) $postId ?>&amp;fahrplan=<?= (int) $fahrplanId ?>"></iframe>
            </div>
        </div>

        <div class="hd-card">
            <h2>4 · Versand</h2>
            <?php if ($schrittVersand): ?>
            <p class="sp-hinweis" style="color:#065f46;margin:0 0 0.8rem">
                ✓ Gesendet <?= htmlspecialchars(date('d.m.Y H:i', strtotime($post['gesendet_am']))) ?>
                an <?= htmlspecialchars(str_replace(',', ' + ', (string) $post['gesendet_kanaele'])) ?>
                — <?= htmlspecialchars((string) $post['gesendet_ergebnis']) ?>
            </p>
            <div style="background:#eef7f0;border:1px solid #bfe3c8;border-radius:8px;padding:0.7rem 1rem;margin:0 0 0.9rem">
                <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#065f46;margin:0 0 0.4rem">Jetzt zählt die erste Stunde</p>
                <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;line-height:1.8">
                    <li>1 · Jeden Kommentar schnell beantworten</li>
                    <li>2 · Post in die eigene Story teilen</li>
                    <li>3 · Mitglieder anstupsen: Like + 1 Kommentar + Teilen (Mail ist raus)</li>
                    <li>4 · Getaggte Partner per DM bitten, in Story zu teilen</li>
                    <li>5 · Meilensteine über 2–3 Tage in lokale FB-Gruppen</li>
                </ul>
            </div>
            <?php elseif ($wartetAufStichtag): ?>
            <p class="sp-hinweis" style="margin:0 0 0.8rem">
                Freigegeben — <strong>wartet auf den Stichtag <?= htmlspecialchars(date('d.m.Y', strtotime($eintrag['zieldatum']))) ?></strong>.
                Die tägliche Mail erinnert bei Fälligkeit; gesendet wird per Klick (Auto-Versand folgt später).
            </p>
            <?php endif; ?>
            <p class="sp-hinweis" style="margin:0 0 0.7rem">Gute Slots: <strong>Di–Do</strong> · mittags 12–14 &amp; abends 18–21 Uhr
                (IG: Mi 12:00 / Do 8:30 · FB: Di 12:30) — Details in den <a href="einstellungen.php#social-section" style="color:var(--primary-dark)">Einstellungen</a>.</p>
            <div class="sp-zeile" style="margin-bottom:0.9rem">
                <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.9rem">
                    <input type="checkbox" id="sp-ch-ig" checked> Instagram
                </label>
                <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.9rem">
                    <input type="checkbox" id="sp-ch-fb" checked> Facebook
                </label>
                <button class="btn btn-primary" id="sp-senden"><?= $schrittVersand ? 'Erneut senden (Make.com)' : 'Jetzt senden (Make.com)' ?></button>
                <span class="sp-hinweis" id="sp-send-spinner" style="display:none">⏳ sendet …</span>
            </div>
            <p class="sp-msg" id="sp-send-msg" style="margin:0 0 0.9rem"></p>
            <details class="sp-manuell" style="border-top:1px solid var(--border);padding-top:0.8rem">
                <summary style="cursor:pointer;font-size:0.88rem;color:var(--text-light)">Manuell posten (Fallback) — Text, Bild &amp; Anleitung</summary>
                <div class="sp-zeile" style="margin:0.7rem 0">
                    <button class="btn btn-small btn-secondary" id="sp-copy-social">Social-Text kopieren</button>
                    <?php if ($mitPresse): ?>
                    <button class="btn btn-small btn-secondary" id="sp-copy-artikel">Presse-Text kopieren</button>
                    <?php endif; ?>
                    <?php if ($schrittGrafik): ?>
                    <a class="btn btn-small btn-secondary" href="../<?= htmlspecialchars($bildPfad) ?>" download>Bild herunterladen</a>
                    <?php endif; ?>
                    <a class="btn btn-small" style="background:#1877F2;color:#fff" href="https://business.facebook.com/latest/home?nav_ref=bm_home_redirect&amp;asset_id=1236742862857199" target="_blank" rel="noopener noreferrer">Meta Business Account öffnen ↗</a>
                </div>
                <ul class="sp-hinweis" style="margin:0 0 0.3rem 1.1rem;line-height:1.5">
                    <li>Meta Business Suite → „Beiträge &amp; Reels" → Beitrag erstellen → Kanäle IG + FB anhaken.</li>
                    <li>Bild hochladen, Social-Text als Caption einfügen, Vorschau prüfen → veröffentlichen oder terminieren.</li>
                    <li>Instagram-Feed: kein klickbarer Caption-Link → „Link in Bio". Facebook: Link klickbar.</li>
                </ul>
            </details>
        </div>
    </main>
</div>

<script>
const csrf     = <?= json_encode($csrf) ?>;
const postId   = <?= (int) $postId ?>;
const anlass   = <?= json_encode($anlassKey) ?>;
const hashtags = <?= json_encode($hashtags) ?>;
const mitPresse = <?= $mitPresse ? 'true' : 'false' ?>;

function zeige(id, text, farbe) {
    const el = document.getElementById(id);
    el.textContent = text;
    if (farbe) { el.style.color = farbe; }
    el.style.display = 'inline';
    setTimeout(() => { el.style.display = 'none'; }, 2500);
}
function stepDone(id, label) {
    const el = document.getElementById(id);
    el.classList.add('done');
    el.textContent = '✓ ' + label;
}

// Provider-Wechsel speichern (vereinsweit, wie Orchestrator)
document.getElementById('sp-provider').addEventListener('change', (ev) => {
    fetch('api/social_provider.php', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: csrf, provider: ev.target.value }),
    });
});

// Fakten/Anweisung je Thema speichern (beim Verlassen des Feldes)
function persistAnlassFeld(feld, wert, msgId) {
    const body = new URLSearchParams({ csrf_token: csrf, anlass });
    body.set(feld, wert);
    fetch('api/social_prompt.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => zeige(msgId, d.ok ? '✓ gespeichert' : '⚠️ ' + (d.message || 'Fehler'), d.ok ? '#16a34a' : '#dc2626'))
        .catch(() => zeige(msgId, '⚠️ Netzwerkfehler', '#dc2626'));
}
document.getElementById('sp-fakten').addEventListener('blur', ev => persistAnlassFeld('fakten', ev.target.value, 'sp-ft-msg'));
document.getElementById('sp-prompt').addEventListener('blur', ev => persistAnlassFeld('prompt', ev.target.value, 'sp-pr-msg'));

// Entwuerfe generieren (manueller Klick). Auto-Feuern beim Oeffnen ist bewusst GEGATED
// (Inhaber 2026-08-14) bis zur "Thema zuerst"-IA — der geschaerfte Prompt (llmPromptSocial)
// bleibt aber aktiv, der Klick erzeugt also weiterhin einen wirksamen Post, kein Fakten-Echo.
async function generiereEntwuerfe() {
    const btn = document.getElementById('sp-generieren');
    btn.disabled = true;
    document.getElementById('sp-spinner').style.display = 'inline';
    try {
        const r = await fetch('api/social_generate.php', {
            method: 'POST',
            body: new URLSearchParams({
                csrf_token:   csrf,
                provider:     document.getElementById('sp-provider').value,
                anlass,
                stichpunkte:  document.getElementById('sp-fakten').value,
                prompt:       document.getElementById('sp-prompt').value,
                hashtags,
                mit_merkfeld: document.getElementById('sp-mit-merkfeld').checked ? '1' : '0',
                mit_presse:   mitPresse ? '1' : '0',
            }),
        });
        const d = await r.json();
        if (d.error) {
            zeige('sp-fehler', d.error, '#dc2626');
            return;
        }
        document.getElementById('sp-social').value = d.social;
        if (mitPresse && document.getElementById('sp-artikel')) {
            document.getElementById('sp-artikel').value = d.article;
        }
        btn.textContent = 'Neu formulieren';
    } catch (e) {
        zeige('sp-fehler', 'Netzwerkfehler.', '#dc2626');
    } finally {
        btn.disabled = false;
        document.getElementById('sp-spinner').style.display = 'none';
    }
}
document.getElementById('sp-generieren').addEventListener('click', generiereEntwuerfe);

// Speichern / Freigeben (Post-Objekt)
function speichern(status) {
    const body = new URLSearchParams({
        csrf_token: csrf,
        id:         postId,
        social:     document.getElementById('sp-social').value,
        article:    mitPresse && document.getElementById('sp-artikel') ? document.getElementById('sp-artikel').value : '',
        status,
        provider:   document.getElementById('sp-provider').value,
    });
    fetch('api/social_save.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                zeige('sp-save-msg', status === 'approved' ? 'Freigegeben.' : 'Gespeichert.', '#16a34a');
                if (document.getElementById('sp-social').value.trim() !== '') { stepDone('sp-step-text', '1 Text'); }
            } else {
                zeige('sp-save-msg', '⚠️ ' + (d.error || 'Fehler'), '#dc2626');
            }
        })
        .catch(() => zeige('sp-save-msg', '⚠️ Netzwerkfehler', '#dc2626'));
}
document.getElementById('sp-speichern').addEventListener('click', () => speichern('draft'));
document.getElementById('sp-freigeben').addEventListener('click', () => speichern('approved'));

// Gegenpruefung (persistiert am Post)
document.getElementById('sp-pruefen').addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    const box = document.getElementById('sp-review-box');
    btn.disabled = true;
    document.getElementById('sp-pruef-spinner').style.display = 'inline';
    try {
        const r = await fetch('api/social_review.php', {
            method: 'POST',
            body: new URLSearchParams({
                csrf_token:  csrf,
                post_id:     postId,
                provider:    document.getElementById('sp-provider').value,
                anlass,
                stichpunkte: document.getElementById('sp-fakten').value,
                social:      document.getElementById('sp-social').value,
                article:     mitPresse && document.getElementById('sp-artikel') ? document.getElementById('sp-artikel').value : '',
            }),
        });
        const d = await r.json();
        box.textContent = d.error ? '⚠️ ' + d.error : d.review;
        box.style.display = 'block';
        if (!d.error) { stepDone('sp-step-geprueft', '2 Geprüft'); }
    } catch (e) {
        box.textContent = '⚠️ Netzwerkfehler.';
        box.style.display = 'block';
    } finally {
        btn.disabled = false;
        document.getElementById('sp-pruef-spinner').style.display = 'none';
    }
});

// Versand ueber Make.com (post_dispatch: Log am Post, Fahrplan rueckt vor/erledigt)
document.getElementById('sp-senden').addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    const msg = document.getElementById('sp-send-msg');
    const channels = [];
    if (document.getElementById('sp-ch-ig').checked) channels.push('instagram');
    if (document.getElementById('sp-ch-fb').checked) channels.push('facebook');
    msg.style.display = 'none';
    if (!channels.length) {
        msg.textContent = '⚠️ Bitte mindestens einen Kanal wählen.';
        msg.style.color = '#dc2626'; msg.style.display = 'block';
        return;
    }
    if (!confirm('Veröffentlicht den Post sofort öffentlich auf: ' + channels.join(' + ') + '. Fortfahren?')) {
        return;
    }
    btn.disabled = true;
    document.getElementById('sp-send-spinner').style.display = 'inline';
    try {
        const body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('post_id', postId);
        channels.forEach(c => body.append('channels[]', c));
        const r = await fetch('api/post_dispatch.php', { method: 'POST', body });
        const d = await r.json();
        if (d.ok) {
            location.reload();
            return;
        }
        msg.textContent = '⚠️ ' + (d.message || 'Versand fehlgeschlagen — bitte manuell posten (Fallback unten).');
        msg.style.color = '#dc2626'; msg.style.display = 'block';
    } catch (e) {
        msg.textContent = '⚠️ Netzwerkfehler — bitte manuell posten (Fallback unten).';
        msg.style.color = '#dc2626'; msg.style.display = 'block';
    } finally {
        btn.disabled = false;
        document.getElementById('sp-send-spinner').style.display = 'none';
    }
});

// Manuelles Posten: Texte kopieren
function spCopy(id) {
    const el = document.getElementById(id);
    if (!el || !el.value) return;
    navigator.clipboard.writeText(el.value).catch(() => {});
}
document.getElementById('sp-copy-social').addEventListener('click', () => spCopy('sp-social'));
const copyArtikelBtn = document.getElementById('sp-copy-artikel');
if (copyArtikelBtn) { copyArtikelBtn.addEventListener('click', () => spCopy('sp-artikel')); }

// Grafik-Editor eingebettet (kein Seitenwechsel): lazy laden, Hoehe + Uebernahme via postMessage
(function() {
    const toggle = document.getElementById('sp-grafik-toggle');
    const wrap   = document.getElementById('sp-grafik-embed-wrap');
    const frame  = document.getElementById('sp-grafik-embed');
    if (!toggle || !wrap || !frame) { return; }
    toggle.addEventListener('click', () => {
        const auf = wrap.style.display !== 'none';
        if (auf) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'block';
        if (!frame.src) { frame.src = frame.dataset.src; }  // erst beim Oeffnen laden
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    window.addEventListener('message', (ev) => {
        if (ev.origin !== location.origin || ev.source !== frame.contentWindow) { return; }
        const d = ev.data || {};
        if (d.type === 'vt-height' && typeof d.height === 'number') {
            frame.style.height = Math.max(480, d.height) + 'px';
        } else if (d.type === 'vt-uebernommen') {
            location.reload();  // neue Grafik am Post -> Schritt 3 abgehakt
        }
    });
})();

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
