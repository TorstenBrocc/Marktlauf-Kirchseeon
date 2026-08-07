<?php
/**
 * Datei-Download Handler für Helfer (GET).
 * Authentifizierung via UUID — keine Session. Zwei Quellen:
 *  - ?fid=<drive-id>  → Datei live aus dem Drive-Helfer-Ordner (aktueller Weg),
 *    abgesichert: die Datei muss unterhalb des Helfer-Wurzelordners liegen.
 *  - ?id=<int>        → lokaler Alt-Bestand aus der dateien-Tabelle (Übergang).
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/google_drive.php';

$uuid   = trim($_GET['uuid'] ?? '');
$fid    = trim((string) ($_GET['fid'] ?? ''));
$fileId = (int) ($_GET['id'] ?? 0);

if ($uuid === '' || ($fid === '' && $fileId <= 0)) {
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

    // (a) Drive-Datei: nur erlaubt, wenn sie im Helfer-Zweig des Laufwerks liegt.
    if ($fid !== '') {
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
    }

    // (b) Lokaler Alt-Bestand aus der dateien-Tabelle.
    $stmt = $pdo->prepare('SELECT * FROM dateien WHERE id = :id AND bereich = :bereich');
    $stmt->execute(['id' => $fileId, 'bereich' => 'helfer']);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }

    $filePath = __DIR__ . '/../storage/files/helfer/' . $file['dateiname'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('Datei nicht auf Server gefunden.');
    }

    header('Content-Type: ' . $file['mimetype']);
    content_disposition($file['originalname']);
    header('Content-Length: ' . $file['groesse']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($filePath);
    exit;

} catch (Throwable $e) {
    logError('Helfer file download error: ' . $e->getMessage());
    http_response_code(500);
    exit('Serverfehler.');
}
