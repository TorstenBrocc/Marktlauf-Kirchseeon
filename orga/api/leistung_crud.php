<?php
/**
 * Leistungs-Matrix — Zelle speichern (POST + CSRF, AJAX/JSON).
 * Setzt je Sponsor+Position den Haken (vereinbart) und/oder den Freitext/Gutscheincode.
 * Muster: die Inline-Selects aus sponsor_crud.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_leistungen.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$position  = trim((string) ($_POST['position'] ?? ''));
$valid     = array_merge(sponsorLeistungKeys(), ['_notiz']); // _notiz = freies Notizfeld je Sponsor
if ($sponsorId <= 0 || !in_array($position, $valid, true)) {
    echo json_encode(['ok' => false, 'error' => 'input']);
    exit;
}

// Getrennt setzbar: nur der jeweils mitgeschickte Wert wird geändert.
$vereinbart = array_key_exists('vereinbart', $_POST) ? (($_POST['vereinbart'] ?? '') === '1') : null;
$freitext   = array_key_exists('freitext', $_POST) ? (string) $_POST['freitext'] : null;

try {
    $pdo = getDbConnection();
    sponsorLeistungSet($pdo, $sponsorId, $position, $vereinbart, $freitext);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    logError('leistung_crud: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'db']);
}
