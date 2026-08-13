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
$slug = (string) ($_POST['slug'] ?? '');
// Optionales sponsor_id: die Bestätigungs-Seite stellt sponsor-bezogen zusammen und will die
// Vorschau mit den ECHTEN Daten des gewählten Sponsors sehen (Anrede, Firma, Paket, Startplätze,
// Gutscheincode) statt mit „Musterfrau". Ohne den Parameter bleibt alles wie bisher.
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
if ($slug === 'rechnung') {
    $ctx = rechnungMailBeispielContext();
} elseif ($sponsorId > 0) {
    $ctx = sponsorBriefKontextFuerSponsor($pdo, $sponsorId, (int) ($previewUser['id'] ?? 0))
        ?? sponsorBriefBeispielContext($pdo, (int) ($previewUser['id'] ?? 0));
} else {
    $ctx = sponsorBriefBeispielContext($pdo, (int) ($previewUser['id'] ?? 0));
}

echo sponsorBriefRenderHtml($md, $ctx);
