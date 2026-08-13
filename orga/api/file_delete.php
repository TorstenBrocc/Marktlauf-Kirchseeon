<?php
/**
 * Datei aus dem geteilten Google-Laufwerk löschen (POST). Adressiert per Drive-file-id.
 * Sicherheit: nur Dateien im geteilten Laufwerk. Zurück zum Ordner-Browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';
require_once __DIR__ . '/../../src/datei_audit.php';

$tab    = ($_POST['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$folder = trim((string) ($_POST['folder'] ?? ''));
$back   = '../dateien.php?tab=' . $tab . ($folder !== '' ? '&folder=' . urlencode($folder) : '');

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
    $_SESSION['flash_error'] = 'Datei nicht gefunden.';
    header('Location: ' . $back);
    exit;
}

try {
    driveTrash($fid);
    dateiAudit($pdo, 'delete', [
        'drive_file_id' => $fid,
        'originalname'  => (string) ($meta['name'] ?? ''),
        'benutzer_id'   => getCurrentUserFromGuard()['id'] ?? null,
    ]);
    $_SESSION['flash_success'] = 'In den Papierkorb verschoben: ' . htmlspecialchars((string) ($meta['name'] ?? ''));
} catch (RuntimeException $e) {
    logError('file_delete Drive: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Löschen fehlgeschlagen.';
}

header('Location: ' . $back);
exit;
