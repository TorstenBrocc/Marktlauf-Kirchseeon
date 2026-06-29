<?php
/**
 * Datei-Download Handler für Helfer (GET)
 * Authentifizierung via UUID — keine Session erforderlich
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$fileId = (int) ($_GET['id'] ?? 0);
$uuid = trim($_GET['uuid'] ?? '');

if ($fileId <= 0 || $uuid === '') {
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
    header('Content-Disposition: attachment; filename="' . addslashes($file['originalname']) . '"');
    header('Content-Length: ' . $file['groesse']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log('Helfer file download error: ' . $e->getMessage(), 3, __DIR__ . '/../storage/logs/error.log');
    http_response_code(500);
    exit('Serverfehler.');
}
