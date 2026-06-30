<?php
/**
 * Orga-Aufgaben CRUD Handler (POST)
 * Actions: create, update, delete, set_status
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? '';
$aufgabeId = (int) ($_POST['aufgabe_id'] ?? 0);

$validActions = ['create', 'update', 'delete', 'set_status'];
if (!in_array($action, $validActions, true)) {
    $_SESSION['flash_error'] = 'Ungültige Aktion.';
    header('Location: ../index.php');
    exit;
}

$redirectUrl = '../index.php';

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'create':
            $titel = trim($_POST['titel'] ?? '');
            if ($titel === '') {
                $_SESSION['flash_error'] = 'Titel ist ein Pflichtfeld.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $notiz = trim($_POST['notiz'] ?? '') ?: null;
            $verantwortlichUserId = ((int) ($_POST['verantwortlich_user_id'] ?? 0)) ?: null;
            $faelligAm = trim($_POST['faellig_am'] ?? '') ?: null;

            if ($verantwortlichUserId !== null) {
                $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role IN ("admin", "orga")');
                $userCheck->execute(['id' => $verantwortlichUserId]);
                if (!$userCheck->fetch()) {
                    $_SESSION['flash_error'] = 'Ungültiger Verantwortlicher.';
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }

            if ($faelligAm !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $faelligAm)) {
                $_SESSION['flash_error'] = 'Ungültiges Datum.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('
                INSERT INTO aufgaben (titel, notiz, verantwortlich_user_id, faellig_am)
                VALUES (:titel, :notiz, :verantwortlich_user_id, :faellig_am)
            ');
            $stmt->execute([
                'titel'                  => $titel,
                'notiz'                  => $notiz,
                'verantwortlich_user_id' => $verantwortlichUserId,
                'faellig_am'             => $faelligAm,
            ]);
            $_SESSION['flash_success'] = 'Aufgabe erstellt.';
            header('Location: ' . $redirectUrl);
            exit;

        case 'update':
            if ($aufgabeId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Aufgaben-ID.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $titel = trim($_POST['titel'] ?? '');
            if ($titel === '') {
                $_SESSION['flash_error'] = 'Titel ist ein Pflichtfeld.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $notiz = trim($_POST['notiz'] ?? '') ?: null;
            $status = $_POST['status'] ?? 'offen';
            $verantwortlichUserId = ((int) ($_POST['verantwortlich_user_id'] ?? 0)) ?: null;
            $faelligAm = trim($_POST['faellig_am'] ?? '') ?: null;

            if (!in_array($status, ['offen', 'in_arbeit', 'erledigt'], true)) {
                $status = 'offen';
            }

            if ($verantwortlichUserId !== null) {
                $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role IN ("admin", "orga")');
                $userCheck->execute(['id' => $verantwortlichUserId]);
                if (!$userCheck->fetch()) {
                    $_SESSION['flash_error'] = 'Ungültiger Verantwortlicher.';
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }

            if ($faelligAm !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $faelligAm)) {
                $_SESSION['flash_error'] = 'Ungültiges Datum.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('
                UPDATE aufgaben SET
                    titel = :titel,
                    notiz = :notiz,
                    status = :status,
                    verantwortlich_user_id = :verantwortlich_user_id,
                    faellig_am = :faellig_am
                WHERE id = :id
            ');
            $stmt->execute([
                'titel'                  => $titel,
                'notiz'                  => $notiz,
                'status'                 => $status,
                'verantwortlich_user_id' => $verantwortlichUserId,
                'faellig_am'             => $faelligAm,
                'id'                     => $aufgabeId,
            ]);
            $_SESSION['flash_success'] = 'Aufgabe aktualisiert.';
            header('Location: ' . $redirectUrl);
            exit;

        case 'set_status':
            if ($aufgabeId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Aufgaben-ID.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $status = $_POST['status'] ?? 'offen';
            if (!in_array($status, ['offen', 'in_arbeit', 'erledigt'], true)) {
                $_SESSION['flash_error'] = 'Ungültiger Status.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE aufgaben SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $status, 'id' => $aufgabeId]);
            header('Location: ' . $redirectUrl);
            exit;

        case 'delete':
            if ($aufgabeId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Aufgaben-ID.';
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM aufgaben WHERE id = :id');
            $stmt->execute(['id' => $aufgabeId]);
            $_SESSION['flash_success'] = 'Aufgabe gelöscht.';
            header('Location: ' . $redirectUrl);
            exit;
    }

} catch (PDOException $e) {
    logError('Orga-Aufgabe CRUD error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ' . $redirectUrl);
    exit;
}
