<?php
/**
 * Einsatzplan als PDF fuer die Orga (GET).
 *
 *   ohne Parameter   -> Gesamtplan (alle Tage, alle Posten, mit Namen)
 *   ?helfer_id=NN    -> der persoenliche Plan eines Helfers, so wie ihn der
 *                       Helfer selbst ueber seine Zugangsseite bekommt
 *                       (zum Ausdrucken/Weitergeben, falls jemand nicht mailt)
 *
 * Immer frisch gerendert, kein Datei-Cache — der Plan aendert sich bis zuletzt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/einsatzplan_pdf.php';

$helferId = (int) ($_GET['helfer_id'] ?? 0);

try {
    $pdo = getDbConnection();

    if ($helferId > 0) {
        $stmt = $pdo->prepare('SELECT id, vorname, nachname FROM helfer WHERE id = :id');
        $stmt->execute(['id' => $helferId]);
        $helfer = $stmt->fetch();
        if (!$helfer) {
            http_response_code(404);
            exit('Helfer nicht gefunden.');
        }

        $orgaEmail = 'info@atsv-kirchseeon-marktlauf.de';
        $orgaPhone = '';
        try {
            $config = getConfig();
            $orgaEmail = $config['orga']['email'] ?? $orgaEmail;
            $orgaPhone = $config['orga']['phone'] ?? '';
        } catch (Throwable $e) {
            // Standardadresse reicht.
        }

        $bytes = einsatzplanPdfHelfer($pdo, $helfer, $orgaEmail, $orgaPhone);
        $slug  = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $helfer['vorname'] . '-' . $helfer['nachname']), '-');
        $name  = 'Einsatzplan-' . ($slug !== '' ? $slug : 'Helfer') . '.pdf';
    } else {
        $bytes = einsatzplanPdfGesamt($pdo);
        $name  = 'Helfereinteilung-Marktlauf-' . date('Y-m-d') . '.pdf';
    }
} catch (PDOException $e) {
    logError('einsatzplan_download DB: ' . $e->getMessage());
    http_response_code(500);
    exit('Datenbankfehler.');
} catch (Throwable $e) {
    logError('einsatzplan_download render: ' . $e->getMessage());
    http_response_code(500);
    exit('PDF konnte nicht erzeugt werden.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $name . '"');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, must-revalidate');
echo $bytes;
