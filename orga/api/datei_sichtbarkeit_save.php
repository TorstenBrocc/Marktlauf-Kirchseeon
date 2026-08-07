<?php
/**
 * Speichert die Schicht-Zuordnung eines Drive-Dokuments (Strang 3, ersetzt-Semantik).
 * Nur Orga/Admin (via _auth), CSRF-geschützt. Leere Auswahl = global sichtbar.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$fid = trim((string) ($_POST['fid'] ?? ''));
$ids = $_POST['schicht_ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));

if ($fid === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Keine Datei angegeben.']);
    exit;
}

$pdo = null;
try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM helfer_datei_sichtbarkeit WHERE drive_file_id = :f');
    $del->execute(['f' => $fid]);
    if ($ids !== []) {
        $ins = $pdo->prepare('INSERT INTO helfer_datei_sichtbarkeit (drive_file_id, schicht_id) VALUES (:f, :s)');
        foreach ($ids as $s) {
            $ins->execute(['f' => $fid, 's' => $s]);
        }
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'count' => count($ids)]);
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('datei_sichtbarkeit_save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Speichern fehlgeschlagen (Migration 041 angewandt?).']);
}
