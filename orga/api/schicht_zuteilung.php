<?php
/**
 * Helfer einer Schicht zuteilen / Zuteilung entfernen (POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

$redirect = '../schichten.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $redirect);
    exit;
}

$action = $_POST['action'] ?? '';
$schichtId = (int) ($_POST['schicht_id'] ?? 0);
$helferId = (int) ($_POST['helfer_id'] ?? 0);

if ($schichtId <= 0 || $helferId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Eingabe.';
    header('Location: ' . $redirect);
    exit;
}

// Nach dem Speichern direkt zur bearbeiteten Schicht zurückspringen
$redirect .= '#schicht-' . $schichtId;

try {
    $pdo = getDbConnection();

    if ($action === 'add') {
        // Nur bestätigte Helfer sind zuteilbar
        $check = $pdo->prepare('SELECT status FROM helfer WHERE id = :id');
        $check->execute(['id' => $helferId]);
        $status = $check->fetchColumn();
        if ($status !== 'bestaetigt') {
            $_SESSION['flash_error'] = 'Nur bestätigte Helfer können zugeteilt werden.';
            header('Location: ' . $redirect);
            exit;
        }
        // INSERT IGNORE: doppelte Zuteilung durch UNIQUE-Key still abgefangen
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO schicht_zuteilung (schicht_id, helfer_id)
            VALUES (:schicht_id, :helfer_id)
        ');
        $stmt->execute(['schicht_id' => $schichtId, 'helfer_id' => $helferId]);
        $_SESSION['flash_success'] = 'Helfer zugeteilt.';
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare('
            DELETE FROM schicht_zuteilung
            WHERE schicht_id = :schicht_id AND helfer_id = :helfer_id
        ');
        $stmt->execute(['schicht_id' => $schichtId, 'helfer_id' => $helferId]);
        $_SESSION['flash_success'] = 'Zuteilung entfernt.';
    } else {
        $_SESSION['flash_error'] = 'Unbekannte Aktion.';
    }
} catch (PDOException $e) {
    logError('schicht_zuteilung: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ' . $redirect);
exit;
