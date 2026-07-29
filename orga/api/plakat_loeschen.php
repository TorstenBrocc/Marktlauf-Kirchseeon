<?php
/**
 * Plakat-PDF löschen (POST). Wrapper um file_delete-Logik mit Redirect
 * zurück zum Anschreiben-Editor statt zur allgemeinen Dateiablage.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine_briefe.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../vereine_briefe.php');
    exit;
}

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$dateiId = (int) ($_POST['datei_id'] ?? 0);
$redirect = 'vereine_briefe.php';
if (!empty($_POST['redirect']) && preg_match('/^vereine_briefe\.php\?slug=[\w]+$/', $_POST['redirect'])) {
    $redirect = $_POST['redirect'];
}

if ($dateiId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Datei-ID.';
    header('Location: ../' . $redirect);
    exit;
}

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM dateien WHERE id = :id AND bereich = 'orga' AND kategorie = 'plakat'");
    $stmt->execute(['id' => $dateiId]);
    $file = $stmt->fetch();

    if (!$file) {
        $_SESSION['flash_error'] = 'Plakat nicht gefunden.';
        header('Location: ../' . $redirect);
        exit;
    }

    if (!$isAdmin && $file['hochgeladen_von'] !== $user['id']) {
        $_SESSION['flash_error'] = 'Keine Berechtigung zum Löschen.';
        header('Location: ../' . $redirect);
        exit;
    }

    $filePath = __DIR__ . '/../../storage/files/orga/' . $file['dateiname'];

    $pdo->prepare('DELETE FROM dateien WHERE id = :id')->execute(['id' => $dateiId]);

    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $_SESSION['flash_success'] = 'Plakat gelöscht: ' . htmlspecialchars($file['originalname']);
    header('Location: ../' . $redirect);
    exit;

} catch (PDOException $e) {
    logError('Plakat löschen DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../' . $redirect);
    exit;
}
