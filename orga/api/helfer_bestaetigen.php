<?php
/**
 * Helfer bestätigen und Zugangs-Mail versenden (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../helfer.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../helfer.php');
    exit;
}

$helferId = (int) ($_POST['helfer_id'] ?? 0);

if ($helferId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Eingabe.';
    header('Location: ../helfer.php');
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id, uuid, vorname, nachname, email, status FROM helfer WHERE id = :id');
    $stmt->execute(['id' => $helferId]);
    $helfer = $stmt->fetch();

    if (!$helfer) {
        $_SESSION['flash_error'] = 'Helfer nicht gefunden.';
        header('Location: ../helfer.php');
        exit;
    }

    if ($helfer['status'] !== 'neu') {
        $_SESSION['flash_error'] = 'Nur neue Anmeldungen können bestätigt werden.';
        header('Location: ../helfer.php');
        exit;
    }

    $uuid = $helfer['uuid'];
    if (empty($uuid)) {
        $uuid = uuid();

        $stmt = $pdo->prepare('UPDATE helfer SET uuid = :uuid, status = :status WHERE id = :id');
        $stmt->execute(['uuid' => $uuid, 'status' => 'bestaetigt', 'id' => $helferId]);
    } else {
        $stmt = $pdo->prepare('UPDATE helfer SET status = :status WHERE id = :id');
        $stmt->execute(['status' => 'bestaetigt', 'id' => $helferId]);
    }

    try {
        $config = getConfig();
        $appUrl = rtrim($config['app']['url'] ?? 'https://atsv-kirchseeon-marktlauf.de', '/');
        $zugangLink = $appUrl . '/helfer/zugang.php?uuid=' . urlencode($uuid);

        sendHelferBestaetigung(
            $helfer['email'],
            $helfer['vorname'] . ' ' . $helfer['nachname'],
            $zugangLink
        );
        $_SESSION['flash_success'] = 'Helfer bestätigt und E-Mail mit Zugangslink versendet.';
    } catch (Throwable $e) {
        logError('Mail send error (helfer_bestaetigen): ' . $e->getMessage());
        $_SESSION['flash_success'] = 'Helfer bestätigt. E-Mail konnte nicht gesendet werden (siehe Log).';
    }

} catch (PDOException $e) {
    logError('DB error (helfer_bestaetigen): ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../helfer.php');
exit;
