<?php
/**
 * Persoenlicher Einsatzplan als PDF (oeffentlich, UUID-validiert).
 * Gegenstueck zum Button auf helfer/zugang.php — gleiche Auth-Regel wie dort:
 * die UUID ist der Token, eine Session gibt es nicht.
 *
 * Das PDF wird bei jedem Aufruf frisch gerendert (kein Datei-Cache): der Plan
 * aendert sich bis kurz vor dem Lauf, ein gecachtes PDF waere sofort falsch.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/einsatzplan_pdf.php';

$uuid = trim($_GET['uuid'] ?? '');

if ($uuid === '' || strlen($uuid) > 64) {
    http_response_code(400);
    exit('Ungültiger Zugang.');
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        SELECT id, vorname, nachname
        FROM helfer
        WHERE uuid = :uuid AND status = :status
    ');
    $stmt->execute(['uuid' => $uuid, 'status' => 'bestaetigt']);
    $helfer = $stmt->fetch();
} catch (PDOException $e) {
    logError('plan_pdf DB: ' . $e->getMessage());
    http_response_code(500);
    exit('Datenbankfehler.');
}

if (!$helfer) {
    http_response_code(404);
    exit('Zugang nicht verfügbar.');
}

$orgaEmail = 'info@atsv-kirchseeon-marktlauf.de';
$orgaPhone = '';
try {
    $config = getConfig();
    $orgaEmail = $config['orga']['email'] ?? $orgaEmail;
    $orgaPhone = $config['orga']['phone'] ?? '';
} catch (Throwable $e) {
    // Ohne Config bleibt es bei der Standard-Adresse.
}

try {
    $bytes = einsatzplanPdfHelfer($pdo, $helfer, $orgaEmail, $orgaPhone);
} catch (Throwable $e) {
    logError('plan_pdf render: ' . $e->getMessage());
    http_response_code(500);
    exit('PDF konnte nicht erzeugt werden.');
}

// Dateiname mit Namen des Helfers — wer mehrere Pläne ablegt (Familien), soll sie
// auseinanderhalten können. Nur unkritische Zeichen.
$slug = preg_replace('/[^A-Za-z0-9]+/', '-', $helfer['vorname'] . '-' . $helfer['nachname']);
$slug = trim((string) $slug, '-');
$name = 'Einsatzplan-' . ($slug !== '' ? $slug : 'Helfer') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, must-revalidate');
echo $bytes;
