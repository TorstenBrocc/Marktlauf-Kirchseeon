<?php
/**
 * Sponsor CRUD Handler (POST)
 * Actions: create, update, delete, kein_kontakt_set, kein_kontakt_remove
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';

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
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$isAdmin = isAdminFromGuard();

$validActions = ['create', 'update', 'delete', 'kein_kontakt_set', 'kein_kontakt_remove'];
if (!in_array($action, $validActions, true)) {
    $_SESSION['flash_error'] = 'Ungültige Aktion.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'delete' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Sponsoren löschen.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'kein_kontakt_remove' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Kein-Kontakt zurücknehmen.';
    header('Location: ../sponsoren.php');
    exit;
}

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'create':
            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php');
                exit;
            }

            $stmt = $pdo->prepare('
                INSERT INTO sponsors (firma, ansprechpartner, email, paket, summe, status, kein_kontakt, notizen, wiedervorlage)
                VALUES (:firma, :ansprechpartner, :email, :paket, :summe, :status, :kein_kontakt, :notizen, :wiedervorlage)
            ');
            $stmt->execute([
                'firma'           => $firma,
                'ansprechpartner' => trim($_POST['ansprechpartner'] ?? '') ?: null,
                'email'           => trim($_POST['email'] ?? '') ?: null,
                'paket'           => $_POST['paket'] ?: null,
                'summe'           => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'          => $_POST['status'] ?? 'angefragt',
                'kein_kontakt'    => isset($_POST['kein_kontakt']) ? 1 : 0,
                'notizen'         => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'   => $_POST['wiedervorlage'] ?: null,
            ]);
            $_SESSION['flash_success'] = 'Sponsor angelegt.';
            header('Location: ../sponsoren.php');
            exit;

        case 'update':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php?id=' . $sponsorId);
                exit;
            }

            $existing = $pdo->prepare('SELECT kein_kontakt FROM sponsors WHERE id = :id');
            $existing->execute(['id' => $sponsorId]);
            $sponsor = $existing->fetch();

            if (!$sponsor) {
                $_SESSION['flash_error'] = 'Sponsor nicht gefunden.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $keinKontakt = $sponsor['kein_kontakt'];
            if (isset($_POST['kein_kontakt'])) {
                $keinKontakt = 1;
            } elseif ($isAdmin) {
                $keinKontakt = 0;
            }

            $stmt = $pdo->prepare('
                UPDATE sponsors SET
                    firma = :firma,
                    ansprechpartner = :ansprechpartner,
                    email = :email,
                    paket = :paket,
                    summe = :summe,
                    status = :status,
                    kein_kontakt = :kein_kontakt,
                    notizen = :notizen,
                    wiedervorlage = :wiedervorlage
                WHERE id = :id
            ');
            $stmt->execute([
                'firma'           => $firma,
                'ansprechpartner' => trim($_POST['ansprechpartner'] ?? '') ?: null,
                'email'           => trim($_POST['email'] ?? '') ?: null,
                'paket'           => $_POST['paket'] ?: null,
                'summe'           => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'          => $_POST['status'] ?? 'angefragt',
                'kein_kontakt'    => $keinKontakt,
                'notizen'         => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'   => $_POST['wiedervorlage'] ?: null,
                'id'              => $sponsorId,
            ]);
            $_SESSION['flash_success'] = 'Sponsor aktualisiert.';
            header('Location: ../sponsor_form.php?id=' . $sponsorId);
            exit;

        case 'delete':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM sponsors WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Sponsor gelöscht.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_set':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 1 WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt gesetzt.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_remove':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 0 WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt aufgehoben.';
            header('Location: ../sponsoren.php');
            exit;
    }

} catch (PDOException $e) {
    error_log('Sponsor CRUD error: ' . $e->getMessage(), 3, __DIR__ . '/../../storage/logs/error.log');
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../sponsoren.php');
    exit;
}
