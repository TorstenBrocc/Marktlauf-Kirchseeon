<?php
/**
 * Sponsor-Leitfaden ausliefern. GET ?id=NN
 * Nur für eingeloggte Orga-Nutzer (Guard via _auth.php). Die Datei liegt web-gesperrt
 * unter storage/files/leitfaeden/ und wird hier kontrolliert gestreamt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/sponsor_leitfaden.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT firma, leitfaden_datei FROM sponsors WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankfehler.');
}

$datei = (string) ($row['leitfaden_datei'] ?? '');
if ($row === false || $datei === '') {
    http_response_code(404);
    exit('Kein Leitfaden hinterlegt.');
}

$path = sponsorLeitfadenPath($datei);
if (!is_file($path)) {
    http_response_code(404);
    exit('Leitfaden-Datei nicht gefunden.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

// Lesbarer Download-Name aus dem Firmennamen.
$slug = mb_strtolower(trim((string) ($row['firma'] ?? '')));
$slug = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $slug);
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');
$downloadName = 'Leitfaden-' . ($slug !== '' ? $slug : ('sponsor-' . $id)) . '.' . $ext;

header('Content-Type: ' . sponsorLeitfadenContentType($ext));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
