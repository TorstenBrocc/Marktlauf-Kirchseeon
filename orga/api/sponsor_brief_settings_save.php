<?php
/**
 * Briefvorlage-Einstellungen speichern (POST) — nur Admin.
 * Behandelt ausschließlich: sponsor_brief_event_datum, sponsor_brief_antwort_bis, sponsoring_pakete.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsor_briefe.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsor_briefe.php');
    exit;
}

if (!isAdminFromGuard()) {
    $_SESSION['flash_error'] = 'Nur Admins können diese Einstellungen ändern.';
    header('Location: ../sponsor_briefe.php');
    exit;
}

$briefEventDatum = trim($_POST['sponsor_brief_event_datum'] ?? '');
$briefAntwortBis = trim($_POST['sponsor_brief_antwort_bis'] ?? '');

if ($briefEventDatum !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefEventDatum)) {
    $_SESSION['flash_error'] = 'Ungültiges Event-Datum.';
    header('Location: ../sponsor_briefe.php');
    exit;
}
if ($briefAntwortBis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefAntwortBis)) {
    $_SESSION['flash_error'] = 'Ungültige Rückmeldefrist.';
    header('Location: ../sponsor_briefe.php');
    exit;
}

$paketeKeys  = ['hauptsponsor', 'gold', 'silber', 'bronze'];
$paketeNames = ['hauptsponsor' => 'Hauptsponsor', 'gold' => 'Gold', 'silber' => 'Silber', 'bronze' => 'Bronze'];
$pakete = [];
foreach ($paketeKeys as $k) {
    $pakete[] = [
        'key'         => $k,
        'name'        => $paketeNames[$k],
        'investition' => trim($_POST["paket_{$k}_investition"] ?? ''),
        'highlights'  => trim($_POST["paket_{$k}_highlights"]  ?? ''),
    ];
}

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    foreach ([
        'sponsor_brief_event_datum' => $briefEventDatum ?: null,
        'sponsor_brief_antwort_bis' => $briefAntwortBis ?: null,
        'sponsoring_pakete'         => json_encode($pakete, JSON_UNESCAPED_UNICODE),
    ] as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }
    $_SESSION['flash_success'] = 'Einstellungen gespeichert.';
} catch (PDOException $e) {
    logError('sponsor_brief_settings_save: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

$slug = $_POST['slug'] ?? 'erstanschreiben';
header('Location: ../sponsor_briefe.php?slug=' . urlencode($slug));
exit;
