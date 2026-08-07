<?php
/**
 * Liefert alle Schichten + die einem Drive-Dokument zugeordneten Schichten (Strang 3).
 * Nur Orga/Admin (via _auth). Read-only, kein CSRF nötig.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

$fid = trim((string) ($_GET['fid'] ?? ''));
if ($fid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $pdo = getDbConnection();
    $schichten = $pdo
        ->query('SELECT id, titel, tag, von, bis FROM schichten ORDER BY (tag IS NULL), tag, (von IS NULL), von, titel')
        ->fetchAll(PDO::FETCH_ASSOC);

    $assigned = [];
    try {
        $st = $pdo->prepare('SELECT schicht_id FROM helfer_datei_sichtbarkeit WHERE drive_file_id = :f');
        $st->execute(['f' => $fid]);
        $assigned = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        // Migration 041 evtl. noch nicht angewandt -> keine Zuordnungen
    }

    echo json_encode(['ok' => true, 'schichten' => $schichten, 'assigned' => $assigned]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
