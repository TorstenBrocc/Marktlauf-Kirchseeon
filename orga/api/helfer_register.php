<?php
/**
 * Helfer-Registrierung Handler (Token-gegatet)
 * Verarbeitet das öffentliche Anmeldeformular.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/channels/mail.php';

initSession();

function redirectWithError(string $message, string $token = ''): void {
    $_SESSION['helfer_error'] = $message;
    $url = '../../helfer-anmeldung.php?error=1';
    if ($token !== '') {
        $url .= '&token=' . urlencode($token);
    }
    header('Location: ' . $url);
    exit;
}

function isValidAccessToken(string $token): bool {
    if ($token === '' || strlen($token) > 64) {
        return false;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT id FROM access_tokens
            WHERE token = :token AND active = 1 AND expires_at > NOW()
        ');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../helfer-anmeldung.php');
    exit;
}

$accessToken = trim($_POST['access_token'] ?? '');
if (!isValidAccessToken($accessToken)) {
    header('Location: ../../helfer-anmeldung.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    redirectWithError('Ungültige Anfrage. Bitte erneut versuchen.', $accessToken);
}

$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
    header('Location: ../../helfer-anmeldung.php?success=1');
    exit;
}

recordRegisterAttempt();

if (isRegisterRateLimited()) {
    redirectWithError('Zu viele Anmeldungen von dieser Adresse. Bitte später erneut versuchen.', $accessToken);
}

$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$slots = $_POST['slots'] ?? [];
$beitrag = $_POST['beitrag'] ?? [];
$beitragFreitext = trim($_POST['beitrag_freitext'] ?? '');
$kuchenArt       = trim($_POST['kuchen_art'] ?? '');
$kuchenNuesse    = trim($_POST['kuchen_nuesse'] ?? '');

if (empty($vorname) || empty($nachname) || empty($email) || empty($phone)) {
    redirectWithError('Bitte fülle alle Pflichtfelder aus.', $accessToken);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Bitte gib eine gültige E-Mail-Adresse ein.', $accessToken);
}

if (strlen($vorname) > 50 || strlen($nachname) > 50 || strlen($email) > 255 || strlen($phone) > 30) {
    redirectWithError('Eingabe zu lang.', $accessToken);
}

if (!is_array($slots)) {
    $slots = [];
}
if (!is_array($beitrag)) {
    $beitrag = [];
}

$validSlots = [];
$slotPattern = '/^(\d{4}-\d{2}-\d{2})_(vormittag|nachmittag)$/';
foreach ($slots as $slot) {
    if (preg_match($slotPattern, $slot, $matches)) {
        $validSlots[] = [
            'tag' => $matches[1],
            'zeitfenster' => $matches[2],
        ];
    }
}

$validBeitragTypes = ['kuchen', 'equipment', 'sonstiges'];
$validBeitrag = array_filter($beitrag, fn($b) => in_array($b, $validBeitragTypes, true));

$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0xffff),
    random_int(0, 0x0fff) | 0x4000,
    random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO helfer (uuid, vorname, nachname, email, phone, status)
        VALUES (:uuid, :vorname, :nachname, :email, :phone, :status)
    ');
    $stmt->execute([
        'uuid'     => $uuid,
        'vorname'  => $vorname,
        'nachname' => $nachname,
        'email'    => $email,
        'phone'    => $phone,
        'status'   => 'neu',
    ]);
    $helferId = (int) $pdo->lastInsertId();

    if (!empty($validSlots)) {
        $slotStmt = $pdo->prepare('
            INSERT INTO helfer_slots (helfer_id, tag, zeitfenster)
            VALUES (:helfer_id, :tag, :zeitfenster)
        ');
        foreach ($validSlots as $slot) {
            $slotStmt->execute([
                'helfer_id'  => $helferId,
                'tag'        => $slot['tag'],
                'zeitfenster' => $slot['zeitfenster'],
            ]);
        }
    }

    if (!empty($validBeitrag)) {
        $beitragStmt = $pdo->prepare('
            INSERT INTO helfer_beitrag (helfer_id, typ, freitext)
            VALUES (:helfer_id, :typ, :freitext)
        ');
        foreach ($validBeitrag as $typ) {
            if ($typ === 'kuchen') {
                $freitext = $kuchenArt !== '' ? $kuchenArt . ($kuchenNuesse !== '' && $kuchenNuesse !== 'nein' ? ' | Nüsse: ' . $kuchenNuesse : '') : null;
            } elseif ($typ === 'sonstiges' && $beitragFreitext !== '') {
                $freitext = $beitragFreitext;
            } else {
                $freitext = null;
            }
            $beitragStmt->execute([
                'helfer_id' => $helferId,
                'typ'       => $typ,
                'freitext'  => $freitext,
            ]);
        }
    }

    $pdo->commit();

    sendHelferEingangsbestaetigung($email, $vorname . ' ' . $nachname);

    header('Location: ../../helfer-anmeldung.php?success=1');
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uk_helfer_email')) {
        redirectWithError('Diese E-Mail-Adresse ist bereits angemeldet.', $accessToken);
    }

    error_log('Helfer registration error: ' . $e->getMessage(), 3, __DIR__ . '/../../storage/logs/error.log');
    redirectWithError('Ein Fehler ist aufgetreten. Bitte versuche es später erneut.', $accessToken);
}
