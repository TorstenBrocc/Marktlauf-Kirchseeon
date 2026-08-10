<?php
/**
 * Newsletter-Push (POST + CSRF): legt das generierte HTML als Brevo-Kampagnen-ENTWURF an.
 *
 * Sendet nichts — Prüfen/Senden macht die Orga anschließend in Brevo. Guard + CSRF +
 * logError wie die übrigen orga/api-Endpunkte. Bei fehlender Brevo-Config oder Fehler
 * kommt fallback=true aus dem Client zurück; die Seite zeigt dann den Copy-Weg.
 * Spec: newsletter-engine-spec.md Phase 2.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/brevo_client.php';

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

$subject = trim((string) ($_POST['subject'] ?? ''));
$html    = (string) ($_POST['html'] ?? '');
$name    = trim((string) ($_POST['name'] ?? ''));

if ($subject === '' || trim($html) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Betreff und Newsletter-HTML dürfen nicht leer sein — bitte zuerst generieren.']);
    exit;
}
if ($name === '') {
    $name = 'Marktlauf Newsletter ' . date('Y-m-d');
}

try {
    $result = brevoCreateCampaignDraft($name, $subject, $html);
} catch (\Throwable $e) {
    logError('newsletter_push: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Serverfehler beim Anlegen des Entwurfs.']);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
