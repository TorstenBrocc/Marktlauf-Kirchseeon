<?php
/**
 * Helfer-Briefings + Gruppen-Links verwalten (POST + CSRF, Redirect zurück).
 * Aktionen: create | toggle | delete | links
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

$back = '../helfer_verzeichnis.php';

function briefingRedirect(string $back, bool $ok, string $msg): void {
    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: ' . $back);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    briefingRedirect($back, false, 'Ungültige Anfrage.');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    briefingRedirect($back, false, 'Ungültige Anfrage (CSRF).');
}

$user   = getCurrentUserFromGuard();
$action = $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();

    if ($action === 'create') {
        $text = trim($_POST['text'] ?? '');
        $prio = $_POST['prioritaet'] ?? 'normal';
        if (!in_array($prio, ['normal', 'wichtig', 'notfall'], true)) {
            $prio = 'normal';
        }
        if ($text === '') {
            briefingRedirect($back, false, 'Bitte einen Text eingeben.');
        }
        $stmt = $pdo->prepare('INSERT INTO briefings (text, prioritaet, erstellt_von) VALUES (:t, :p, :u)');
        $stmt->execute(['t' => mb_substr($text, 0, 5000), 'p' => $prio, 'u' => $user['id']]);
        briefingRedirect($back, true, 'Briefing veröffentlicht.');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE briefings SET sichtbar = 1 - sichtbar WHERE id = :id')->execute(['id' => $id]);
        briefingRedirect($back, true, 'Sichtbarkeit geändert.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM briefings WHERE id = :id')->execute(['id' => $id]);
        briefingRedirect($back, true, 'Briefing gelöscht.');
    }

    if ($action === 'links') {
        $ins = $pdo->prepare(
            'INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2'
        );
        foreach (['telegram_gruppe_url' => 'telegram_url', 'whatsapp_gruppe_url' => 'whatsapp_url'] as $key => $field) {
            $val = trim($_POST[$field] ?? '');
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                briefingRedirect($back, false, 'Bitte gültige Gruppen-Links (URLs) eingeben.');
            }
            $ins->execute(['k' => $key, 'v' => $val !== '' ? $val : null, 'v2' => $val !== '' ? $val : null]);
        }
        briefingRedirect($back, true, 'Gruppen-Links gespeichert.');
    }

    briefingRedirect($back, false, 'Unbekannte Aktion.');
} catch (PDOException $e) {
    logError('briefing_crud error: ' . $e->getMessage());
    briefingRedirect($back, false, 'Datenbankfehler.');
}
