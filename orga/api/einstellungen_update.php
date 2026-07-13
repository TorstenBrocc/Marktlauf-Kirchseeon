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
    'sponsor_brief_event_datum',
    'sponsor_brief_antwort_bis',
    'sponsoring_pakete',
    'llm_provider',
];

$renntag = trim($_POST['renntag_datum'] ?? '');
$veranstaltungsname = trim($_POST['veranstaltungsname'] ?? '');
$kontaktEmail = trim($_POST['kontakt_email'] ?? '');
$raceresultUrl = trim($_POST['raceresult_url'] ?? '');
$trelloUrl = trim($_POST['trello_board_url'] ?? '');
$onedriveUrl = trim($_POST['onedrive_url'] ?? '');

$briefEventDatum = trim($_POST['sponsor_brief_event_datum'] ?? '');
$briefAntwortBis = trim($_POST['sponsor_brief_antwort_bis'] ?? '');

$paketeKeys  = ['hauptsponsor', 'gold', 'silber', 'bronze'];
$paketeNames = ['hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber', 'bronze' => 'Bronze'];
$sponsoringPakete = [];
foreach ($paketeKeys as $k) {
    $sponsoringPakete[] = [
        'key'         => $k,
        'name'        => $paketeNames[$k],
        'investition' => trim($_POST["paket_{$k}_investition"] ?? ''),
        'highlights'  => trim($_POST["paket_{$k}_highlights"]  ?? ''),
    ];
}
$sponsoringPaketeJson = json_encode($sponsoringPakete, JSON_UNESCAPED_UNICODE);

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
        'sponsor_brief_event_datum' => $briefEventDatum ?: null,
        'sponsor_brief_antwort_bis' => $briefAntwortBis ?: null,
        'sponsoring_pakete'         => $sponsoringPaketeJson,
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
