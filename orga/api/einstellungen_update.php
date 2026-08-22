<?php
/**
 * Einstellungen speichern (POST) — nur Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/offene_todos.php'; // REMINDER_FREQUENZ_OPTIONEN

// Autosave (fetch mit X-Requested-With) bekommt JSON statt Redirect+Flash; ein klassisches
// Formular-Submit ohne den Header läuft weiterhin über Redirect mit Flash (No-JS-Fallback).
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '';
function ein_respond(bool $ok, string $message, string $redirect = '../einstellungen.php'): void {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$ok) { http_response_code(422); }
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $message;
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ein_respond(false, 'Methode nicht erlaubt.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    ein_respond(false, 'Ungültige Anfrage.');
}

if (!isAdminFromGuard()) {
    ein_respond(false, 'Nur Admins können Einstellungen ändern.', '../index.php');
}

$allowedKeys = [
    'renntag_datum',
    'veranstaltungsname',
    'kontakt_email',
    'raceresult_url',
    'trello_board_url',
    'onedrive_url',
    'strava_url',
    'raceresult_hinweis',
    'trello_hinweis',
    'onedrive_hinweis',
    'strava_hinweis',
    'meta_business_url',
    'meta_business_hinweis',
    'sponsor_brief_event_datum',
    'sponsor_brief_antwort_bis',
    'sponsoring_pakete',
    'llm_provider',
    'drive_root_orga_id',
    'drive_root_helfer_id',
    'social_hashtags',
    'beste_sendezeiten',
    'raceresult_api_url',
    'sponsor_merkfeld',
    'reminder_versandtage',
    'reminder_pause_bis',
];

$renntag = trim($_POST['renntag_datum'] ?? '');
$veranstaltungsname = trim($_POST['veranstaltungsname'] ?? '');
$kontaktEmail = trim($_POST['kontakt_email'] ?? '');
$raceresultUrl = trim($_POST['raceresult_url'] ?? '');
$trelloUrl = trim($_POST['trello_board_url'] ?? '');
$onedriveUrl = trim($_POST['onedrive_url'] ?? '');
$stravaUrl = trim($_POST['strava_url'] ?? '');
$metaBusinessUrl = trim($_POST['meta_business_url'] ?? '');
$driveRootOrga   = trim((string) ($_POST['drive_root_orga_id'] ?? ''));
$driveRootHelfer = trim((string) ($_POST['drive_root_helfer_id'] ?? ''));

// Social Media (umgezogen aus dem Orchestrator, Schnitt 5 Redesign-Spec)
$socialHashtags   = mb_substr(trim((string) ($_POST['social_hashtags'] ?? '')), 0, 500);
$besteSendezeiten = mb_substr(trim((string) ($_POST['beste_sendezeiten'] ?? '')), 0, 2000);
$raceresultApiUrl = trim((string) ($_POST['raceresult_api_url'] ?? ''));

// Sponsoren-Merkfeld (Bank-/Vereinsdaten), umgezogen aus der Sponsoren-Übersicht.
$sponsorMerkfeld = mb_substr(trim((string) ($_POST['sponsor_merkfeld'] ?? '')), 0, 5000);

// Versandtage des ToDo-Digests: Checkbox-Gruppe Mo–So. Ein leeres Set ist eine gültige
// Wahl (Digest aus) und wird als Sonderwert 'keine' gespeichert — unterscheidbar vom
// fehlenden Key (= Default: alle Tage). Der Marker reminder_versandtage_gesendet zeigt,
// dass die Gruppe auf der Seite stand; ohne ihn wird der Key nicht angefasst (sonst
// würde jeder fremde POST ohne die Checkboxen den Zeitplan auf 'keine' kippen).
$versandtageGesendet = ($_POST['reminder_versandtage_gesendet'] ?? '') === '1';
$reminderVersandtage = null;
if ($versandtageGesendet) {
    $tage = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['reminder_versandtage'] ?? [])),
        static fn (int $t): bool => $t >= 1 && $t <= 7
    )));
    sort($tage);
    $reminderVersandtage = $tage === [] ? 'keine' : implode(',', $tage);
}

// Urlaubs-Pause des Digests (einschließlich; leer = aktiv).
$reminderPauseBis = trim((string) ($_POST['reminder_pause_bis'] ?? ''));
if ($reminderPauseBis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminderPauseBis)) {
    ein_respond(false, 'Ungültiges Datum bei „Pausiert bis".');
}

// Zugangsdaten-Notizen (Freitext, nur Admin sichtbar) — auf 2000 Zeichen gekappt.
$raceresultHinweis = mb_substr(trim($_POST['raceresult_hinweis'] ?? ''), 0, 2000);
$trelloHinweis     = mb_substr(trim($_POST['trello_hinweis'] ?? ''), 0, 2000);
$onedriveHinweis   = mb_substr(trim($_POST['onedrive_hinweis'] ?? ''), 0, 2000);
$stravaHinweis     = mb_substr(trim($_POST['strava_hinweis'] ?? ''), 0, 2000);
$metaBusinessHinweis = mb_substr(trim($_POST['meta_business_hinweis'] ?? ''), 0, 2000);

// ACHTUNG, hier lag ein Datenverlust: Dieser Endpoint hat früher zusätzlich
// `sponsor_brief_event_datum`, `sponsor_brief_antwort_bis` und `sponsoring_pakete` geschrieben —
// aus POST-Feldern, die `orga/einstellungen.php` gar nicht rendert. Jedes Speichern der
// allgemeinen Einstellungen hat die drei Werte damit auf leer gesetzt: Paketpreise und
// -Highlights weg, {{event_datum}} und {{antwort_bis}} leer im Sponsorenbrief.
// Diese drei Keys gehören zur Anschreiben-Seite und werden ausschließlich von
// `api/sponsor_brief_settings_save.php` geschrieben. Ein Endpoint je Datensatz.

if ($veranstaltungsname !== '' && mb_strlen($veranstaltungsname) > 200) {
    ein_respond(false, 'Veranstaltungsname zu lang (max. 200 Zeichen).');
}
if ($kontaktEmail !== '' && !filter_var($kontaktEmail, FILTER_VALIDATE_EMAIL)) {
    ein_respond(false, 'Ungültige Kontakt-E-Mail-Adresse.');
}
if ($renntag !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renntag)) {
    ein_respond(false, 'Ungültiges Datumsformat.');
}
foreach ([
    [$raceresultUrl,    'Ungültige Race-Result-URL.'],
    [$trelloUrl,        'Ungültige Trello-Board-URL.'],
    [$onedriveUrl,      'Ungültige OneDrive-URL.'],
    [$stravaUrl,        'Ungültige Strava-URL.'],
    [$metaBusinessUrl,  'Ungültige Meta-Business-URL.'],
    [$raceresultApiUrl, 'Ungültiger RaceResult-SimpleAPI-Link.'],
] as [$urlVal, $urlMsg]) {
    if ($urlVal !== '' && !filter_var($urlVal, FILTER_VALIDATE_URL)) {
        ein_respond(false, $urlMsg);
    }
}

// Hinweis: Die früheren Validierungen für sponsor_brief_event_datum / sponsor_brief_antwort_bis
// standen hier auf nie gesetzten Variablen (die Reads wurden mit dem Datenverlust-Fix entfernt)
// und ließen jedes Speichern fehlschlagen. Diese Keys gehören zur Anschreiben-Seite und werden
// ausschließlich von sponsor_brief_settings_save.php gelesen, validiert und geschrieben.

try {
    $pdo = getDbConnection();

    $settings = [
        'renntag_datum'             => $renntag ?: null,
        'veranstaltungsname'        => $veranstaltungsname ?: null,
        'kontakt_email'             => $kontaktEmail ?: null,
        'raceresult_url'            => $raceresultUrl ?: null,
        'trello_board_url'          => $trelloUrl ?: null,
        'onedrive_url'              => $onedriveUrl ?: null,
        'strava_url'                => $stravaUrl ?: null,
        'raceresult_hinweis'        => $raceresultHinweis ?: null,
        'trello_hinweis'            => $trelloHinweis ?: null,
        'onedrive_hinweis'          => $onedriveHinweis ?: null,
        'strava_hinweis'            => $stravaHinweis ?: null,
        'meta_business_url'         => $metaBusinessUrl ?: null,
        'meta_business_hinweis'     => $metaBusinessHinweis ?: null,
        'drive_root_orga_id'        => $driveRootOrga ?: null,
        'drive_root_helfer_id'      => $driveRootHelfer ?: null,
        'social_hashtags'           => $socialHashtags ?: null,
        'beste_sendezeiten'         => $besteSendezeiten ?: null,
        'raceresult_api_url'        => $raceresultApiUrl ?: null,
        'sponsor_merkfeld'          => $sponsorMerkfeld ?: null,
        'reminder_pause_bis'        => $reminderPauseBis ?: null,
    ];
    // Nur schreiben, wenn die Checkbox-Gruppe wirklich im POST stand (Marker, s. o.).
    if ($reminderVersandtage !== null) {
        $settings['reminder_versandtage'] = $reminderVersandtage;
    }

    $stmt = $pdo->prepare('INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value2');

    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }

    ein_respond(true, 'Einstellungen gespeichert.');

} catch (PDOException $e) {
    logError('Einstellungen update error: ' . $e->getMessage());
    ein_respond(false, 'Datenbankfehler.');
}
