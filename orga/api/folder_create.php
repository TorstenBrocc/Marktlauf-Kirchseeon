<?php
/**
 * Neuen Unterordner im geteilten Google-Laufwerk anlegen (POST).
 * Sicherheit: der Elternordner muss im geteilten Laufwerk liegen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

$tab    = ($_POST['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$parent = trim((string) ($_POST['parent'] ?? ''));
$back   = 'dateien.php?tab=' . $tab . ($parent !== '' ? '&folder=' . urlencode($parent) : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $back);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$name = str_replace(['/', '\\'], '', $name);

if (!driveConfigured() || $parent === '' || $name === '' || mb_strlen($name) > 120) {
    $_SESSION['flash_error'] = 'Bitte einen gültigen Ordnernamen angeben.';
    header('Location: ' . $back);
    exit;
}

$pmeta = driveFileMeta($parent);
if ($pmeta === null
    || (string) ($pmeta['driveId'] ?? '') !== driveSharedDriveId()
    || (string) ($pmeta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
    $_SESSION['flash_error'] = 'Elternordner ungültig.';
    header('Location: ' . $back);
    exit;
}

try {
    driveCreateFolder($name, $parent);
    $_SESSION['flash_success'] = 'Ordner angelegt: ' . htmlspecialchars($name);
} catch (RuntimeException $e) {
    logError('folder_create Drive: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Ordner konnte nicht angelegt werden.';
}

header('Location: ' . $back);
exit;
