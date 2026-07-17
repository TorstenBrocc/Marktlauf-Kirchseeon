<?php
/**
 * CRUD-API für Prompt-Bibliothek.
 * Actions: list, get, save, delete
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

getCurrentUserFromGuard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = json_decode($raw ?: '', true) ?? [];
$action = (string) ($body['action'] ?? '');

if (!verifyCsrfToken((string) ($body['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF-Fehler']);
    exit;
}

$pdo = getDbConnection();

try {
    switch ($action) {

        case 'list':
            $where  = '';
            $params = [];
            if (!empty($body['kategorie'])) {
                $where = ' WHERE kategorie = ?';
                $params[] = $body['kategorie'];
            }
            $stmt = $pdo->prepare(
                'SELECT id, titel, kategorie, tags, LEFT(inhalt,120) AS vorschau, geaendert_am
                 FROM prompts' . $where . ' ORDER BY geaendert_am DESC'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['tags'] = json_decode((string)$r['tags'], true) ?? [];
            }
            echo json_encode(['ok' => true, 'prompts' => $rows]);
            break;

        case 'get':
            $id   = (int) ($body['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM prompts WHERE id = ?');
            $stmt->execute([$id]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok' => false, 'error' => 'Nicht gefunden']); break; }
            $row['tags'] = json_decode((string)$row['tags'], true) ?? [];
            echo json_encode(['ok' => true, 'prompt' => $row]);
            break;

        case 'save':
            $id        = (int) ($body['id'] ?? 0);
            $titel     = trim((string) ($body['titel'] ?? ''));
            $kategorie = trim((string) ($body['kategorie'] ?? 'frei'));
            $tags      = array_values(array_filter(array_map('trim', (array) ($body['tags'] ?? []))));
            $inhalt    = (string) ($body['inhalt'] ?? '');

            if ($titel === '' || $inhalt === '') {
                echo json_encode(['ok' => false, 'error' => 'Titel und Inhalt sind Pflicht']);
                break;
            }
            $kategorienErlaubt = ['raceresult','sponsoren','social','newsletter','presse','frei'];
            if (!in_array($kategorie, $kategorienErlaubt, true)) {
                $kategorie = 'frei';
            }
            $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE prompts SET titel=?, kategorie=?, tags=?, inhalt=? WHERE id=?'
                );
                $stmt->execute([$titel, $kategorie, $tagsJson, $inhalt, $id]);
                echo json_encode(['ok' => true, 'id' => $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO prompts (titel, kategorie, tags, inhalt) VALUES (?,?,?,?)'
                );
                $stmt->execute([$titel, $kategorie, $tagsJson, $inhalt]);
                echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
            }
            break;

        case 'delete':
            $id = (int) ($body['id'] ?? 0);
            if ($id < 1) { echo json_encode(['ok' => false, 'error' => 'Ungültige ID']); break; }
            $pdo->prepare('DELETE FROM prompts WHERE id = ?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unbekannte Action']);
    }
} catch (PDOException $e) {
    logError('prompt_crud: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Datenbankfehler']);
}
