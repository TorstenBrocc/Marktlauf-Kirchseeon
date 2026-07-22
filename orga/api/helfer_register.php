<?php
/**
 * Helfer-Registrierung Handler (Token-gegatet)
 * Verarbeitet das öffentliche Anmeldeformular.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/helfer_aufgaben.php';

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
$isMinorRaw      = $_POST['is_minor'] ?? '';
$consentPhoto    = $_POST['consent_photo'] ?? '';
$guardianName    = trim($_POST['guardian_name'] ?? '');

if (empty($vorname) || empty($nachname) || empty($email) || empty($phone)) {
    redirectWithError('Bitte fülle alle Pflichtfelder aus.', $accessToken);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Bitte gib eine gültige E-Mail-Adresse ein.', $accessToken);
}

if (strlen($vorname) > 50 || strlen($nachname) > 50 || strlen($email) > 255 || strlen($phone) > 30) {
    redirectWithError('Eingabe zu lang.', $accessToken);
}

// --- Fotoeinwilligung (DSGVO) serverseitig validieren ---
if ($isMinorRaw !== '0' && $isMinorRaw !== '1') {
    redirectWithError('Bitte gib an, ob du dich selbst oder eine minderjährige Person anmeldest.', $accessToken);
}
$isMinor = ($isMinorRaw === '1') ? 1 : 0;

if ($consentPhoto !== 'yes' && $consentPhoto !== 'no') {
    redirectWithError('Bitte triff eine Auswahl zur Fotoeinwilligung (Ja/Nein).', $accessToken);
}

// Bedingte Pflicht: bei Minderjährigen ist der Name der erziehungsberechtigten
// Person zwingend — sonst ist die Einwilligung rechtlich wertlos.
if ($isMinor === 1 && $guardianName === '') {
    redirectWithError('Bei der Anmeldung einer minderjährigen Person ist der Name der erziehungsberechtigten Person erforderlich.', $accessToken);
}
if (!$isMinor) {
    $guardianName = '';
}
if (strlen($guardianName) > 255) {
    redirectWithError('Name der erziehungsberechtigten Person zu lang.', $accessToken);
}

if (!is_array($slots)) {
    $slots = [];
}
if (!is_array($beitrag)) {
    $beitrag = [];
}

// Slots nur aus angebotenen Schichten akzeptieren (Key = schicht_id).
// schicht_id = verbindliche Referenz; tag/zeitfenster/aufgabe als Snapshot.
$validSlots = [];
$seenSchichten = [];
foreach ($slots as $slotKey) {
    if (!is_string($slotKey)) {
        continue;
    }
    $aufgabe = helferAufgabeByKey($slotKey);
    if ($aufgabe !== null && !isset($seenSchichten[$aufgabe['schicht_id']])) {
        $seenSchichten[$aufgabe['schicht_id']] = true;
        $validSlots[] = [
            'schicht_id'  => $aufgabe['schicht_id'],
            'tag'         => $aufgabe['tag'],
            'zeitfenster' => $aufgabe['zeitfenster'],
            'aufgabe'     => $aufgabe['beschreibung'],
        ];
    }
}

// Beitrag: "kuchen" per Checkbox; "sonstiges" ergibt sich allein aus dem
// Freitext (kein eigenes Auswahlfeld mehr).
$validBeitrag = in_array('kuchen', $beitrag, true) ? ['kuchen'] : [];
if ($beitragFreitext !== '') {
    $validBeitrag[] = 'sonstiges';
}

$uuid = uuid();

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO helfer (uuid, vorname, nachname, email, phone, status, is_minor, consent_photo, guardian_name, consent_ts)
        VALUES (:uuid, :vorname, :nachname, :email, :phone, :status, :is_minor, :consent_photo, :guardian_name, NOW())
    ');
    $stmt->execute([
        'uuid'          => $uuid,
        'vorname'       => $vorname,
        'nachname'      => $nachname,
        'email'         => $email,
        'phone'         => $phone,
        'status'        => 'neu',
        'is_minor'      => $isMinor,
        'consent_photo' => $consentPhoto,
        'guardian_name' => $isMinor ? $guardianName : null,
    ]);
    $helferId = (int) $pdo->lastInsertId();

    if (!empty($validSlots)) {
        $slotStmt = $pdo->prepare('
            INSERT INTO helfer_slots (helfer_id, schicht_id, tag, zeitfenster, aufgabe)
            VALUES (:helfer_id, :schicht_id, :tag, :zeitfenster, :aufgabe)
        ');
        foreach ($validSlots as $slot) {
            $slotStmt->execute([
                'helfer_id'   => $helferId,
                'schicht_id'  => $slot['schicht_id'],
                'tag'         => $slot['tag'],
                'zeitfenster' => $slot['zeitfenster'],
                'aufgabe'     => $slot['aufgabe'],
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
                $nuesse = $kuchenNuesse === 'ja' ? 'enthält Nüsse' : '';
                $freitext = trim($kuchenArt . ($kuchenArt !== '' && $nuesse !== '' ? ' | ' : '') . $nuesse);
                $freitext = $freitext !== '' ? $freitext : null;
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

    try {
        sendHelferEingangsbestaetigung($email, $vorname . ' ' . $nachname);
    } catch (Throwable $e) {
        logError('Mail send error: ' . $e->getMessage());
    }

    header('Location: ../../helfer-anmeldung.php?success=1');
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logError('Helfer registration error: ' . $e->getMessage());
    redirectWithError('Ein Fehler ist aufgetreten. Bitte versuche es später erneut.', $accessToken);
}
