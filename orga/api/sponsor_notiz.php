<?php
/**
 * Sponsor-Notiz speichern (POST + CSRF)
 * Inline-Schnellbearbeitung aus der Übersicht.
 * Grundlage: intern/sponsor-crm-ausbau.md §6 (Muster: helfer_notiz.php)
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

$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$notiz = trim($_POST['notizen'] ?? '');

if ($sponsorId <= 0) {
    $_SESSION['flash_error'] = 'Ungültige Eingabe.';
    header('Location: ../sponsoren.php');
    exit;
}

$notiz = mb_substr($notiz, 0, 5000);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE sponsors SET notizen = :notizen WHERE id = :id');
    $stmt->execute(['notizen' => $notiz ?: null, 'id' => $sponsorId]);
    $_SESSION['flash_success'] = 'Notiz gespeichert.';
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../sponsoren.php');
exit;
