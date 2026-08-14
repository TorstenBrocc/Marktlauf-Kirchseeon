<?php
/**
 * Benennt eine Branche um: migriert alle getaggten Sponsoren (branche-JSON)
 * und zieht die Branche-Liste in den Einstellungen mit. Admin-only, CSRF-geschützt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Nur Admins.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'CSRF-Fehler.']);
    exit;
}

$old = trim((string) ($_POST['old'] ?? ''));
$new = trim((string) ($_POST['new'] ?? ''));

if ($old === '' || $new === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Alter und neuer Name nötig.']);
    exit;
}
if ($old === $new) {
    echo json_encode(['ok' => true, 'migrated' => 0]);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1) Sponsoren migrieren: alten Branche-Namen gegen den neuen tauschen.
    $migrated = 0;
    $sel = $pdo->prepare('SELECT id, branche FROM sponsors WHERE branche LIKE :like');
    $sel->execute(['like' => '%' . $old . '%']); // Vorfilter; exakter Vergleich unten
    $upd = $pdo->prepare('UPDATE sponsors SET branche = :b WHERE id = :id');
    foreach ($sel->fetchAll() as $row) {
        $arr = json_decode((string) $row['branche'], true);
        if (!is_array($arr)) {
            continue;
        }
        $hit = false;
        foreach ($arr as &$v) {
            if ($v === $old) { $v = $new; $hit = true; }
        }
        unset($v);
        if ($hit) {
            $arr = array_values(array_unique($arr));
            $upd->execute(['b' => json_encode($arr, JSON_UNESCAPED_UNICODE), 'id' => $row['id']]);
            $migrated++;
        }
    }

    // 2) Branche-Liste in den Einstellungen mit umbenennen (falls dort vorhanden).
    $stmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'sponsor_branchen'");
    $stmt->execute();
    $liste = json_decode((string) ($stmt->fetchColumn() ?: '[]'), true);
    if (is_array($liste)) {
        $changed = false;
        foreach ($liste as &$b) {
            if ($b === $old) { $b = $new; $changed = true; }
        }
        unset($b);
        if ($changed) {
            $listeJson = json_encode(array_values(array_unique($liste)), JSON_UNESCAPED_UNICODE);
            $pdo->prepare(
                "INSERT INTO einstellungen (`key`, `value`) VALUES ('sponsor_branchen', :v)
                 ON DUPLICATE KEY UPDATE `value` = :v2"
            )->execute(['v' => $listeJson, 'v2' => $listeJson]);
        }
    }

    echo json_encode(['ok' => true, 'migrated' => $migrated]);
} catch (PDOException $e) {
    logError('branche_rename: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
}
