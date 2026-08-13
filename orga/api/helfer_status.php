<?php
/**
 * Helfer Status ändern (POST)
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
$status = $_POST['status'] ?? '';

$validStatuses = ['neu', 'bestaetigt', 'abgelehnt'];
if ($helferId <= 0 || !in_array($status, $validStatuses, true)) {
    $_SESSION['flash_error'] = 'Ungültige Eingabe.';
    header('Location: ../helfer.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE helfer SET status = :status WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $helferId]);
    $_SESSION['flash_success'] = 'Status aktualisiert.';
} catch (PDOException $e) {
    logError('helfer_status: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../helfer.php');
exit;
