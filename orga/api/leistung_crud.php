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

// Werbemittel-Detail (Option B): nur mitgeschickte Felder landen im $wm-Array; Leerstring => NULL.
// Validierung hart gegen die ENUM-/Typwerte, damit der Zustand nie kaputtgeht.
$wm = null;
$wmSet = static function (string $key, $value) use (&$wm): void {
    $wm ??= [];
    $wm[$key] = $value;
};
if (array_key_exists('wm_art', $_POST)) {
    $v = trim((string) $_POST['wm_art']);
    $wmSet('wm_art', in_array($v, ['banner', 'hussen'], true) ? $v : '');
}
if (array_key_exists('wm_anzahl', $_POST)) {
    $v = trim((string) $_POST['wm_anzahl']);
    $wmSet('wm_anzahl', ($v !== '' && ctype_digit($v) && (int) $v <= 65535) ? (string) (int) $v : '');
}
if (array_key_exists('wm_deadline', $_POST)) {
    $v = trim((string) $_POST['wm_deadline']);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    $wmSet('wm_deadline', ($d && $d->format('Y-m-d') === $v) ? $v : '');
}
if (array_key_exists('wm_status', $_POST)) {
    $v = trim((string) $_POST['wm_status']);
    $wmSet('wm_status', in_array($v, ['offen', 'erhalten', 'zurueck'], true) ? $v : '');
}

try {
    $pdo = getDbConnection();
    sponsorLeistungSet($pdo, $sponsorId, $position, $vereinbart, $freitext, $wm);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    logError('leistung_crud: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'db']);
}
