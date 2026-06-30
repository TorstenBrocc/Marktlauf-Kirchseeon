<?php
/**
 * Datei-Lösch Handler (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dateien.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../dateien.php');
    exit;
}

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$fileId = (int) ($_POST['file_id'] ?? 0);

if ($fileId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Datei-ID.';
    header('Location: ../dateien.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM dateien WHERE id = :id');
    $stmt->execute(['id' => $fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        $_SESSION['flash_error'] = 'Datei nicht gefunden.';
        header('Location: ../dateien.php');
        exit;
    }

    if (!$isAdmin && $file['hochgeladen_von'] !== $user['id']) {
        $_SESSION['flash_error'] = 'Keine Berechtigung zum Löschen dieser Datei.';
        header('Location: ../dateien.php?tab=' . $file['bereich']);
        exit;
    }

    $filePath = __DIR__ . '/../../storage/files/' . $file['bereich'] . '/' . $file['dateiname'];

    $deleteStmt = $pdo->prepare('DELETE FROM dateien WHERE id = :id');
    $deleteStmt->execute(['id' => $fileId]);

    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $_SESSION['flash_success'] = 'Datei gelöscht: ' . htmlspecialchars($file['originalname']);
    header('Location: ../dateien.php?tab=' . $file['bereich']);
    exit;

} catch (PDOException $e) {
    logError('File delete error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../dateien.php');
    exit;
}
