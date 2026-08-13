<?php
/**
 * Helfer-Stammdaten aktualisieren (POST) — Admin + Orga.
 * Bearbeitet: vorname, nachname, email, phone, status, notiz.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

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
    $_SESSION['flash_error'] = 'Ungültige Helfer-ID.';
    header('Location: ../helfer.php');
    exit;
}

$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$status = $_POST['status'] ?? 'neu';
$notiz = trim($_POST['notiz'] ?? '');

if ($vorname === '' || $nachname === '') {
    $_SESSION['flash_error'] = 'Vor- und Nachname sind Pflichtfelder.';
    header('Location: ../helfer_form.php?id=' . $helferId);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    header('Location: ../helfer_form.php?id=' . $helferId);
    exit;
}

if (!in_array($status, ['neu', 'bestaetigt', 'abgelehnt'], true)) {
    $status = 'neu';
}

try {
    $pdo = getDbConnection();

    $check = $pdo->prepare('SELECT id FROM helfer WHERE id = :id');
    $check->execute(['id' => $helferId]);
    if (!$check->fetchColumn()) {
        $_SESSION['flash_error'] = 'Helfer nicht gefunden.';
        header('Location: ../helfer.php');
        exit;
    }

    $stmt = $pdo->prepare('
        UPDATE helfer SET
            vorname = :vorname,
            nachname = :nachname,
            email = :email,
            phone = :phone,
            status = :status,
            notiz = :notiz
        WHERE id = :id
    ');
    $stmt->execute([
        'vorname'  => mb_substr($vorname, 0, 100),
        'nachname' => mb_substr($nachname, 0, 100),
        'email'    => mb_substr($email, 0, 255),
        'phone'    => mb_substr($phone, 0, 30),
        'status'   => $status,
        'notiz'    => $notiz !== '' ? $notiz : null,
        'id'       => $helferId,
    ]);

    $_SESSION['flash_success'] = 'Helfer „' . $vorname . ' ' . $nachname . '" aktualisiert.';
    header('Location: ../helfer.php');
    exit;

} catch (PDOException $e) {
    logError('Helfer update error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../helfer_form.php?id=' . $helferId);
    exit;
}
