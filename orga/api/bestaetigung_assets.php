<?php
/**
 * Liefert die anhängbaren Dateien für die Sponsoring-Bestätigung (JSON) — getrennt nach
 * Plakaten (designierter Plakate-Ordner) und Bestätigungs-Assets (eigener Ordner).
 * Grundlage für die Opt-out-Liste beim Versand. Nur eingeloggte Orga/Admin.
 *
 * Die Abwahl selbst lebt browser-seitig (localStorage) und gilt bis zum nächsten Versand;
 * dieser Endpoint liefert nur die aktuell vorhandenen Dateien, immer den Ist-Stand des Ordners.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/google_drive.php';

header('Content-Type: application/json; charset=utf-8');

if (!driveConfigured()) {
    echo json_encode(['ok' => false, 'plakat' => [], 'asset' => []]);
    exit;
}

/** @return array<int,array{id:string,name:string}> */
$listFolder = static function (?string $folderId): array {
    if ($folderId === null) {
        return [];
    }
    $items = [];
    foreach (driveListChildren($folderId) as $c) {
        if ($c['isFolder'] || $c['id'] === '') {
            continue;
        }
        $items[] = ['id' => $c['id'], 'name' => $c['name']];
    }
    return $items;
};

try {
    $pdo          = getDbConnection();
    $plakatFolder = drivePlakatFolderId($pdo, driveRennJahr($pdo));
    $assetFolder  = driveBestaetigungAssetsFolderId($pdo);
    echo json_encode([
        'ok'         => true,
        'configured' => $assetFolder !== null,
        'plakat'     => $listFolder($plakatFolder),
        'asset'      => $listFolder($assetFolder),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logError('bestaetigung_assets: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'plakat' => [], 'asset' => []]);
}
