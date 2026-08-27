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
$driveRootOrga   = $settings['drive_root_orga_id'] ?? '';
$driveRootHelfer = $settings['drive_root_helfer_id'] ?? '';
$kontaktEmail = $settings['kontakt_email'] ?? '';
$raceresultUrl = $settings['raceresult_url'] ?? '';
$trelloUrl = $settings['trello_board_url'] ?? '';
$onedriveUrl = $settings['onedrive_url'] ?? '';
$stravaUrl = $settings['strava_url'] ?? '';
$metaBusinessUrl = $settings['meta_business_url'] ?? '';
$sponsorMerkfeld = $settings['sponsor_merkfeld'] ?? '';

require_once __DIR__ . '/../src/social_anlaesse.php';
require_once __DIR__ . '/../src/offene_todos.php'; // REMINDER_TAGE_LABELS/_PRESETS, reminderVersandtage()
$reminderVersandtage = reminderVersandtage($settings['reminder_versandtage'] ?? null);
$reminderPauseBis    = trim((string) ($settings['reminder_pause_bis'] ?? ''));
$socialHashtags   = trim((string) ($settings['social_hashtags'] ?? '')) ?: socialHashtagsDefault();
$besteSendezeiten = trim((string) ($settings['beste_sendezeiten'] ?? '')) ?: besteSendezeitenDefault();
$bszStruktur = (function () use ($settings): array {
    $d = json_decode((string) ($settings['beste_sendezeiten_struktur'] ?? ''), true);
    return is_array($d) && $d !== [] ? $d : besteSendezeitenStrukturDefault();
})();
$raceresultApiUrl = $settings['raceresult_api_url'] ?? '';
$raceresultHinweis = $settings['raceresult_hinweis'] ?? '';
$trelloHinweis = $settings['trello_hinweis'] ?? '';
$onedriveHinweis = $settings['onedrive_hinweis'] ?? '';
$stravaHinweis = $settings['strava_hinweis'] ?? '';
$metaBusinessHinweis = $settings['meta_business_hinweis'] ?? '';

