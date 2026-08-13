<?php
/**
 * Datei-Download Handler für Helfer (GET).
 * Authentifizierung via UUID — keine Session. Quelle: Drive-Helfer-Ordner
 * (?fid=<drive-id>), abgesichert — die Datei muss unterhalb des Helfer-Wurzelordners liegen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/google_drive.php';

$uuid = trim($_GET['uuid'] ?? '');
$fid  = trim((string) ($_GET['fid'] ?? ''));

if ($uuid === '' || $fid === '') {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

try {
    $pdo = getDbConnection();

    $helferStmt = $pdo->prepare('SELECT id FROM helfer WHERE uuid = :uuid AND status = :status');
    $helferStmt->execute(['uuid' => $uuid, 'status' => 'bestaetigt']);
    $helfer = $helferStmt->fetch();

    if (!$helfer) {
        http_response_code(403);
        exit('Zugriff verweigert.');
    }

    // Drive-Datei: nur erlaubt, wenn sie im Helfer-Zweig des Laufwerks liegt.
    if (!driveConfigured()) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }
    $helferRoot = driveRootFolderId($pdo, 'helfer');
    if (!driveIsDescendantOf($fid, $helferRoot)) {
        http_response_code(403);
        exit('Zugriff verweigert.');
    }
    $meta = driveFileMeta($fid);
    if ($meta === null) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }
    $bytes = driveDownload($fid);
    header('Content-Type: ' . ((string) ($meta['mimeType'] ?? 'application/octet-stream')));
    content_disposition((string) ($meta['name'] ?? 'download'));
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $bytes;
    exit;

} catch (Throwable $e) {
    logError('Helfer file download error: ' . $e->getMessage());
    http_response_code(500);
    exit('Serverfehler.');
}
