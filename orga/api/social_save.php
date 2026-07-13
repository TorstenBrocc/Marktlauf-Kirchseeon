<?php
/**
 * Nachbericht speichern/aktualisieren (POST + CSRF) — nur Admin/Orga.
 * Legt einen neuen Datensatz an oder aktualisiert einen bestehenden (per id).
 * Response: {"ok":true,"id":123} oder {"error":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültige Anfrage.']);
    exit;
}

$user    = getCurrentUserFromGuard();
$id      = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : null;
$artikel = trim($_POST['article'] ?? '');
$social  = trim($_POST['social']  ?? '');
$status  = $_POST['status'] === 'approved' ? 'approved' : 'draft';
$provider = in_array($_POST['provider'] ?? '', ['gemini', 'mistral'], true) ? $_POST['provider'] : null;

if ($artikel === '' && $social === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Inhalt zum Speichern.']);
    exit;
}

try {
    $pdo = getDbConnection();

    if ($id !== null) {
        $stmt = $pdo->prepare(
            'UPDATE post_race_contents
                SET llm_text_article = :article,
                    llm_text_social  = :social,
                    status           = :status,
                    llm_provider     = :provider,
                    updated_at       = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            'article'  => $artikel ?: null,
            'social'   => $social  ?: null,
            'status'   => $status,
            'provider' => $provider,
            'id'       => $id,
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO post_race_contents
                (llm_text_article, llm_text_social, status, llm_provider, erstellt_von)
             VALUES (:article, :social, :status, :provider, :user_id)'
        );
        $stmt->execute([
            'article'  => $artikel ?: null,
            'social'   => $social  ?: null,
            'status'   => $status,
            'provider' => $provider,
            'user_id'  => $user['id'],
        ]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (PDOException $e) {
    logError('social_save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler.']);
}
