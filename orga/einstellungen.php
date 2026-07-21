<?php
/**
 * Einstellungen (Admin only)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

if (!$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins haben Zugriff auf die Einstellungen.';
    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = getDbConnection();
$config = getConfig();

$settings = [];
try {
    $stmt = $pdo->query('SELECT `key`, `value` FROM einstellungen');
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

$renntagDatum = $settings['renntag_datum'] ?? '';
$veranstaltungsname = $settings['veranstaltungsname'] ?? '';
$kontaktEmail = $settings['kontakt_email'] ?? '';
$raceresultUrl = $settings['raceresult_url'] ?? '';
$trelloUrl = $settings['trello_board_url'] ?? '';
$onedriveUrl = $settings['onedrive_url'] ?? '';
$stravaUrl = $settings['strava_url'] ?? '';

$raceresultHinweis = $settings['raceresult_hinweis'] ?? '';
$trelloHinweis = $settings['trello_hinweis'] ?? '';
$onedriveHinweis = $settings['onedrive_hinweis'] ?? '';
$stravaHinweis = $settings['strava_hinweis'] ?? '';

// Vorbelegung: geteilter Vereins-Account. Erste Zeile = Login, zweite Zeile = Passwort.
$loginDefault = "info@atsv-kirchseeon-marktlauf.de\n";
$raceresultHinweisVal = $raceresultHinweis !== '' ? $raceresultHinweis : $loginDefault;
$trelloHinweisVal     = $trelloHinweis !== ''     ? $trelloHinweis     : $loginDefault;
$onedriveHinweisVal   = $onedriveHinweis !== ''   ? $onedriveHinweis   : $loginDefault;
$stravaHinweisVal     = $stravaHinweis !== ''     ? $stravaHinweis     : $loginDefault;

$smtpHost = $config['smtp_host'] ?? '–';
$smtpPort = $config['smtp_port'] ?? '–';
$smtpFrom = $config['smtp_from'] ?? $config['smtp_user'] ?? '–';

$makeWebhookUrl    = (string) ($config['make_webhook_url'] ?? '');
$makeWebhookSecret = (string) ($config['make_webhook_secret'] ?? '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Einstellungen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <style>
        .settings-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .settings-section h2 {
            font-size: 1.1rem;
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-row.single {
            grid-template-columns: 1fr;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .form-group input,
        .form-group textarea {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 0.875rem;
            font-family: inherit;
        }
        .form-group textarea {
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .settings-hint {
            font-size: 0.8rem;
            color: var(--text-light);
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.6rem 0.8rem;
            margin: 0 0 1rem 0;
            line-height: 1.4;
        }
        .info-block {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1rem;
        }
        .info-block dl {
            margin: 0;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
        }
        .info-block dt {
            font-weight: 500;
            color: var(--text-light);
        }
        .info-block dd {
            margin: 0;
        }
        .info-hint {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.75rem;
        }
        .btn-row {
            /* Wrapper für Formular-Buttons */
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php $activeNav = 'einstellungen'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Einstellungen</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <form method="post" action="api/einstellungen_update.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="settings-section">
                    <h2>Eventdaten</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="renntag_datum">Renntag-Datum</label>
                            <input type="date" id="renntag_datum" name="renntag_datum" value="<?= htmlspecialchars($renntagDatum) ?>">
                        </div>
                        <div class="form-group">
                            <label for="veranstaltungsname">Veranstaltungsname</label>
                            <input type="text" id="veranstaltungsname" name="veranstaltungsname" value="<?= htmlspecialchars($veranstaltungsname) ?>" maxlength="200" placeholder="z.B. 10. Kirchseeoner Marktlauf">
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="kontakt_email">Kontakt-E-Mail</label>
                            <input type="email" id="kontakt_email" name="kontakt_email" value="<?= htmlspecialchars($kontaktEmail) ?>" placeholder="info@atsv-kirchseeon-marktlauf.de">
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Schnellzugriff-Links (Dashboard)</h2>
                    <p class="settings-hint">Zugangsdaten-Notizen sind im Dashboard <strong>nur für Admins</strong> hinter dem&nbsp;ⓘ-Symbol sichtbar. Sie werden im Klartext gespeichert – lege hier idealerweise nur geteilte Vereins-Zugänge ab, keine persönlichen Passwörter.</p>

                    <div class="form-row single" id="link-raceresult_hinweis">
                        <div class="form-group">
                            <label for="raceresult_url">Race-Result-URL</label>
                            <input type="url" id="raceresult_url" name="raceresult_url" value="<?= htmlspecialchars($raceresultUrl) ?>" placeholder="https://my.raceresult.com/...">
                        </div>
                        <div class="form-group">
                            <label for="raceresult_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="raceresult_hinweis" name="raceresult_hinweis" rows="3"><?= htmlspecialchars($raceresultHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-trello_hinweis">
                        <div class="form-group">
                            <label for="trello_board_url">Trello-Board-URL</label>
                            <input type="url" id="trello_board_url" name="trello_board_url" value="<?= htmlspecialchars($trelloUrl) ?>" placeholder="https://trello.com/b/...">
                        </div>
                        <div class="form-group">
                            <label for="trello_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="trello_hinweis" name="trello_hinweis" rows="3"><?= htmlspecialchars($trelloHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-onedrive_hinweis">
                        <div class="form-group">
                            <label for="onedrive_url">OneDrive-URL</label>
                            <input type="url" id="onedrive_url" name="onedrive_url" value="<?= htmlspecialchars($onedriveUrl) ?>" placeholder="https://onedrive.live.com/...">
                        </div>
                        <div class="form-group">
                            <label for="onedrive_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="onedrive_hinweis" name="onedrive_hinweis" rows="3"><?= htmlspecialchars($onedriveHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-strava_hinweis">
                        <div class="form-group">
                            <label for="strava_url">Strava-URL</label>
                            <input type="url" id="strava_url" name="strava_url" value="<?= htmlspecialchars($stravaUrl) ?>" placeholder="https://www.strava.com/clubs/...">
                        </div>
                        <div class="form-group">
                            <label for="strava_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="strava_hinweis" name="strava_hinweis" rows="3"><?= htmlspecialchars($stravaHinweisVal) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </div>
                </div>
            </form>

            <div class="settings-section" id="branchen-section">
                <h2>Sponsor-Branchen</h2>
                <p style="font-size:0.85rem;color:var(--text-light);margin-bottom:0.75rem">
                    Diese Liste bestimmt die Optionen im Branchen-Dropdown der Sponsorenliste.
                </p>
                <?php
                $bRaw = $settings['sponsor_branchen'] ?? '[]';
                $bListe = json_decode($bRaw, true) ?? [];
                ?>
                <ul id="branchen-liste" style="list-style:none;padding:0;margin:0 0 0.75rem;display:flex;flex-direction:column;gap:0.4rem">
                    <?php foreach ($bListe as $i => $b): ?>
                        <li style="display:flex;align-items:center;gap:0.5rem">
                            <span style="flex:1;font-size:0.875rem"><?= htmlspecialchars($b) ?></span>
                            <button type="button" class="btn-icon branche-del" data-index="<?= $i ?>" title="Löschen">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="display:flex;gap:0.5rem;align-items:center">
                    <input type="text" id="branche-neu-input" placeholder="Neue Branche …" maxlength="100"
                           style="flex:1;padding:0.45rem 0.6rem;border:1px solid var(--border);border-radius:6px;font-size:0.875rem">
                    <button type="button" class="btn btn-secondary" id="branche-add-btn">Hinzufügen</button>
                </div>
                <div style="margin-top:0.75rem">
                    <button type="button" class="btn btn-primary" id="branchen-save-btn">Branchen speichern</button>
                    <span id="branchen-status" style="font-size:0.8rem;color:var(--text-light);margin-left:0.75rem"></span>
                </div>
            </div>

            <div class="settings-section">
                <h2>SMTP-Konfiguration</h2>
                <div class="info-block">
                    <dl>
                        <dt>Host</dt>
                        <dd><?= htmlspecialchars((string) $smtpHost) ?></dd>
                        <dt>Port</dt>
                        <dd><?= htmlspecialchars((string) $smtpPort) ?></dd>
                        <dt>From-Adresse</dt>
                        <dd><?= htmlspecialchars((string) $smtpFrom) ?></dd>
                    </dl>
                </div>
                <p class="info-hint">Änderungen nur über <code>storage/config.php</code></p>
            </div>

            <div class="settings-section">
                <h2>Social-Media Auto-Posting (Make.com)</h2>
                <div class="info-block">
                    <dl>
                        <dt>Status</dt>
                        <dd><?= $makeWebhookUrl !== '' ? 'aktiv' : 'nicht konfiguriert – manueller Versand' ?></dd>
                        <dt>Webhook-URL</dt>
                        <dd><?= $makeWebhookUrl !== '' ? htmlspecialchars($makeWebhookUrl) : '–' ?></dd>
                        <dt>Secret</dt>
                        <dd><?= $makeWebhookSecret !== '' ? htmlspecialchars($makeWebhookSecret) : '–' ?></dd>
                    </dl>
                </div>
                <p class="info-hint">Dasselbe Secret gehört in den Filter des Make.com-Szenarios. Änderungen nur über <code>storage/config.php</code>.</p>
            </div>
        </main>
    </div>
    <script>
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    })();

    // ── Branchen-Verwaltung ───────────────────────────────────
    (function () {
        const csrf      = <?= json_encode($csrfToken) ?>;
        const liste     = document.getElementById('branchen-liste');
        const neuInput  = document.getElementById('branche-neu-input');
        const addBtn    = document.getElementById('branche-add-btn');
        const saveBtn   = document.getElementById('branchen-save-btn');
        const statusEl  = document.getElementById('branchen-status');

        function getBranchen() {
            return Array.from(liste.querySelectorAll('li span')).map(function(s) {
                return s.textContent.trim();
            });
        }

        function addRow(name) {
            const li = document.createElement('li');
            li.style.cssText = 'display:flex;align-items:center;gap:0.5rem';
            const span = document.createElement('span');
            span.style.cssText = 'flex:1;font-size:0.875rem';
            span.textContent = name;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-icon branche-del';
            btn.title = 'Löschen';
            btn.textContent = '✕';
            btn.addEventListener('click', function() { li.remove(); });
            li.appendChild(span);
            li.appendChild(btn);
            liste.appendChild(li);
        }

        // Bestehende Löschen-Buttons verdrahten
        liste.querySelectorAll('.branche-del').forEach(function(btn) {
            btn.addEventListener('click', function() { btn.closest('li').remove(); });
        });

        addBtn.addEventListener('click', function() {
            const val = neuInput.value.trim();
            if (!val) return;
            addRow(val);
            neuInput.value = '';
            neuInput.focus();
        });
        neuInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); }
        });

        saveBtn.addEventListener('click', function() {
            const branchen = getBranchen();
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('key', 'sponsor_branchen');
            body.set('value', JSON.stringify(branchen));
            saveBtn.disabled = true;
            fetch('api/einstellungen_json_update.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            }).then(function(r) { return r.json(); })
              .then(function(d) {
                statusEl.textContent = d.ok ? 'Gespeichert ✓' : (d.message || 'Fehler');
                statusEl.style.color = d.ok ? 'var(--primary)' : 'var(--error)';
                setTimeout(function() { statusEl.textContent = ''; }, 2500);
              })
              .catch(function() { statusEl.textContent = 'Fehler'; })
              .finally(function() { saveBtn.disabled = false; });
        });
    })();
    </script>
</body>
</html>
