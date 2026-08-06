<?php
/**
 * Datei-Upload in einen Ordner des geteilten Google-Laufwerks (POST).
 * Adressiert per Ziel-Ordner-id (folder=). Sicherheit: der Zielordner muss im
 * geteilten Laufwerk liegen. Behält MIME-Whitelist, 10-MB-Limit und Bild-Downscale.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/google_drive.php';
require_once __DIR__ . '/../../src/datei_audit.php';

$tab    = ($_POST['tab'] ?? 'orga') === 'helfer' ? 'helfer' : 'orga';
$folder = trim((string) ($_POST['folder'] ?? ''));
$back   = 'dateien.php?tab=' . $tab . ($folder !== '' ? '&folder=' . urlencode($folder) : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $back);
    exit;
}

$user = getCurrentUserFromGuard();
$pdo  = getDbConnection();

if (!driveConfigured() || $folder === '') {
    $_SESSION['flash_error'] = 'Google Drive nicht konfiguriert oder kein Zielordner.';
    header('Location: ' . $back);
    exit;
}

// Security: target must be a folder inside our shared drive.
$fmeta = driveFileMeta($folder);
if ($fmeta === null
    || (string) ($fmeta['driveId'] ?? '') !== driveSharedDriveId()
    || (string) ($fmeta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
    $_SESSION['flash_error'] = 'Zielordner ungültig.';
    header('Location: ' . $back);
    exit;
}

if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Es wurde keine gültige Datei ausgewählt.';
    header('Location: ' . $back);
    exit;
}

$file         = $_FILES['datei'];
$originalName = basename($file['name']);
$tmpUpload    = $file['tmp_name'];
$size         = (int) $file['size'];

if ($size > 10 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'Die Datei ist zu groß (max. 10 MB).';
    header('Location: ' . $back);
    exit;
}

$allowedMimes = [
    'application/pdf'                                                         => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    'image/png'                                                               => 'png',
    'image/jpeg'                                                              => 'jpg',
];
$detectedMime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpUpload);
if (!isset($allowedMimes[$detectedMime])) {
    $_SESSION['flash_error'] = 'Dateityp nicht erlaubt. Erlaubt: PDF, DOCX, XLSX, PNG, JPG.';
    header('Location: ' . $back);
    exit;
}

// Auf eine temporäre Arbeitsdatei bringen (für Downscale + Upload), dann wieder entfernen.
$work = tempnam(sys_get_temp_dir(), 'upl_');
if ($work === false || !move_uploaded_file($tmpUpload, $work)) {
    $_SESSION['flash_error'] = 'Datei konnte nicht verarbeitet werden.';
    header('Location: ' . $back);
    exit;
}

if ($detectedMime === 'image/png' || $detectedMime === 'image/jpeg') {
    downscaleImage($work, $detectedMime, 2000);
}

try {
    $fid = driveUploadToFolder($folder, $work, $originalName, $detectedMime);
    dateiAudit($pdo, 'upload', [
        'drive_file_id' => $fid,
        'originalname'  => $originalName,
        'benutzer_id'   => $user['id'],
    ]);
    $_SESSION['flash_success'] = 'Hochgeladen: ' . htmlspecialchars($originalName);
} catch (RuntimeException $e) {
    logError('file_upload Drive: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Upload fehlgeschlagen.';
}

@unlink($work);

// Optionaler Redirect zurück zu einer aufrufenden Seite (z. B. Plakate-Karte).
if (!empty($_POST['redirect_after']) && preg_match('/^[\w\-]+\.php(\?[\w=&%\-]+)?$/', (string) $_POST['redirect_after'])) {
    $back = (string) $_POST['redirect_after'];
}
header('Location: ' . $back);
exit;

/**
 * Verkleinert ein Bild in-place auf eine maximale Kantenlänge (proportional).
 * Ohne GD oder bei Fehlern bleibt die Datei unverändert.
 */
function downscaleImage(string $path, string $mime, int $maxEdge): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    $info = @getimagesize($path);
    if ($info === false) {
        return;
    }
    [$w, $h] = $info;
    if ($w <= $maxEdge && $h <= $maxEdge) {
        return;
    }
    $ratio = min($maxEdge / $w, $maxEdge / $h);
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    $src = $mime === 'image/png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
    if (!$src) {
        return;
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if ($mime === 'image/png') {
        imagepng($dst, $path, 6);
    } else {
        imagejpeg($dst, $path, 85);
    }
}
