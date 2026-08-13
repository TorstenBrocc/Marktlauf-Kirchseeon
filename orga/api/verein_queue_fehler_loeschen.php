<?php
/**
 * Fehlgeschlagene Einträge aus der verein_versand_queue löschen (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../vereine.php');
    exit;
}

getCurrentUserFromGuard();

try {
    $pdo = getDbConnection();
    $deleted = $pdo->exec("DELETE FROM verein_versand_queue WHERE status = 'fehler'");
    $_SESSION['flash_success'] = $deleted . ' Fehlereinträge aus der Queue gelöscht.';
} catch (PDOException $e) {
    logError('Verein-Queue-Fehler löschen: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../vereine.php');
exit;
