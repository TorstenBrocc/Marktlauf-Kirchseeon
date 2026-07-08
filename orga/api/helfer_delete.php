<?php
/**
 * Helfer löschen (POST, Admin only)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if (!isAdminFromGuard()) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

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
    $stmt = $pdo->prepare('DELETE FROM helfer WHERE id = :id');
    $stmt->execute(['id' => $helferId]);
    $_SESSION['flash_success'] = 'Helfer gelöscht.';
} catch (PDOException $e) {
    logError('helfer_delete: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../helfer.php');
exit;
