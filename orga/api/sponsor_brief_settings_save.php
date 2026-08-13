<?php
/**
 * Anschreiben-Einstellungen speichern (POST) — Ziel: orga/anschreiben_einstellungen.php.
 * Behandelt ausschließlich: sponsor_brief_event_datum, sponsor_brief_antwort_bis, sponsoring_pakete.
 * (Paketpreise sind immer netto; Brutto-Ausnahme pro Sponsor über die Sponsor-Maske.)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../anschreiben_einstellungen.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../anschreiben_einstellungen.php');
    exit;
}


$briefEventDatum = trim($_POST['sponsor_brief_event_datum'] ?? '');
$briefAntwortBis = trim($_POST['sponsor_brief_antwort_bis'] ?? '');

if ($briefEventDatum !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefEventDatum)) {
    $_SESSION['flash_error'] = 'Ungültiges Event-Datum.';
    header('Location: ../anschreiben_einstellungen.php');
    exit;
}
if ($briefAntwortBis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $briefAntwortBis)) {
    $_SESSION['flash_error'] = 'Ungültige Rückmeldefrist.';
    header('Location: ../anschreiben_einstellungen.php');
    exit;
}

// `sponsoring_pakete` wird hier NICHT mehr geschrieben: Preise und Leistungen werden seit
// 2026-08-12 auf `orga/pakete.php` gepflegt (api/paket_crud.php). Würde dieser Endpoint die
// Pakete weiter aus POST zusammenbauen, leerte jedes Speichern der Termine die Paketdaten —
// exakt der Datenverlust, der zuvor in `einstellungen_update.php` steckte.

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    foreach ([
        'sponsor_brief_event_datum' => $briefEventDatum ?: null,
        'sponsor_brief_antwort_bis' => $briefAntwortBis ?: null,
    ] as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value, 'value2' => $value]);
    }
    $_SESSION['flash_success'] = 'Einstellungen gespeichert.';
} catch (PDOException $e) {
    logError('sponsor_brief_settings_save: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../anschreiben_einstellungen.php');
exit;
