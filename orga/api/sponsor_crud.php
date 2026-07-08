<?php
/**
 * Sponsor CRUD Handler (POST)
 * Actions: create, update, delete, kein_kontakt_set, kein_kontakt_remove
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_status.php';

/**
 * prioritaet aus dem Formular (leer|1|2|3) validieren.
 */
function sponsorPrioritaetFromPost(mixed $raw): ?int {
    $v = trim((string) $raw);
    if ($v === '' || !ctype_digit($v)) {
        return null;
    }
    $n = (int) $v;
    return ($n >= 1 && $n <= 3) ? $n : null;
}

/**
 * status aus dem Formular gegen die erlaubten Werte prüfen.
 */
function sponsorStatusFromPost(mixed $raw): string {
    $v = (string) $raw;
    return sponsorStatusValid($v) ? $v : 'neu';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsoren.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsoren.php');
    exit;
}

$action = $_POST['action'] ?? '';
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$isAdmin = isAdminFromGuard();

$validActions = ['create', 'update', 'delete', 'kein_kontakt_set', 'kein_kontakt_remove'];
if (!in_array($action, $validActions, true)) {
    $_SESSION['flash_error'] = 'Ungültige Aktion.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'delete' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Sponsoren löschen.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'kein_kontakt_remove' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Kein-Kontakt zurücknehmen.';
    header('Location: ../sponsoren.php');
    exit;
}

