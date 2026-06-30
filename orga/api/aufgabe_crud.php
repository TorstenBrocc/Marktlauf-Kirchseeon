<?php
/**
 * Sponsor-Aufgaben CRUD Handler (POST)
 * Actions: create, toggle_erledigt, delete
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsoren.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsoren.php');
    exit;
}

$action = $_POST['action'] ?? '';
$aufgabeId = (int) ($_POST['aufgabe_id'] ?? 0);
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);

$validActions = ['create', 'toggle_erledigt', 'delete'];
if (!in_array($action, $validActions, true)) {
    $_SESSION['flash_error'] = 'Ungültige Aktion.';
    header('Location: ../sponsoren.php');
    exit;
}

$redirectUrl = $sponsorId > 0 ? '../sponsor_form.php?id=' . $sponsorId : '../sponsoren.php';

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'create':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $titel = trim($_POST['titel'] ?? '');
            if ($titel === '') {
                $_SESSION['flash_error'] = 'Aufgabe darf nicht leer sein.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO sponsor_aufgaben (sponsor_id, titel) VALUES (:sponsor_id, :titel)');
            $stmt->execute(['sponsor_id' => $sponsorId, 'titel' => $titel]);
            $_SESSION['flash_success'] = 'Aufgabe hinzugefügt.';
            header('Location: ' . $redirectUrl);
            exit;

        case 'toggle_erledigt':
            if ($aufgabeId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Aufgaben-ID.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsor_aufgaben SET erledigt = NOT erledigt WHERE id = :id');
            $stmt->execute(['id' => $aufgabeId]);
            header('Location: ' . $redirectUrl);
            exit;

        case 'delete':
            if ($aufgabeId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Aufgaben-ID.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM sponsor_aufgaben WHERE id = :id');
            $stmt->execute(['id' => $aufgabeId]);
            $_SESSION['flash_success'] = 'Aufgabe gelöscht.';
            header('Location: ' . $redirectUrl);
            exit;
    }

} catch (PDOException $e) {
    logError('Aufgabe CRUD error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ' . $redirectUrl);
    exit;
}
