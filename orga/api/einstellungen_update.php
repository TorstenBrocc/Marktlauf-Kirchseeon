<?php
/**
 * Einstellungen speichern (POST) — nur Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../einstellungen.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../einstellungen.php');
    exit;
}

if (!isAdminFromGuard()) {
    $_SESSION['flash_error'] = 'Nur Admins können Einstellungen ändern.';
    header('Location: ../index.php');
    exit;
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
    'raceresult_api_url',
    'sponsor_merkfeld',
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
$raceresultApiUrl = trim((string) ($_POST['raceresult_api_url'] ?? ''));

// Sponsoren-Merkfeld (Bank-/Vereinsdaten), umgezogen aus der Sponsoren-Übersicht.
$sponsorMerkfeld = mb_substr(trim((string) ($_POST['sponsor_merkfeld'] ?? '')), 0, 5000);

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
    $_SESSION['flash_error'] = 'Veranstaltungsname zu lang (max. 200 Zeichen).';
    header('Location: ../einstellungen.php');
    exit;
}

if ($kontaktEmail !== '' && !filter_var($kontaktEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Ungültige Kontakt-E-Mail-Adresse.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($renntag !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renntag)) {
    $_SESSION['flash_error'] = 'Ungültiges Datumsformat.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($raceresultUrl !== '' && !filter_var($raceresultUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültige Race-Result-URL.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($trelloUrl !== '' && !filter_var($trelloUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültige Trello-Board-URL.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($onedriveUrl !== '' && !filter_var($onedriveUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültige OneDrive-URL.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($stravaUrl !== '' && !filter_var($stravaUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültige Strava-URL.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($metaBusinessUrl !== '' && !filter_var($metaBusinessUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültige Meta-Business-URL.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($raceresultApiUrl !== '' && !filter_var($raceresultApiUrl, FILTER_VALIDATE_URL)) {
    $_SESSION['flash_error'] = 'Ungültiger RaceResult-SimpleAPI-Link.';
    header('Location: ../einstellungen.php');
    exit;
}

if ($briefEventDatum !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefEventDatum)) {
    $_SESSION['flash_error'] = 'Ungültiges Event-Datum.';
    header('Location: ../einstellungen.php');
    exit;
}
if ($briefAntwortBis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefAntwortBis)) {
    $_SESSION['flash_error'] = 'Ungültige Rückmeldefrist.';
    header('Location: ../einstellungen.php');
    exit;
}

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
        'raceresult_api_url'        => $raceresultApiUrl ?: null,
        'sponsor_merkfeld'          => $sponsorMerkfeld ?: null,
    ];

    $stmt = $pdo->prepare('INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value2');

    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }

    $_SESSION['flash_success'] = 'Einstellungen gespeichert.';
    header('Location: ../einstellungen.php');
    exit;

} catch (PDOException $e) {
    logError('Einstellungen update error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../einstellungen.php');
    exit;
}
