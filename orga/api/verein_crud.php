<?php
/**
 * CRUD für Vereine & Laufevents (POST + CSRF).
 * Aktionen: inline_update (JSON), notiz, create, update, delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/verein_status.php';

$isFetch = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

/** JSON-Antwort (für inline_update). */
$json = static function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
};

/** Redirect mit Flash (für Formular-Aktionen). */
$back = static function (string $msgKey, string $msg, string $target = '../vereine.php'): void {
    $_SESSION[$msgKey] = $msg;
    header('Location: ' . $target);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    if ($isFetch) { $json(['ok' => false, 'message' => 'Ungültige Anfrage.'], 403); }
    $back('flash_error', 'Ungültige Anfrage.');
}

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$action = (string) ($_POST['action'] ?? '');
$pdo = getDbConnection();

$allowedAnrede = ['', 'Frau', 'Herr', 'Divers'];

// ---- Inline-Update (nur Status) -------------------------------------------
if ($action === 'inline_update') {
    $id = (int) ($_POST['verein_id'] ?? 0);
    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');
    if ($id <= 0) { $json(['ok' => false, 'message' => 'Ungültige ID.'], 400); }

    if ($field === 'status') {
        if (!vereinStatusValid($value)) { $json(['ok' => false, 'message' => 'Ungültiger Status.'], 400); }
        try {
            $stmt = $pdo->prepare('UPDATE vereine SET status = :s WHERE id = :id');
            $stmt->execute(['s' => $value, 'id' => $id]);
            $json(['ok' => true, 'ampel' => vereinStatusAmpel($value)]);
        } catch (PDOException $e) {
            logError('Verein inline_update: ' . $e->getMessage());
            $json(['ok' => false, 'message' => 'Datenbankfehler.'], 500);
        }
    }
    $json(['ok' => false, 'message' => 'Unbekanntes Feld.'], 400);
}

// ---- Notiz speichern -------------------------------------------------------
if ($action === 'notiz') {
    $id = (int) ($_POST['verein_id'] ?? 0);
    $notizen = trim((string) ($_POST['notizen'] ?? ''));
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE vereine SET notizen = :n WHERE id = :id');
            $stmt->execute(['n' => $notizen !== '' ? $notizen : null, 'id' => $id]);
            $back('flash_success', 'Notiz gespeichert.');
        } catch (PDOException $e) {
            logError('Verein notiz: ' . $e->getMessage());
            $back('flash_error', 'Datenbankfehler beim Speichern der Notiz.');
        }
    }
    $back('flash_error', 'Ungültige ID.');
}

// ---- Delete (nur Admin) ----------------------------------------------------
if ($action === 'delete') {
    if (!$isAdmin) { $back('flash_error', 'Löschen nur für Admins.'); }
    $id = (int) ($_POST['verein_id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->prepare('DELETE FROM vereine WHERE id = :id')->execute(['id' => $id]);
            $back('flash_success', 'Eintrag gelöscht.');
        } catch (PDOException $e) {
            logError('Verein delete: ' . $e->getMessage());
            $back('flash_error', 'Datenbankfehler beim Löschen.');
        }
    }
    $back('flash_error', 'Ungültige ID.');
}

// ---- Create / Update -------------------------------------------------------
if ($action === 'create' || $action === 'update') {
    $kategorie = (string) ($_POST['kategorie'] ?? 'verein');
    if (!in_array($kategorie, ['verein', 'laufevent'], true)) { $kategorie = 'verein'; }

    $status = (string) ($_POST['status'] ?? 'neu');
    if (!vereinStatusValid($status)) { $status = 'neu'; }

    $anrede = (string) ($_POST['anrede'] ?? '');
    if (!in_array($anrede, $allowedAnrede, true)) { $anrede = ''; }

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        $target = $action === 'update' ? '../verein_form.php?id=' . (int) ($_POST['verein_id'] ?? 0) : '../verein_form.php';
        $back('flash_error', 'Name ist ein Pflichtfeld.', $target);
    }

    $fields = [
        'kategorie'    => $kategorie,
        'name'         => mb_substr($name, 0, 255),
        'veranstalter' => trim((string) ($_POST['veranstalter'] ?? '')) ?: null,
        'ort'          => trim((string) ($_POST['ort'] ?? '')) ?: null,
        'entfernung'   => trim((string) ($_POST['entfernung'] ?? '')) ?: null,
        'relevanz'     => trim((string) ($_POST['relevanz'] ?? '')) ?: null,
        'termin'       => trim((string) ($_POST['termin'] ?? '')) ?: null,
        'anrede'       => $anrede,
        'vorname'      => trim((string) ($_POST['vorname'] ?? '')) ?: null,
        'nachname'     => trim((string) ($_POST['nachname'] ?? '')) ?: null,
        'funktion'     => trim((string) ($_POST['funktion'] ?? '')) ?: null,
        'email'        => trim((string) ($_POST['email'] ?? '')) ?: null,
        'telefon'      => trim((string) ($_POST['telefon'] ?? '')) ?: null,
        'anschrift'    => trim((string) ($_POST['anschrift'] ?? '')) ?: null,
        'website'      => trim((string) ($_POST['website'] ?? '')) ?: null,
        'social'       => trim((string) ($_POST['social'] ?? '')) ?: null,
        'quelle'       => trim((string) ($_POST['quelle'] ?? '')) ?: null,
        'hinweis'      => trim((string) ($_POST['hinweis'] ?? '')) ?: null,
        'notizen'      => trim((string) ($_POST['notizen'] ?? '')) ?: null,
        'status'       => $status,
    ];

    try {
        if ($action === 'create') {
            $cols = array_keys($fields);
            $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
            $sql = 'INSERT INTO vereine (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $pdo->prepare($sql)->execute($fields);
            $back('flash_success', 'Eintrag angelegt.');
        } else {
            $id = (int) ($_POST['verein_id'] ?? 0);
            if ($id <= 0) { $back('flash_error', 'Ungültige ID.'); }
            $set = implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($fields)));
            $fields['id'] = $id;
            $pdo->prepare("UPDATE vereine SET $set WHERE id = :id")->execute($fields);
            $back('flash_success', 'Änderungen gespeichert.');
        }
    } catch (PDOException $e) {
        logError('Verein ' . $action . ': ' . $e->getMessage());
        $back('flash_error', 'Datenbankfehler beim Speichern.');
    }
}

header('Location: ../vereine.php');
exit;
