<?php
/**
 * Plakat aus dem geteilten Google-Laufwerk löschen (POST). Adressiert per Drive-file-id.
 * Wrapper mit Redirect zurück zum Anschreiben-Editor (sponsor_briefe / vereine_briefe).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';
require_once __DIR__ . '/../../src/datei_audit.php';

$redirect = 'vereine_briefe.php';
if (!empty($_POST['redirect']) && preg_match('/^(vereine_briefe|sponsor_briefe)\.php\?slug=[\w\-]+$/', (string) $_POST['redirect'])) {
    $redirect = (string) $_POST['redirect'];
}
$back = '../' . $redirect;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $back);
    exit;
}

$fid = trim((string) ($_POST['fid'] ?? ''));
$pdo = getDbConnection();

if ($fid === '' || !driveConfigured()) {
    $_SESSION['flash_error'] = 'Ungültige Datei.';
    header('Location: ' . $back);
    exit;
}

$meta = driveFileMeta($fid);
if ($meta === null || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()) {
    $_SESSION['flash_error'] = 'Plakat nicht gefunden.';
    header('Location: ' . $back);
    exit;
}

try {
    driveDelete($fid);
    dateiAudit($pdo, 'delete', [
        'drive_file_id' => $fid,
        'originalname'  => (string) ($meta['name'] ?? ''),
        'benutzer_id'   => getCurrentUserFromGuard()['id'] ?? null,
    ]);
    $_SESSION['flash_success'] = 'Plakat gelöscht: ' . htmlspecialchars((string) ($meta['name'] ?? ''));
} catch (RuntimeException $e) {
    logError('plakat_loeschen Drive: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Löschen fehlgeschlagen.';
}

header('Location: ' . $back);
exit;
