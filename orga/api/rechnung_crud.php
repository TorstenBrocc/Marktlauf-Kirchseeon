<?php
/**
 * Sponsoring-Rechnungen — Aktionen (POST + CSRF).
 *   action=generate       : aus ausgewählten Sponsoren Rechnungsentwürfe erzeugen
 *                           (ohne Mail — der Kassier steht beim Sponsor-Versand in Kopie).
 *   action=assign_number  : fortlaufende Nummer einer Rechnung vergeben.
 *   action=discard        : Rechnung verwerfen (nur solange nicht versendet); Nummer wird frei.
 * Muster: sponsor_notiz.php / sponsor_versand.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/rechnung.php';
require_once __DIR__ . '/../../src/rechnung_repo.php';

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
    $id = (int) ($_POST['id'] ?? 0);
    // Der Kassier trägt nur die laufende Nummer (NN) ein; das Jahr ergänzt das System.
    $nn = trim($_POST['nn'] ?? '');
    if (!preg_match('/^\d{1,4}$/', $nn)) {
        $_SESSION['flash_error'] = 'Bitte eine laufende Nummer eingeben (1–4 Ziffern, z. B. 05).';
        header('Location: ../rechnungen.php');
        exit;
    }
    $nummer = $nn . '-' . date('Y');
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

if ($action === 'discard') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $res = rechnungVerwerfen($pdo, $id);
        $msg = $res['nummer'] !== ''
            ? 'Rechnung ' . $res['nummer'] . ' (' . $res['firma'] . ') verworfen — die Nummer '
                . $res['nummer'] . ' ist wieder frei.'
            : 'Entwurf (' . $res['firma'] . ') verworfen.';
        if ($res['status_zurueck']) {
            $msg .= ' Der Sponsor steht wieder auf „Bestätigt" und erscheint unter „Abzurechnen".';
        }
        $_SESSION['flash_success'] = $msg;
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (PDOException $e) {
        logError('Rechnung verwerfen (' . $id . '): ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Datenbankfehler beim Verwerfen.';
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

    // Bewusst KEINE Mail an den Kassier an dieser Stelle (Änderung 2026-08-10): der Kassier
    // erfährt vom Vorgang erst beim tatsächlichen Versand an den Sponsor, wo er in Kopie steht.
    // Die Nummernvergabe passiert ohnehin im Dashboard.
    $msg = count($erstellt) . ' Rechnungsentwurf/-entwürfe erstellt.';
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
