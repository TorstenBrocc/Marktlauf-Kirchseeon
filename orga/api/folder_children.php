<?php
/**
 * Liefert die Kinder (Unterordner UND Dateien) eines Ordners im geteilten Laufwerk
 * (JSON) — für das inline-Aufklappen des Datei-Baums. Nur eingeloggte Orga/Admin.
 * Sicherheit: nur Ordner innerhalb des geteilten Laufwerks.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

$parent = trim((string) ($_GET['parent'] ?? ''));

if ($parent === '' || !driveConfigured()) {
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

$meta = driveFileMeta($parent);
if ($meta === null
    || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()
    || (string) ($meta['mimeType'] ?? '') !== DRIVE_FOLDER_MIME) {
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

try {
    $items = [];
    foreach (driveListChildren($parent) as $c) {
        $items[] = [
            'id'         => $c['id'],
            'name'       => $c['name'],
            'isFolder'   => $c['isFolder'],
            'size'       => $c['size'],
            'mimeType'   => $c['mimeType'],
            'restricted' => false,
        ];
    }
    // Strang 3: markieren, welche Kinder eine Helfer-Schicht-Zuordnung haben (👁 im Baum).
    if ($items !== []) {
        try {
            $pdo = getDbConnection();
            $ids = array_column($items, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $st  = $pdo->prepare("SELECT DISTINCT drive_file_id FROM helfer_datei_sichtbarkeit WHERE drive_file_id IN ($ph)");
            $st->execute($ids);
            $restricted = array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN), true);
            foreach ($items as &$it) {
                $it['restricted'] = isset($restricted[$it['id']]);
            }
            unset($it);
        } catch (PDOException $e) {
            // Migration 041 evtl. nicht angewandt -> ohne Markierung
        }
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logError('folder_children: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'items' => []]);
}
