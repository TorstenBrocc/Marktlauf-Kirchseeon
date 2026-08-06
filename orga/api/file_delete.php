<?php
/**
 * Datei-Lösch Handler (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';
require_once __DIR__ . '/../../src/datei_audit.php';

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

    $deleteStmt = $pdo->prepare('DELETE FROM dateien WHERE id = :id');
    $deleteStmt->execute(['id' => $fileId]);

    if (!empty($file['drive_file_id'])) {
        try {
            driveDelete((string) $file['drive_file_id']);
        } catch (RuntimeException $e) {
            logError('File delete -> Drive: ' . $e->getMessage());
        }
    } else {
        $filePath = __DIR__ . '/../../storage/files/' . $file['bereich'] . '/' . $file['dateiname'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    dateiAudit($pdo, 'delete', [
        'datei_id'      => (int) $file['id'],
        'drive_file_id' => $file['drive_file_id'] ?? null,
        'originalname'  => $file['originalname'],
        'kategorie'     => $file['kategorie'] ?? null,
        'benutzer_id'   => $user['id'],
    ]);

    $_SESSION['flash_success'] = 'Datei gelöscht: ' . htmlspecialchars($file['originalname']);
    header('Location: ../dateien.php?tab=' . $file['bereich']);
    exit;

} catch (PDOException $e) {
    logError('File delete error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../dateien.php');
    exit;
}
