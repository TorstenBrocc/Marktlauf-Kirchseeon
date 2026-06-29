<?php
/**
 * Datei-Download Handler (GET) — nur für eingeloggte Orga/Admin
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';

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

    $filePath = __DIR__ . '/../../storage/files/' . $file['bereich'] . '/' . $file['dateiname'];

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
    error_log('File download error: ' . $e->getMessage(), 3, __DIR__ . '/../../storage/logs/error.log');
    http_response_code(500);
    exit('Serverfehler.');
}
