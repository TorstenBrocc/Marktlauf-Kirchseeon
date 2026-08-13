<?php
/**
 * Schicht anlegen / bearbeiten / löschen (POST)
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

$action = $_POST['action'] ?? '';

// Eingaben normalisieren (leere Strings -> NULL)
$titel = trim($_POST['titel'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '');
$ort = trim($_POST['ort'] ?? '');
$tag = trim($_POST['tag'] ?? '');
$von = trim($_POST['von'] ?? '');
$bis = trim($_POST['bis'] ?? '');
$bedarf = (int) ($_POST['bedarf'] ?? 1);
if ($bedarf < 1) {
    $bedarf = 1;
}
$zeitfenster = trim($_POST['zeitfenster'] ?? '');
// Checkbox: gesetzt => in Anmeldung anbieten, sonst nur intern.
$inAnmeldung = isset($_POST['in_anmeldung']) ? 1 : 0;

$params = [
    'titel' => $titel,
    'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
    'ort' => $ort !== '' ? $ort : null,
    'tag' => $tag !== '' ? $tag : null,
    'von' => $von !== '' ? $von : null,
    'bis' => $bis !== '' ? $bis : null,
    'bedarf' => $bedarf,
    'zeitfenster' => $zeitfenster !== '' ? $zeitfenster : null,
    'in_anmeldung' => $inAnmeldung,
];

try {
    $pdo = getDbConnection();

    if ($action === 'create') {
        if ($titel === '') {
            $_SESSION['flash_error'] = 'Titel darf nicht leer sein.';
            header('Location: ../schichten.php');
            exit;
        }
        $stmt = $pdo->prepare('
            INSERT INTO schichten (titel, beschreibung, ort, tag, von, bis, bedarf, zeitfenster, in_anmeldung)
            VALUES (:titel, :beschreibung, :ort, :tag, :von, :bis, :bedarf, :zeitfenster, :in_anmeldung)
        ');
        $stmt->execute($params);
        $_SESSION['flash_success'] = 'Schicht angelegt.';
    } elseif ($action === 'update') {
        $id = (int) ($_POST['schicht_id'] ?? 0);
        if ($id <= 0 || $titel === '') {
            $_SESSION['flash_error'] = 'Ungültige Eingabe.';
            header('Location: ../schichten.php');
            exit;
        }
        $params['id'] = $id;
        $stmt = $pdo->prepare('
            UPDATE schichten
            SET titel = :titel, beschreibung = :beschreibung, ort = :ort,
                tag = :tag, von = :von, bis = :bis, bedarf = :bedarf,
                zeitfenster = :zeitfenster, in_anmeldung = :in_anmeldung
            WHERE id = :id
        ');
        $stmt->execute($params);
        $_SESSION['flash_success'] = 'Schicht aktualisiert.';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['schicht_id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Ungültige Eingabe.';
            header('Location: ../schichten.php');
            exit;
        }
        // Zuteilungen werden per ON DELETE CASCADE mitgelöscht
        $stmt = $pdo->prepare('DELETE FROM schichten WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $_SESSION['flash_success'] = 'Schicht gelöscht.';
    } else {
        $_SESSION['flash_error'] = 'Unbekannte Aktion.';
    }
} catch (PDOException $e) {
    logError('schicht_crud: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../schichten.php');
exit;
