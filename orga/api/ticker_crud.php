<?php
/**
 * Live-Ticker CRUD Handler (POST)
 * Actions: create, delete, toggle
 * Schreibt nach jeder Änderung data/status.json neu.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ticker.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../ticker.php');
    exit;
}

$action = $_POST['action'] ?? '';
$user   = getCurrentUserFromGuard();

try {
    $pdo = getDbConnection();

    switch ($action) {

        case 'create':
            $nachricht = trim($_POST['nachricht'] ?? '');
            $typ       = $_POST['typ'] ?? 'info';
            if (!in_array($typ, ['info', 'warnung', 'ergebnis'], true)) {
                $typ = 'info';
            }
            if ($nachricht === '') {
                $_SESSION['flash_error'] = 'Nachricht darf nicht leer sein.';
                break;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO ticker_posts (nachricht, typ, erstellt_von) VALUES (?, ?, ?)'
            );
            $stmt->execute([$nachricht, $typ, $user['id']]);
            $_SESSION['flash_success'] = 'Ticker-Eintrag veröffentlicht.';
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM ticker_posts WHERE id = ?')->execute([$id]);
            }
            $_SESSION['flash_success'] = 'Eintrag gelöscht.';
            break;

        case 'toggle':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE ticker_posts SET aktiv = 1 - aktiv WHERE id = ?'
                )->execute([$id]);
            }
            break;

        default:
            $_SESSION['flash_error'] = 'Unbekannte Aktion.';
    }

    tickerStatusJsonSchreiben($pdo);

} catch (PDOException $e) {
    logError('ticker_crud: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
}

header('Location: ../ticker.php');
exit;

// ---------------------------------------------------------------------------

function tickerStatusJsonSchreiben(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT nachricht, typ, erstellt_am
         FROM ticker_posts
         WHERE aktiv = 1
         ORDER BY erstellt_am DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $json = json_encode([
        'aktualisiert' => date('c'),
        'eintraege'    => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $ziel = __DIR__ . '/../../data/status.json';
    @mkdir(dirname($ziel), 0755, true);

    if (file_put_contents($ziel, $json, LOCK_EX) === false) {
        logError('ticker_crud: status.json konnte nicht geschrieben werden');
    }
}
