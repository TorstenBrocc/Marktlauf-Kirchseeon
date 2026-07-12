<?php
/**
 * Live-Vorschau einer Briefvorlage (POST + CSRF).
 * Rendert den übergebenen Markdown-Körper mit Beispiel-Empfängerdaten zu HTML
 * und liefert ihn zurück (für das Vorschau-Iframe im Editor). Nutzt denselben
 * Renderer wie der echte Versand -> WYSIWYG.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/sponsor_brief.php';
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

$md = (string) ($_POST['koerper_md'] ?? '');
$pdo = getDbConnection();
$previewUser = getCurrentUserFromGuard();
$ctx = sponsorBriefBeispielContext($pdo, (int) ($previewUser['id'] ?? 0));

echo sponsorBriefRenderHtml($md, $ctx);
