<?php
/**
 * Alle offenen Helfer-Anmeldungen ('neu') auf einmal bestaetigen und jedem den
 * persoenlichen Zugangslink schicken (POST).
 *
 * Hintergrund: Bis zur Umstellung legte das Anmeldeformular Helfer mit Status
 * 'neu' an — den Zugangslink verschickte erst ein Klick in der Helferliste.
 * Wer nie bestaetigt wurde, kam damit nie auf seine Seite (helfer/zugang.php
 * verlangt 'bestaetigt') und hat seinen Einsatzplan nie digital gesehen.
 * Neue Anmeldungen sind ab sofort direkt bestaetigt; dieser Knopf holt den
 * Altbestand nach.
 *
 * Bewusst als ausdrueckliche Aktion mit Rueckfrage in der Oberflaeche: hier
 * gehen echte Mails an echte Leute raus. Fehlschlaege beim Versand kippen die
 * Bestaetigung NICHT — der Status ist die Wahrheit, die Mail ist die Zustellung;
 * wie viele Mails nicht rausgingen, sagt die Rueckmeldung.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';

$redirect = '../helfer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $pdo = getDbConnection();
    $offene = $pdo->query("SELECT id, uuid, vorname, nachname, email FROM helfer WHERE status = 'neu'")->fetchAll();
} catch (PDOException $e) {
    logError('helfer_bestaetigen_alle DB: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ' . $redirect);
    exit;
}

if ($offene === []) {
    $_SESSION['flash_success'] = 'Es gibt keine offenen Anmeldungen — alle Helfer sind bestätigt.';
    header('Location: ' . $redirect);
    exit;
}

$appUrl = 'https://atsv-kirchseeon-marktlauf.de';
try {
    $config = getConfig();
    $appUrl = rtrim($config['app']['url'] ?? $appUrl, '/');
} catch (Throwable $e) {
    // Standard-URL reicht.
}

$bestaetigt = 0;
$mailsOk = 0;
$mailsFehler = 0;

foreach ($offene as $h) {
    $id = (int) $h['id'];
    $uuid = (string) ($h['uuid'] ?? '');

    try {
        if ($uuid === '') {
            $uuid = uuid();
            $stmt = $pdo->prepare("UPDATE helfer SET uuid = :uuid, status = 'bestaetigt' WHERE id = :id");
            $stmt->execute(['uuid' => $uuid, 'id' => $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE helfer SET status = 'bestaetigt' WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }
        $bestaetigt++;
    } catch (PDOException $e) {
        logError('helfer_bestaetigen_alle UPDATE id=' . $id . ': ' . $e->getMessage());
        continue;
    }

    try {
        sendHelferBestaetigung(
            (string) $h['email'],
            trim($h['vorname'] . ' ' . $h['nachname']),
            $appUrl . '/helfer/zugang.php?uuid=' . urlencode($uuid)
        );
        $mailsOk++;
    } catch (Throwable $e) {
        $mailsFehler++;
        logError('helfer_bestaetigen_alle Mail id=' . $id . ': ' . $e->getMessage());
    }
}

$_SESSION['flash_success'] = $bestaetigt . ' Anmeldung(en) bestätigt · ' . $mailsOk . ' Zugangsmail(s) versendet'
    . ($mailsFehler > 0 ? ' · ' . $mailsFehler . ' Mail(s) fehlgeschlagen (siehe Log)' : '');

header('Location: ' . $redirect);
exit;