function saveAnsprechpartner(PDO $pdo, int $sponsorId, array $post): void {
    $pdo->prepare('DELETE FROM sponsor_ansprechpartner WHERE sponsor_id = :id')->execute(['id' => $sponsorId]);

    $anreden = $post['ap_anrede'] ?? [];
    $vornamen = $post['ap_vorname'] ?? [];
    $nachnamen = $post['ap_nachname'] ?? [];
    $funktionen = $post['ap_funktion'] ?? [];
    $emails = $post['ap_email'] ?? [];

    if (!is_array($anreden)) return;

    $stmt = $pdo->prepare('
        INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, vorname, nachname, funktion, email)
        VALUES (:sponsor_id, :anrede, :vorname, :nachname, :funktion, :email)
    ');

    for ($i = 0; $i < count($anreden); $i++) {
        $vorname = trim($vornamen[$i] ?? '');
        $nachname = trim($nachnamen[$i] ?? '');
        $email = trim($emails[$i] ?? '');

        if ($vorname === '' && $nachname === '' && $email === '') {
            continue;
        }

        $stmt->execute([
            'sponsor_id' => $sponsorId,
            'anrede'     => $anreden[$i] ?? '',
            'vorname'    => $vorname,
            'nachname'   => $nachname,
            'funktion'   => trim($funktionen[$i] ?? ''),
            'email'      => $email,
        ]);
    }
}

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'create':
            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php');
                exit;
            }

            $keinKontakt = isset($_POST['kein_kontakt']) ? 1 : 0;
            $keinKontaktDatum = $_POST['kein_kontakt_datum'] ?? '';
            if ($keinKontakt && empty($keinKontaktDatum)) {
                $keinKontaktDatum = date('Y-m-d');
            }

            $stmt = $pdo->prepare('
                INSERT INTO sponsors (firma, paket, prioritaet, ort, summe, status, kein_kontakt, kein_kontakt_grund, kein_kontakt_wer, kein_kontakt_datum, notizen, wiedervorlage)
                VALUES (:firma, :paket, :prioritaet, :ort, :summe, :status, :kein_kontakt, :kein_kontakt_grund, :kein_kontakt_wer, :kein_kontakt_datum, :notizen, :wiedervorlage)
            ');
            $stmt->execute([
                'firma'              => $firma,
                'paket'              => $_POST['paket'] ?: null,
                'prioritaet'         => sponsorPrioritaetFromPost($_POST['prioritaet'] ?? ''),
                'ort'                => trim($_POST['ort'] ?? '') ?: null,
                'summe'              => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'             => sponsorStatusFromPost($_POST['status'] ?? 'neu'),
                'kein_kontakt'       => $keinKontakt,
                'kein_kontakt_grund' => $keinKontakt ? (trim($_POST['kein_kontakt_grund'] ?? '') ?: null) : null,
                'kein_kontakt_wer'   => $keinKontakt ? (trim($_POST['kein_kontakt_wer'] ?? '') ?: null) : null,
                'kein_kontakt_datum' => $keinKontakt ? ($keinKontaktDatum ?: null) : null,
                'notizen'            => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'      => $_POST['wiedervorlage'] ?: null,
            ]);

            $newSponsorId = (int) $pdo->lastInsertId();

            try {
                saveAnsprechpartner($pdo, $newSponsorId, $_POST);
            } catch (PDOException $e) {
                // Table may not exist yet
            }

            $_SESSION['flash_success'] = 'Sponsor angelegt.';
            header('Location: ../sponsoren.php');
            exit;

        case 'update':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php?id=' . $sponsorId);
                exit;
            }

            $existing = $pdo->prepare('SELECT kein_kontakt FROM sponsors WHERE id = :id');
            $existing->execute(['id' => $sponsorId]);
            $sponsor = $existing->fetch();

            if (!$sponsor) {
                $_SESSION['flash_error'] = 'Sponsor nicht gefunden.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $keinKontakt = $sponsor['kein_kontakt'];
            $keinKontaktGrund = null;
            $keinKontaktWer = null;
            $keinKontaktDatum = null;

            if (isset($_POST['kein_kontakt'])) {
                $keinKontakt = 1;
                $keinKontaktGrund = trim($_POST['kein_kontakt_grund'] ?? '') ?: null;
                $keinKontaktWer = trim($_POST['kein_kontakt_wer'] ?? '') ?: null;
                $keinKontaktDatum = $_POST['kein_kontakt_datum'] ?? '';
                if (empty($keinKontaktDatum)) {
                    $keinKontaktDatum = date('Y-m-d');
                }
            } elseif ($isAdmin) {
                $keinKontakt = 0;
            } else {
                $existingFull = $pdo->prepare('SELECT kein_kontakt_grund, kein_kontakt_wer, kein_kontakt_datum FROM sponsors WHERE id = :id');
                $existingFull->execute(['id' => $sponsorId]);
                $full = $existingFull->fetch();
                $keinKontaktGrund = $full['kein_kontakt_grund'];
                $keinKontaktWer = $full['kein_kontakt_wer'];
                $keinKontaktDatum = $full['kein_kontakt_datum'];
            }

            $stmt = $pdo->prepare('
                UPDATE sponsors SET
                    firma = :firma,
                    paket = :paket,
                    prioritaet = :prioritaet,
                    ort = :ort,
                    summe = :summe,
                    status = :status,
                    kein_kontakt = :kein_kontakt,
                    kein_kontakt_grund = :kein_kontakt_grund,
                    kein_kontakt_wer = :kein_kontakt_wer,
                    kein_kontakt_datum = :kein_kontakt_datum,
                    notizen = :notizen,
                    wiedervorlage = :wiedervorlage
                WHERE id = :id
            ');
            $stmt->execute([
                'firma'              => $firma,
                'paket'              => $_POST['paket'] ?: null,
                'prioritaet'         => sponsorPrioritaetFromPost($_POST['prioritaet'] ?? ''),
                'ort'                => trim($_POST['ort'] ?? '') ?: null,
                'summe'              => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'             => sponsorStatusFromPost($_POST['status'] ?? 'neu'),
                'kein_kontakt'       => $keinKontakt,
                'kein_kontakt_grund' => $keinKontaktGrund,
                'kein_kontakt_wer'   => $keinKontaktWer,
                'kein_kontakt_datum' => $keinKontaktDatum ?: null,
                'notizen'            => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'      => $_POST['wiedervorlage'] ?: null,
                'id'                 => $sponsorId,
            ]);

            try {
                saveAnsprechpartner($pdo, $sponsorId, $_POST);
            } catch (PDOException $e) {
                // Table may not exist yet
            }

            $_SESSION['flash_success'] = 'Sponsor aktualisiert.';
            header('Location: ../sponsor_form.php?id=' . $sponsorId);
            exit;

        case 'delete':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM sponsors WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Sponsor gelöscht.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_set':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 1, kein_kontakt_datum = COALESCE(kein_kontakt_datum, CURDATE()) WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt gesetzt.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_remove':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 0, kein_kontakt_grund = NULL, kein_kontakt_wer = NULL, kein_kontakt_datum = NULL WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt aufgehoben.';
            header('Location: ../sponsoren.php');
            exit;
    }

} catch (PDOException $e) {
    logError('Sponsor CRUD error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../sponsoren.php');
    exit;
}
