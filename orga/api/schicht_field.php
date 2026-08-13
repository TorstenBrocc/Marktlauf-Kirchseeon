<?php
/**
 * Inline-Feld-Update einer Schicht (Doppelklick-Bearbeitung im Einsatzplan).
 * Aktualisiert nur die im POST übergebenen, whitelisteten Felder. Ein Aufruf
 * kann auch mehrere zusammengehörige Felder setzen (z. B. von/bis/zeitfenster).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../schichten.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../schichten.php');
    exit;
}

$id = (int) ($_POST['schicht_id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Schicht.';
    header('Location: ../schichten.php');
    exit;
}

// Erlaubte Felder + Normalisierung. Nur tatsächlich übergebene Felder werden gesetzt.
$allowed = ['titel', 'ort', 'beschreibung', 'tag', 'von', 'bis', 'zeitfenster', 'bedarf', 'in_anmeldung'];
$set = [];
$params = ['id' => $id];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $_POST)) {
        continue;
    }
    $raw = is_string($_POST[$field]) ? trim($_POST[$field]) : $_POST[$field];

    switch ($field) {
        case 'titel':
            if ($raw === '') {
                $_SESSION['flash_error'] = 'Der Schichtname darf nicht leer sein.';
                header('Location: ../schichten.php');
                exit;
            }
            $value = mb_substr((string) $raw, 0, 255);
            break;
        case 'bedarf':
            $value = max(1, (int) $raw);
            break;
        case 'in_anmeldung':
            $value = ((string) $raw === '1') ? 1 : 0;
            break;
        case 'zeitfenster':
            $value = $raw !== '' ? mb_substr((string) $raw, 0, 80) : null;
            break;
        default: // ort, beschreibung, tag, von, bis
            $value = $raw !== '' ? $raw : null;
    }

    $set[] = "`$field` = :$field";
    $params[$field] = $value;
}

if (empty($set)) {
    header('Location: ../schichten.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE schichten SET ' . implode(', ', $set) . ' WHERE id = :id');
    $stmt->execute($params);
    $_SESSION['flash_success'] = 'Gespeichert.';
} catch (PDOException $e) {
    logError('schicht_field: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../schichten.php#schicht-' . $id);
exit;
