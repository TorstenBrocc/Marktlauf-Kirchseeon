<?php
/**
 * Datei-Upload Handler (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../_dateien_kategorien.php';

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
$bereich = $_POST['bereich'] ?? '';

if (!in_array($bereich, ['orga', 'helfer'], true)) {
    $_SESSION['flash_error'] = 'Ungültiger Bereich.';
    header('Location: ../dateien.php');
    exit;
}

$kategorie = dateiKategorieNormalisieren($_POST['kategorie'] ?? null);

if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Die Datei überschreitet die maximale Upload-Größe.',
        UPLOAD_ERR_FORM_SIZE  => 'Die Datei überschreitet die maximale Formulargröße.',
        UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei ausgewählt.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt.',
        UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht gespeichert werden.',
        UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Erweiterung gestoppt.',
    ];
    $code = $_FILES['datei']['error'] ?? UPLOAD_ERR_NO_FILE;
    $_SESSION['flash_error'] = $errorMessages[$code] ?? 'Upload-Fehler.';
    header('Location: ../dateien.php?tab=' . $bereich);
    exit;
}

$file = $_FILES['datei'];
$originalName = basename($file['name']);
$tmpPath = $file['tmp_name'];
$size = $file['size'];

$maxSize = 10 * 1024 * 1024;
if ($size > $maxSize) {
    $_SESSION['flash_error'] = 'Die Datei ist zu groß (max. 10 MB).';
    header('Location: ../dateien.php?tab=' . $bereich);
    exit;
}

$allowedMimes = [
    'application/pdf'                                                         => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    'image/png'                                                               => 'png',
    'image/jpeg'                                                              => 'jpg',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($tmpPath);

if (!isset($allowedMimes[$detectedMime])) {
    $_SESSION['flash_error'] = 'Dateityp nicht erlaubt. Erlaubt: PDF, DOCX, XLSX, PNG, JPG.';
    header('Location: ../dateien.php?tab=' . $bereich);
    exit;
}

$extension = $allowedMimes[$detectedMime];

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0x0fff) | 0x4000,
    random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

$serverFilename = $uuid . '.' . $extension;
$targetDir = __DIR__ . '/../../storage/files/' . $bereich . '/';
$targetPath = $targetDir . $serverFilename;

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

if (!move_uploaded_file($tmpPath, $targetPath)) {
    $_SESSION['flash_error'] = 'Datei konnte nicht gespeichert werden.';
    header('Location: ../dateien.php?tab=' . $bereich);
    exit;
}

// Bilder auf sinnvolle Maximalkante verkleinern (spart Speicher, keine Vollgröße-Dauerablage)
if ($detectedMime === 'image/png' || $detectedMime === 'image/jpeg') {
    downscaleImage($targetPath, $detectedMime, 2000);
    $size = @filesize($targetPath) ?: $size;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO dateien (bereich, kategorie, dateiname, originalname, mimetype, groesse, hochgeladen_von)
        VALUES (:bereich, :kategorie, :dateiname, :originalname, :mimetype, :groesse, :hochgeladen_von)
    ');
    $stmt->execute([
        'bereich'         => $bereich,
        'kategorie'       => $kategorie,
        'dateiname'       => $serverFilename,
        'originalname'    => $originalName,
        'mimetype'        => $detectedMime,
        'groesse'         => $size,
        'hochgeladen_von' => $user['id'],
    ]);

    $_SESSION['flash_success'] = 'Datei hochgeladen: ' . htmlspecialchars($originalName);
} catch (PDOException $e) {
    @unlink($targetPath);
    logError('File upload DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Speichern.';
}

header('Location: ../dateien.php?tab=' . $bereich);
exit;

/**
 * Verkleinert ein Bild in-place auf eine maximale Kantenlänge (proportional).
 * Ohne GD oder bei Fehlern bleibt die Datei unverändert.
 */
function downscaleImage(string $path, string $mime, int $maxEdge): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return; // GD nicht verfügbar -> Original behalten
    }
    $info = @getimagesize($path);
    if ($info === false) {
        return;
    }
    [$w, $h] = $info;
    if ($w <= $maxEdge && $h <= $maxEdge) {
        return; // schon klein genug
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
    // imagedestroy() entfällt: seit PHP 8.0 wirkungslos, ab 8.5 deprecated (GC übernimmt)
}
