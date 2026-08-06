<?php
/**
 * Datei oder Ordner im geteilten Google-Laufwerk umbenennen (POST).
 * Adressiert per Drive-file-id. Sicherheit: nur innerhalb des Laufwerks.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

$tab    = ($_POST['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$folder = trim((string) ($_POST['folder'] ?? ''));
$back   = '../dateien.php?tab=' . $tab . ($folder !== '' ? '&folder=' . urlencode($folder) : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $back);
    exit;
}

$fid  = trim((string) ($_POST['fid'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$name = str_replace(['/', '\\'], '', $name);

if ($fid === '' || $name === '' || mb_strlen($name) > 200 || !driveConfigured()) {
    $_SESSION['flash_error'] = 'Bitte einen gültigen Namen angeben.';
    header('Location: ' . $back);
    exit;
}

$meta = driveFileMeta($fid);
if ($meta === null || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()) {
    $_SESSION['flash_error'] = 'Element nicht gefunden.';
    header('Location: ' . $back);
    exit;
}

try {
    driveRename($fid, $name);
    $_SESSION['flash_success'] = 'Umbenannt in: ' . htmlspecialchars($name);
} catch (RuntimeException $e) {
    logError('file_rename Drive: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Umbenennen fehlgeschlagen.';
}

header('Location: ' . $back);
exit;
