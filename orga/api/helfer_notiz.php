<?php
/**
 * Helfer Notiz speichern (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';

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
$notiz = trim($_POST['notiz'] ?? '');

if ($helferId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Eingabe.';
    header('Location: ../helfer.php');
    exit;
}

$notiz = mb_substr($notiz, 0, 5000);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE helfer SET notiz = :notiz WHERE id = :id');
    $stmt->execute(['notiz' => $notiz ?: null, 'id' => $helferId]);
    $_SESSION['flash_success'] = 'Notiz gespeichert.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../helfer.php');
exit;
