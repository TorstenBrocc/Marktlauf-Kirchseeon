<?php
/**
 * Sponsoring-Rechnungen — Aktionen (POST + CSRF).
 *   action=generate       : aus ausgewählten Sponsoren Rechnungsentwürfe erzeugen
 *                           + Anstoß-Mail an den Kassier (Nummernvergabe im Dashboard).
 *   action=assign_number  : fortlaufende Nummer einer Rechnung vergeben.
 * Muster: sponsor_notiz.php / sponsor_versand.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/rechnung.php';
require_once __DIR__ . '/../../src/rechnung_repo.php';
require_once __DIR__ . '/../../src/channels/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../rechnungen.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../rechnungen.php');
    exit;
}

$user   = getCurrentUserFromGuard();
$userId = (int) ($user['id'] ?? 0) ?: null;
$action = $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../rechnungen.php');
    exit;
}

if ($action === 'assign_number') {
    $id     = (int) ($_POST['id'] ?? 0);
    $nummer = trim($_POST['rechnungsnummer'] ?? '');
    try {
        rechnungNummerVergeben($pdo, $id, $nummer, $userId);
        $_SESSION['flash_success'] = 'Rechnungsnummer ' . $nummer . ' vergeben.';
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (PDOException $e) {
        logError('Rechnung Nummer vergeben: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Datenbankfehler bei der Nummernvergabe.';
    }
    header('Location: ../rechnungen.php');
    exit;
}

if ($action === 'generate') {
    $ids = array_values(array_unique(array_map('intval', (array) ($_POST['sponsor_ids'] ?? []))));
    $ids = array_filter($ids, static fn ($v) => $v > 0);

    if ($ids === []) {
        $_SESSION['flash_error'] = 'Keine Sponsoren ausgewählt.';
        header('Location: ../sponsoren.php');
        exit;
    }

    // Firmennamen für verständliche Meldungen
    $namen = [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT id, firma FROM sponsors WHERE id IN ($in)");
    $q->execute(array_values($ids));
    foreach ($q->fetchAll() as $row) {
        $namen[(int) $row['id']] = $row['firma'];
    }

    $erstellt   = []; // [['id'=>.., 'snapshot'=>.., 'firma'=>..], ...]
    $uebersprungen = []; // ['Firma – Grund', ...]

    foreach ($ids as $sid) {
        $firma = $namen[$sid] ?? ('Sponsor #' . $sid);
        try {
            $res = rechnungEntwurfErstellen($pdo, $sid, $userId);
            $erstellt[] = ['id' => $res['id'], 'snapshot' => $res['snapshot'], 'firma' => $firma];
        } catch (InvalidArgumentException $e) {
            $uebersprungen[] = $firma . ' – fehlt: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            $uebersprungen[] = $firma . ' – ' . $e->getMessage();
        } catch (PDOException $e) {
            logError('Rechnung Entwurf (' . $sid . '): ' . $e->getMessage());
            $uebersprungen[] = $firma . ' – Datenbankfehler';
        }
    }

    // Anstoß-Mail an den Kassier (best effort; Entwürfe bleiben auch ohne Mail bestehen)
    $mailHinweis = '';
    if ($erstellt !== []) {
        try {
            $mailHinweis = rechnungKassierMailSenden($erstellt);
        } catch (Throwable $e) {
            logError('Kassier-Mail: ' . $e->getMessage());
            $mailHinweis = ' Die Benachrichtigung an den Kassier konnte nicht gesendet werden (im Dashboard sichtbar).';
        }
    }

    $msg = count($erstellt) . ' Rechnungsentwurf/-entwürfe erstellt.' . $mailHinweis;
    if ($uebersprungen !== []) {
        $_SESSION['flash_error'] = 'Übersprungen: ' . implode(' · ', $uebersprungen);
    }
    $_SESSION['flash_success'] = $msg;
    header('Location: ../rechnungen.php');
    exit;
}

$_SESSION['flash_error'] = 'Unbekannte Aktion.';
header('Location: ../rechnungen.php');
exit;

/**
 * Sendet eine formale Benachrichtigung an den Kassier: es steht eine Abrechnung zur
 * Nummernvergabe bereit. Kein Anhang — die Nummer wird im Dashboard vergeben.
 * Rückgabe: kurzer Zusatzhinweis für die Flash-Meldung.
 */
function rechnungKassierMailSenden(array $erstellt): string
{
    $s   = rechnungStammdaten();
    $cfg = getConfig();
    $to  = $s['kassier_email'];
    $dashboardUrl = rtrim($cfg['app']['url'] ?? '', '/') . '/orga/rechnungen.php';
    $anzahl = count($erstellt);

    $subject = $anzahl === 1
        ? 'Sponsoring-Abrechnung: Rechnungsnummer vergeben'
        : 'Sponsoring-Abrechnungen: Rechnungsnummern vergeben';

    $zeilen = [];
    $zeilen[] = 'Hallo,';
    $zeilen[] = '';
    $zeilen[] = $anzahl === 1
        ? 'es steht eine neue Sponsoring-Abrechnung zur Nummernvergabe bereit:'
        : 'es stehen ' . $anzahl . ' neue Sponsoring-Abrechnungen zur Nummernvergabe bereit:';
    $zeilen[] = '';
    foreach ($erstellt as $e) {
        $zeilen[] = '  - ' . $e['firma'];
    }
    $zeilen[] = '';
    $zeilen[] = 'Bitte im Orga-Dashboard die jeweils aktuelle Rechnungsnummer vergeben:';
    $zeilen[] = $dashboardUrl;
    $zeilen[] = '';
    $zeilen[] = 'Viele Grüße';
    $zeilen[] = $s['verein'] . ' – ' . $s['abteilung'];
    $textBody = implode("\n", $zeilen);

    $ok = sendMail($to, $subject, $textBody, '', []);

    return $ok
        ? ' Der Kassier wurde per Mail informiert.'
        : ' Die Benachrichtigung an den Kassier konnte nicht gesendet werden (im Dashboard sichtbar).';
}
