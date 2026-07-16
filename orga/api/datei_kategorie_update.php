<?php
/**
 * Kategorie einer Datei nachträglich ändern (Umflaggen), POST + CSRF, JSON.
 * Stufe 1 der Datei-Flag-Verwaltung — eine Kategorie pro Datei (kein Schema-Change).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../_dateien_kategorien.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Methode nicht erlaubt.']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$id  = (int) ($_POST['id'] ?? 0);
$kat = dateiKategorieNormalisieren($_POST['kategorie'] ?? null);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Datei.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->prepare('UPDATE dateien SET kategorie = :k WHERE id = :id')
        ->execute(['k' => $kat, 'id' => $id]);
    echo json_encode(['ok' => true, 'kategorie' => $kat, 'label' => dateiKategorieLabel($kat)]);
} catch (PDOException $e) {
    logError('datei_kategorie_update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
}
