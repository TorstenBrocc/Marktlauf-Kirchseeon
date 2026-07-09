<?php
/**
 * Sponsor-Merkfeld speichern (POST + CSRF)
 * Vereinsweites Referenz-Freitextfeld (Bankverbindung, Vereins-/Steuernummer)
 * auf der Sponsoren-Übersicht. Speicherung in einstellungen (Key sponsor_merkfeld).
 * Antwortet als JSON (fetch aus sponsoren.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function merkfeldJson(bool $ok, string $message = ''): void {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    merkfeldJson(false, 'Methode nicht erlaubt.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    merkfeldJson(false, 'Ungültige Anfrage.');
}

$merkfeld = trim($_POST['merkfeld'] ?? '');
$merkfeld = mb_substr($merkfeld, 0, 5000);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE `value` = :value2'
    );
    $stmt->execute([
        'key' => 'sponsor_merkfeld',
        'value' => $merkfeld !== '' ? $merkfeld : null,
        'value2' => $merkfeld !== '' ? $merkfeld : null,
    ]);
    merkfeldJson(true);
} catch (PDOException $e) {
    logError('Sponsor-Merkfeld update error: ' . $e->getMessage());
    http_response_code(500);
    merkfeldJson(false, 'Datenbankfehler.');
}
