<?php
/**
 * Datei-Download Handler (GET) — nur für eingeloggte Orga/Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/google_drive.php';
require_once __DIR__ . '/../../src/datei_audit.php';

$fileId = (int) ($_GET['id'] ?? 0);

if ($fileId <= 0) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM dateien WHERE id = :id');
    $stmt->execute(['id' => $fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }

    // Drive-backed file: stream bytes from the shared drive.
    if (!empty($file['drive_file_id'])) {
        try {
            $bytes = driveDownload((string) $file['drive_file_id']);
        } catch (RuntimeException $e) {
            http_response_code(502);
            exit('Datei konnte nicht von Google Drive geladen werden.');
        }
        $inline = isset($_GET['inline']) && $_GET['inline'] === '1'
            && str_starts_with((string) $file['mimetype'], 'image/');
        header('Content-Type: ' . $file['mimetype']);
        content_disposition($file['originalname'], $inline ? 'inline' : 'attachment');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        $u = getCurrentUserFromGuard();
        dateiAudit($pdo, 'download', [
            'datei_id'      => (int) $file['id'],
            'drive_file_id' => (string) $file['drive_file_id'],
            'originalname'  => $file['originalname'],
            'kategorie'     => $file['kategorie'] ?? null,
            'benutzer_id'   => $u['id'] ?? null,
        ]);
        echo $bytes;
        exit;
    }

    $filePath = __DIR__ . '/../../storage/files/' . $file['bereich'] . '/' . $file['dateiname'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('Datei nicht auf Server gefunden.');
    }

    // inline=1: Bild direkt anzeigen (z. B. als <img>-Quelle für die Share-Grafik),
    // sonst weiterhin als Download-Anhang ausliefern.
    $inline = isset($_GET['inline']) && $_GET['inline'] === '1'
        && str_starts_with((string) $file['mimetype'], 'image/');
    $disposition = $inline ? 'inline' : 'attachment';

    header('Content-Type: ' . $file['mimetype']);
    content_disposition($file['originalname'], $disposition);
    header('Content-Length: ' . $file['groesse']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($filePath);
    exit;

} catch (PDOException $e) {
    logError('File download error: ' . $e->getMessage());
    http_response_code(500);
    exit('Serverfehler.');
}
