<?php
/**
 * Listet die Bild-Dateien aus der Datei-Ablage (JSON) für den Bild-Picker
 * der Share-Grafik. Nur eingeloggte Orga/Admin (via _auth.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query(
        "SELECT id, originalname, kategorie, bereich
           FROM dateien
          WHERE mimetype IN ('image/png', 'image/jpeg')
          ORDER BY id DESC"
    );
    $images = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $images[] = [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['originalname'],
            'kat'   => (string) ($row['kategorie'] ?? ''),
            'url'   => 'api/file_download.php?id=' . (int) $row['id'] . '&inline=1',
        ];
    }
    echo json_encode(['ok' => true, 'images' => $images], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    logError('dateien_images error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
}