// Vorbelegung: geteilter Vereins-Account. Erste Zeile = Login, zweite Zeile = Passwort.
$loginDefault = "info@atsv-kirchseeon-marktlauf.de\n";
$raceresultHinweisVal = $raceresultHinweis !== '' ? $raceresultHinweis : $loginDefault;
$trelloHinweisVal     = $trelloHinweis !== ''     ? $trelloHinweis     : $loginDefault;
$onedriveHinweisVal   = $onedriveHinweis !== ''   ? $onedriveHinweis   : $loginDefault;
$stravaHinweisVal     = $stravaHinweis !== ''     ? $stravaHinweis     : $loginDefault;
$metaBusinessHinweisVal = $metaBusinessHinweis !== '' ? $metaBusinessHinweis : $loginDefault;

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
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .settings-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--shadow-card);
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
        /* Überschriften der Schnellzugriff-URL-Felder als farbige Pille,
           in der Button-Farbe der jeweiligen Kachel im Cockpit. */
        .form-group label.link-pill {
            align-self: flex-start;
            display: inline-block;
            color: #fff;
            font-weight: 600;
            padding: 0.15rem 0.7rem;
            border-radius: 999px;
            letter-spacing: 0.01em;
        }
        .link-pill-raceresult { background: #C41011; }
        .link-pill-trello     { background: #0079BF; }
        .link-pill-onedrive   { background: #6264A7; }
        .link-pill-strava     { background: #FC4C02; }
        .link-pill-meta       { background: #0866FF; }
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
        /* Autosave-Status statt Speicher-Button — dezent, nur kurz farbig bei Erfolg/Fehler. */
        .autosave-status { font-size: 0.85rem; color: var(--text-light); transition: color 0.2s; }
        .autosave-status.ok { color: var(--primary); font-weight: 600; }
        .autosave-status.err { color: var(--error); font-weight: 600; }
        /* Versandtage: 7 Wochentags-Schalter als Pillen (Checkbox versteckt, Zustand am Rahmen). */
        .reminder-tage {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .reminder-tag input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .reminder-tag span {
            display: inline-block;
            min-width: 2.6rem;
            text-align: center;
            padding: 0.35rem 0.6rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 0.85rem;
            cursor: pointer;
            user-select: none;
            color: var(--text-light);
            background: var(--bg);
        }
        .reminder-tag input:checked + span {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .reminder-tag input:focus-visible + span {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
        .reminder-presets {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-light);
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
                    <div class="form-row">
                        <div class="form-group">
                            <label for="drive_root_orga_id">Drive-Wurzel „Orga" (Ordner-ID)</label>
                            <input type="text" id="drive_root_orga_id" name="drive_root_orga_id" value="<?= htmlspecialchars($driveRootOrga) ?>" placeholder="leer = Ordner „Orga" im Laufwerk">
                            <small style="color:var(--text-light)">Einstieg des „Orga"-Tabs im Dateien-Browser. Ordner-ID = der Teil hinter <code>/folders/</code> in der Drive-URL. Leer = automatisch „Orga".</small>
                        </div>
                        <div class="form-group">
                            <label for="drive_root_helfer_id">Drive-Wurzel „Helfer" (Ordner-ID)</label>
                            <input type="text" id="drive_root_helfer_id" name="drive_root_helfer_id" value="<?= htmlspecialchars($driveRootHelfer) ?>" placeholder="leer = Ordner „Helfer" im Laufwerk">
                            <small style="color:var(--text-light)">Einstieg des „Helfer"-Tabs. Leer = automatisch „Helfer".</small>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Erinnerungs-Mails</h2>
                    <div class="form-row single">
                        <div class="form-group">
                            <label>Versandtage des ToDo-Digests</label>
                            <div class="reminder-tage">
                                <?php foreach (REMINDER_TAGE_LABELS as $tag => $tagLabel): ?>
                                    <label class="reminder-tag">
                                        <input type="checkbox" name="reminder_versandtage[]" value="<?= $tag ?>" <?= in_array($tag, $reminderVersandtage, true) ? 'checked' : '' ?>>
                                        <span><?= $tagLabel ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php /* Marker that the checkbox group was rendered — without it an
                                        all-unchecked group would simply be absent from the POST and
                                        the endpoint could not tell "off" from "field not on page". */ ?>
                                <input type="hidden" name="reminder_versandtage_gesendet" value="1">
                            </div>
                            <div class="reminder-presets">
                                Schnellwahl:
                                <?php foreach (REMINDER_TAGE_PRESETS as $presetLabel => $presetTage): ?>
                                    <button type="button" class="btn btn-small btn-secondary reminder-preset" data-tage="<?= implode(',', $presetTage) ?>"><?= htmlspecialchars($presetLabel) ?></button>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:var(--text-light)">Gilt für die Sammel-Mail „Offene ToDos Sponsoring" (inkl. Social-Fahrplan). Freitags kommt der volle Überblick, an anderen Tagen nur Neues. Alle Tage aus = Digest aus. Einzel-Erinnerungen zu heute fälligen Orga-Aufgaben kommen unabhängig davon am Fälligkeitstag.</small>
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="reminder_pause_bis">Pausiert bis einschließlich</label>
                            <input type="date" id="reminder_pause_bis" name="reminder_pause_bis" value="<?= htmlspecialchars($reminderPauseBis) ?>">
                            <small style="color:var(--text-light)">Für Urlaub o. Ä.: bis zu diesem Datum (einschließlich) wird kein Digest verschickt, danach geht es automatisch weiter. Leer = aktiv.</small>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Sponsoren</h2>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="sponsor_merkfeld">Merkfeld (Bankverbindung, Vereins-/Steuernummer …)</label>
                            <textarea id="sponsor_merkfeld" name="sponsor_merkfeld" rows="6" maxlength="5000" placeholder="Bankverbindung, Vereins-/Steuernummer, Notizen zur Sponsoren-Abwicklung …"><?= htmlspecialchars($sponsorMerkfeld) ?></textarea>
                            <small style="color:var(--text-light)">Freitext, nur für die Orga sichtbar. Wird in der Sponsoren-Übersicht nicht mehr angezeigt.</small>
                        </div>
                    </div>
                </div>

                <div class="settings-section" id="social-section">
                    <h2>Social Media</h2>
                    <p class="settings-hint">Vereinsweite Vorgaben für die Social-Pipeline und das Post-Detail.</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin-bottom:1rem">
                        <div style="border:1px solid var(--border);border-radius:8px;padding:0.8rem 1rem">
                            <label for="beste_sendezeiten" style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-light);margin:0 0 0.5rem;display:block">Beste Sendezeiten (Studien 2025/26)</label>
                            <textarea id="beste_sendezeiten" name="beste_sendezeiten" rows="4" style="font-size:0.85rem;line-height:1.6" placeholder="Instagram: …&#10;Facebook: …&#10;Kernzeit: …&#10;Meiden: …"><?= htmlspecialchars($besteSendezeiten) ?></textarea>
                            <p class="settings-hint" style="margin:0.4rem 0 0">Eine Zeile je Kanal/Regel. Wird im Post-Detail (Thema-Kachel + eigene Kachel) angezeigt. Nach 4–6 Wochen gegen die eigenen Insights halten (Meta Business Suite → „Aktivste Zeiten").</p>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:8px;padding:0.8rem 1rem">
                            <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-light);margin:0 0 0.5rem">Nach dem Posten (erste Stunde)</p>
                            <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;line-height:1.9">
                                <li>1 · Jeden Kommentar schnell beantworten</li>
                                <li>2 · Post in die eigene Story teilen</li>
                                <li>3 · Mitglieder anstupsen: Like + 1 Kommentar + Teilen</li>
                                <li>4 · Getaggte Partner per DM bitten, in Story zu teilen</li>
                                <li>5 · Meilensteine über 2–3 Tage in lokale FB-Gruppen</li>
                            </ul>
                        </div>
                    </div>
                    <?php $wtLabels = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So']; ?>
                    <div class="form-row single">
                        <div class="form-group">
                            <label>Beste Sendezeiten — strukturiert für den Auto-Versand-Timer <span style="font-weight:400">(je Kanal &amp; Wochentag; leer = kein bevorzugter Slot)</span></label>
                            <input type="hidden" name="bsz_gesendet" value="1">
                            <div style="overflow-x:auto">
                                <table style="border-collapse:collapse;font-size:0.82rem">
                                    <thead><tr><th style="padding:0.2rem 0.5rem"></th>
                                        <?php foreach ($wtLabels as $n => $lbl): ?><th style="padding:0.2rem 0.4rem;font-weight:600;color:var(--text-light)"><?= $lbl ?></th><?php endforeach; ?>
                                    </tr></thead>
                                    <tbody>
                                        <?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook'] as $ch => $chLbl): ?>
                                        <tr><td style="padding:0.2rem 0.5rem;font-weight:600;white-space:nowrap"><?= $chLbl ?></td>
                                            <?php for ($n = 1; $n <= 7; $n++): $v = $bszStruktur[$ch][$n] ?? ($bszStruktur[$ch][(string) $n] ?? ''); ?>
                                            <td style="padding:0.15rem 0.25rem"><input type="time" name="bsz_<?= $ch ?>_<?= $n ?>" value="<?= htmlspecialchars(preg_match('/^\d{2}:\d{2}$/', (string) $v) ? (string) $v : '') ?>" style="width:6.2rem;font-size:0.82rem;padding:0.2rem 0.3rem"></td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="settings-hint" style="margin:0.4rem 0 0">Diese Zeiten speisen den geplanten „zur besten Zeit senden"-Timer und die Zeit-Vorbelegung je Post. Der Freitext oben bleibt die menschenlesbare Notiz.</p>
                        </div>
                    </div>
                    <div class="form-row single">
                        <div class="form-group">
                            <label for="social_hashtags">Standard-Hashtags (werden an jeden Social-Post gehängt)</label>
                            <textarea id="social_hashtags" name="social_hashtags" rows="2" placeholder="#marktlauf #kirchseeon #atsv"><?= htmlspecialchars($socialHashtags) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="raceresult_api_url">RaceResult SimpleAPI-Link (Datenquelle „Renntag-Nachbericht")</label>
                            <input type="url" id="raceresult_api_url" name="raceresult_api_url" value="<?= htmlspecialchars($raceresultApiUrl) ?>" placeholder="https://my.raceresult.com/377952/RRPublish/data/list?...">
                            <p class="settings-hint" style="margin:0.3rem 0 0">In RaceResult unter „Zugriffsrechte/Freigabe → Freigabe (SimpleAPI)", Typ „Liste" anlegen. Ohne Link werden Beispiel-Daten verwendet.</p>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2>Schnellzugriff-Links (Cockpit)</h2>
                    <p class="settings-hint">Zugangsdaten-Notizen sind im Cockpit <strong>nur für Admins</strong> hinter dem&nbsp;ⓘ-Symbol sichtbar. Sie werden im Klartext gespeichert – lege hier idealerweise nur geteilte Vereins-Zugänge ab, keine persönlichen Passwörter.</p>

                    <div class="form-row single" id="link-raceresult_hinweis">
                        <div class="form-group">
                            <label for="raceresult_url" class="link-pill link-pill-raceresult">Race-Result-URL</label>
                            <input type="url" id="raceresult_url" name="raceresult_url" value="<?= htmlspecialchars($raceresultUrl) ?>" placeholder="https://my.raceresult.com/...">
                        </div>
                        <div class="form-group">
                            <label for="raceresult_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="raceresult_hinweis" name="raceresult_hinweis" rows="3"><?= htmlspecialchars($raceresultHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-trello_hinweis">
                        <div class="form-group">
                            <label for="trello_board_url" class="link-pill link-pill-trello">Trello-Board-URL</label>
                            <input type="url" id="trello_board_url" name="trello_board_url" value="<?= htmlspecialchars($trelloUrl) ?>" placeholder="https://trello.com/b/...">
                        </div>
                        <div class="form-group">
                            <label for="trello_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="trello_hinweis" name="trello_hinweis" rows="3"><?= htmlspecialchars($trelloHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-onedrive_hinweis">
                        <div class="form-group">
                            <label for="onedrive_url" class="link-pill link-pill-onedrive">OneDrive-URL</label>
                            <input type="url" id="onedrive_url" name="onedrive_url" value="<?= htmlspecialchars($onedriveUrl) ?>" placeholder="https://onedrive.live.com/...">
                        </div>
                        <div class="form-group">
                            <label for="onedrive_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="onedrive_hinweis" name="onedrive_hinweis" rows="3"><?= htmlspecialchars($onedriveHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-strava_hinweis">
                        <div class="form-group">
                            <label for="strava_url" class="link-pill link-pill-strava">Strava-URL</label>
                            <input type="url" id="strava_url" name="strava_url" value="<?= htmlspecialchars($stravaUrl) ?>" placeholder="https://www.strava.com/clubs/...">
                        </div>
                        <div class="form-group">
                            <label for="strava_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="strava_hinweis" name="strava_hinweis" rows="3"><?= htmlspecialchars($stravaHinweisVal) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row single" id="link-meta_business_hinweis">
                        <div class="form-group">
                            <label for="meta_business_url" class="link-pill link-pill-meta">Meta Business</label>
                            <input type="url" id="meta_business_url" name="meta_business_url" value="<?= htmlspecialchars($metaBusinessUrl) ?>" placeholder="https://business.facebook.com/...">
                        </div>
                        <div class="form-group">
                            <label for="meta_business_hinweis">Zugangsdaten / Notiz (nur Admin)</label>
                            <textarea id="meta_business_hinweis" name="meta_business_hinweis" rows="3"><?= htmlspecialchars($metaBusinessHinweisVal) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="btn-row">
                        <span id="autosave-status" class="autosave-status">Änderungen werden automatisch gespeichert.</span>
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
                            <input type="text" class="branche-name" value="<?= htmlspecialchars($b) ?>" data-orig="<?= htmlspecialchars($b) ?>" maxlength="100"
                                   style="flex:1;padding:0.4rem 0.55rem;border:1px solid var(--border);border-radius:6px;font-size:0.875rem">
                            <button type="button" class="btn-icon branche-del" title="Löschen">✕</button>
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
    // Autosave: Einstellungen speichern automatisch (kein Speicher-Button mehr). Tippen ->
    // entprellt (700 ms), Feld verlassen / Auswahl -> sofort. Das ganze Formular wird gepostet
    // (der Endpoint schreibt nur die gerenderten Keys -> kein Datenverlust).
    (function () {
        const form = document.querySelector('form[action="api/einstellungen_update.php"]');
        if (!form) { return; }
        const statusEl = document.getElementById('autosave-status');
        let timer = null;

        function setStatus(text, cls) {
            if (!statusEl) { return; }
            statusEl.textContent = text;
            statusEl.className = 'autosave-status' + (cls ? ' ' + cls : '');
        }

        function save() {
            clearTimeout(timer); timer = null;
            setStatus('Speichern …', '');
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: new FormData(form)
            })
                .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unerwartete Antwort.' }; }); })
                .then(function (d) {
                    if (d && d.ok) { setStatus('✓ Gespeichert', 'ok'); }
                    else { setStatus('⚠ ' + ((d && d.message) || 'Nicht gespeichert'), 'err'); }
                })
                .catch(function () { setStatus('⚠ Netzwerkfehler — nicht gespeichert', 'err'); });
        }

        form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(save, 700); });
        form.addEventListener('change', save);
        form.addEventListener('submit', function (e) { e.preventDefault(); save(); });

        // Schnellwahl-Presets: Wochentags-Schalter vorbelegen. Programmatic checks fire no
        // events, so trigger the form's change handler explicitly to autosave the preset.
        document.querySelectorAll('.reminder-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tage = btn.dataset.tage.split(',');
                document.querySelectorAll('input[name="reminder_versandtage[]"]').forEach(function (cb) {
                    cb.checked = tage.indexOf(cb.value) !== -1;
                });
                save();
            });
        });
    })();

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

        function inputs() { return Array.from(liste.querySelectorAll('.branche-name')); }
        function getBranchen() {
            return inputs().map(function(i) { return i.value.trim(); }).filter(Boolean);
        }
        // Umbenennungen: bestehende Zeile (data-orig gesetzt), deren Name sich geändert hat.
        function getRenames() {
            const out = [];
            inputs().forEach(function(i) {
                const orig = (i.dataset.orig || '').trim();
                const neu = i.value.trim();
                if (orig && neu && orig !== neu) out.push({ old: orig, 'new': neu });
            });
            return out;
        }

        function addRow(name) {
            const li = document.createElement('li');
            li.style.cssText = 'display:flex;align-items:center;gap:0.5rem';
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'branche-name';
            inp.maxLength = 100;
            inp.value = name;
            inp.dataset.orig = ''; // neue Branche -> keine Migration
            inp.style.cssText = 'flex:1;padding:0.4rem 0.55rem;border:1px solid var(--border);border-radius:6px;font-size:0.875rem';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-icon branche-del';
            btn.title = 'Löschen';
            btn.textContent = '✕';
            btn.addEventListener('click', function() { li.remove(); });
            li.appendChild(inp);
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
            const body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('key', 'sponsor_branchen');
            body.set('value', JSON.stringify(getBranchen()));
            body.set('renames', JSON.stringify(getRenames()));
            saveBtn.disabled = true;
            fetch('api/einstellungen_json_update.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: body
            }).then(function(r) { return r.json(); })
              .then(function(d) {
                if (d.ok) {
                    // Umbenennungen sind gespeichert -> Ausgangswerte nachziehen.
                    inputs().forEach(function(i) { i.dataset.orig = i.value.trim(); });
                    statusEl.textContent = d.migrated ? ('Gespeichert ✓ (' + d.migrated + ' Sponsoren angepasst)') : 'Gespeichert ✓';
                    statusEl.style.color = 'var(--primary)';
                } else {
                    statusEl.textContent = d.message || 'Fehler';
                    statusEl.style.color = 'var(--error)';
                }
                setTimeout(function() { statusEl.textContent = ''; }, 3000);
              })
              .catch(function() { statusEl.textContent = 'Fehler'; statusEl.style.color = 'var(--error)'; })
              .finally(function() { saveBtn.disabled = false; });
        });
    })();
    </script>
</body>
</html>
