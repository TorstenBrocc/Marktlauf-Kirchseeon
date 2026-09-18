<?php
/**
 * Reihenfolge der Schichten eines Tages speichern (POST, JSON-Antwort).
 * Gegenstueck zum Karten-Drag im Board (orga/einsatzplan.php).
 *
 * Erwartet die vollstaendige neue Reihenfolge EINES Tages als order[] (Schicht-IDs
 * in Anzeigereihenfolge) und schreibt kompakte Raenge 0..n in `schichten.sortierung`
 * (Migration 077). Vollstaendig statt "verschiebe X hinter Y": damit ist der
 * gespeicherte Zustand immer genau das, was der Nutzer sieht — auch wenn zwei
 * Leute gleichzeitig ziehen, gewinnt der letzte Zug vollstaendig statt teilweise.
 *
 * Geprueft wird, dass alle uebergebenen IDs wirklich zu diesem Tag gehoeren —
 * sonst koennte ein manipulierter Aufruf Schichten fremder Tage umnummerieren.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function sortFail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    sortFail('Ungültige Anfrage.');
}

$tagRaw = trim((string) ($_POST['tag'] ?? ''));   // '' = Schichten ohne festen Termin
$order  = $_POST['order'] ?? [];

if (!is_array($order) || $order === []) {
    sortFail('Keine Reihenfolge übergeben.');
}
if ($tagRaw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tagRaw)) {
    sortFail('Ungültiger Tag.');
}
$tag = $tagRaw !== '' ? $tagRaw : null;

$ids = [];
foreach ($order as $v) {
    $id = (int) $v;
    if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }
}
if ($ids === []) {
    sortFail('Keine gültigen Schichten übergeben.');
}

try {
    $pdo = getDbConnection();

    // Deploy und Migration sind entkoppelt: steht der Code schon auf dem Server,
    // die Migration 095 aber noch nicht, gaebe der UPDATE unten einen nackten
    // SQL-Fehler. Lieber im Klartext sagen, was fehlt.
    if ($pdo->query("SHOW COLUMNS FROM schichten LIKE 'sortierung'")->fetch() === false) {
        sortFail('Die Reihenfolge lässt sich noch nicht speichern: Migration 095 ist auf diesem Stand noch nicht angewandt.');
    }

    // Alle Schichten dieses Tages — die uebergebene Liste muss deckungsgleich sein.
    $tagStmt = $tag === null
        ? $pdo->query('SELECT id FROM schichten WHERE tag IS NULL')
        : (static function (PDO $pdo, string $tag) {
            $s = $pdo->prepare('SELECT id FROM schichten WHERE tag = :tag');
            $s->execute(['tag' => $tag]);
            return $s;
        })($pdo, $tag);

    $tagIds = array_map('intval', $tagStmt->fetchAll(PDO::FETCH_COLUMN));

    $fremd = array_diff($ids, $tagIds);
    if ($fremd !== []) {
        sortFail('Reihenfolge enthält Schichten eines anderen Tages.');
    }

    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE schichten SET sortierung = :rang WHERE id = :id');
    foreach ($ids as $rang => $id) {
        $upd->execute(['rang' => $rang, 'id' => $id]);
    }
    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Reihenfolge gespeichert.', 'anzahl' => count($ids)]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('schicht_sortierung: ' . $e->getMessage());
    sortFail('Datenbankfehler.', 500);
}
