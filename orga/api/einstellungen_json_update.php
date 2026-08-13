<?php
/**
 * Speichert einen einzelnen JSON-Wert in der einstellungen-Tabelle.
 * Erlaubte Keys sind explizit whitegelistet.
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

$erlaubteKeys = ['sponsor_branchen'];
$key   = (string) ($_POST['key'] ?? '');
$value = (string) ($_POST['value'] ?? '');

if (!in_array($key, $erlaubteKeys, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültiger Key.']);
    exit;
}

// Sicherstellen dass value valides JSON ist
$decoded = json_decode($value, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültiges JSON.']);
    exit;
}
$value = json_encode($decoded, JSON_UNESCAPED_UNICODE);

try {
    $pdo = getDbConnection();

    // Branche-Umbenennungen: getaggte Sponsoren mitziehen, damit keine Tags verwaisen.
    // branche ist ein JSON-Array; wir tauschen den alten gegen den neuen Namen aus.
    $migrated = 0;
    if ($key === 'sponsor_branchen') {
        $renames = json_decode((string) ($_POST['renames'] ?? '[]'), true);
        if (is_array($renames)) {
            $sel = $pdo->prepare('SELECT id, branche FROM sponsors WHERE branche LIKE :like');
            $upd = $pdo->prepare('UPDATE sponsors SET branche = :b WHERE id = :id');
            foreach ($renames as $rn) {
                $old = trim((string) ($rn['old'] ?? ''));
                $new = trim((string) ($rn['new'] ?? ''));
                if ($old === '' || $new === '' || $old === $new) {
                    continue;
                }
                $sel->execute(['like' => '%' . $old . '%']); // Vorfilter; exakter Vergleich unten
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
                        $arr = array_values(array_unique($arr)); // falls der neue Name schon getaggt war
                        $upd->execute(['b' => json_encode($arr, JSON_UNESCAPED_UNICODE), 'id' => $row['id']]);
                        $migrated++;
                    }
                }
            }
        }
    }

    $pdo->prepare(
        'INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = :v2'
    )->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
    echo json_encode(['ok' => true, 'migrated' => $migrated]);
} catch (PDOException $e) {
    logError('einstellungen_json_update: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
}
