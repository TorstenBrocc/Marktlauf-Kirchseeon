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

// Rücksprungziel als Schlüssel, nie als URL aus dem Request (sonst Open Redirect).
// `sponsor` braucht die ID, deshalb wird sie hier ausgewertet statt im create-Zweig.
$sponsorId = (int) ($_POST['kontext_id'] ?? 0);
$redirectUrl = '../index.php';
$zurueck = $_POST['zurueck'] ?? '';
if ($zurueck === 'todos') {
    $redirectUrl = '../offene_todos.php';
} elseif ($zurueck === 'sponsor' && $sponsorId > 0) {
    $redirectUrl = '../sponsor_form.php?id=' . $sponsorId;
}

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

            // Kontext ist optional: ohne ihn entsteht eine allgemeine Orga-Aufgabe wie bisher,
            // mit ihm hängt sie am Sponsor (Migration 063). Die Sponsor-ID wird geprüft —
            // sonst zeigte die ToDo-Kachel eine Aufgabe ohne Firma.
            $kontextTyp = null;
            $kontextId = null;
            if (($_POST['kontext_typ'] ?? '') === 'sponsor') {
                if ($sponsorId > 0) {
                    // Aus der Sponsor-Maske: die ID steht fest.
                    $sponsorCheck = $pdo->prepare('SELECT id FROM sponsors WHERE id = :id');
                    $sponsorCheck->execute(['id' => $sponsorId]);
                    if (!$sponsorCheck->fetch()) {
                        $_SESSION['flash_error'] = 'Sponsor nicht gefunden.';
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                } else {
                    // Aus der ToDo-Kachel: dort tippt man in eine Datalist, die nur den Namen
                    // liefert. Bewusst serverseitig aufgelöst statt per JS in ein Hidden-Feld —
                    // sonst legt ein Tippfehler stillschweigend eine Aufgabe ohne Sponsor an.
                    $firmaEingabe = trim($_POST['sponsor_firma'] ?? '');
                    $treffer = $pdo->prepare('SELECT id FROM sponsors WHERE firma = :firma');
                    $treffer->execute(['firma' => $firmaEingabe]);
                    $ids = $treffer->fetchAll(PDO::FETCH_COLUMN);

                    if (count($ids) === 0) {
                        $_SESSION['flash_error'] = 'Kein Sponsor mit dem Namen „' . $firmaEingabe . '" — bitte aus der Vorschlagsliste wählen.';
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                    if (count($ids) > 1) {
                        $_SESSION['flash_error'] = 'Der Name „' . $firmaEingabe . '" kommt mehrfach vor — bitte die Aufgabe direkt beim Sponsor anlegen.';
                        header('Location: ' . $redirectUrl);
                        exit;
                    }
                    $sponsorId = (int) $ids[0];
                }
                $kontextTyp = 'sponsor';
                $kontextId = $sponsorId;
            }

            $stmt = $pdo->prepare('
                INSERT INTO aufgaben (titel, notiz, verantwortlich_user_id, faellig_am, kontext_typ, kontext_id)
                VALUES (:titel, :notiz, :verantwortlich_user_id, :faellig_am, :kontext_typ, :kontext_id)
            ');
            $stmt->execute([
                'titel'                  => $titel,
                'notiz'                  => $notiz,
                'verantwortlich_user_id' => $verantwortlichUserId,
                'faellig_am'             => $faelligAm,
                'kontext_typ'            => $kontextTyp,
                'kontext_id'             => $kontextId,
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
