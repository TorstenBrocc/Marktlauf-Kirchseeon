<?php
/**
 * Live-Vorschau einer Vereins-/Laufevent-Vorlage (POST + CSRF).
 * Rendert den übergebenen Markdown-Körper mit Beispieldaten (je nach Slug) und
 * liefert ihn zurück (Vorschau-Iframe). Nutzt denselben Renderer wie der Versand.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/verein_brief.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Methode nicht erlaubt.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Ungültige Anfrage.');
}

$slug = (string) ($_POST['slug'] ?? 'verein');
$kategorie = $slug === 'laufevent' ? 'laufevent' : 'verein';

$md = (string) ($_POST['koerper_md'] ?? '');
$pdo = getDbConnection();
$previewUser = getCurrentUserFromGuard();
$ctx = vereinBriefBeispielContext($pdo, (int) ($previewUser['id'] ?? 0), $kategorie);

echo sponsorBriefRenderHtml($md, $ctx);
