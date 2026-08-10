<?php
/**
 * Sponsoring-Rechnung an den Sponsor senden (POST + CSRF).
 * Ablauf: finales PDF rendern → Pflicht-Ablage in Google Drive
 * (Orga/<Jahr>/Finanzen/Sponsoren-Abrechnungen; fehlt der Ordner → Abbruch mit
 * Ansage, KEIN Versand) → Mail an den Sponsor (info@, CC kassier@) → Protokoll.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/rechnung.php';
require_once __DIR__ . '/../../src/rechnung_repo.php';
require_once __DIR__ . '/../../src/rechnung_pdf.php';
require_once __DIR__ . '/../../src/sponsor_brief.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/google_drive.php';

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
$id        = (int) ($_POST['id'] ?? 0);
$empfaenger = trim($_POST['empfaenger'] ?? '');

try {
    $pdo = getDbConnection();
    $row = rechnungLaden($pdo, $id);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../rechnungen.php');
    exit;
}

if ($row === null) {
    $_SESSION['flash_error'] = 'Rechnung nicht gefunden.';
    header('Location: ../rechnungen.php');
    exit;
}
if (($row['status'] ?? '') !== 'nummeriert' || trim((string) $row['rechnungsnummer']) === '') {
    $_SESSION['flash_error'] = 'Die Rechnung braucht zuerst eine Rechnungsnummer.';
    header('Location: ../rechnungen.php');
    exit;
}

// Empfänger muss eine bekannte Adresse des Sponsors sein (kein freies Ziel).
$erlaubt = rechnungSponsorEmails($pdo, (int) $row['sponsor_id']);
$erlaubtLower = array_map('strtolower', $erlaubt);
if ($empfaenger === '' || !in_array(strtolower($empfaenger), $erlaubtLower, true)) {
    $_SESSION['flash_error'] = 'Bitte eine gültige Empfängeradresse des Sponsors wählen.';
    header('Location: ../rechnungen.php');
    exit;
}

$nummer   = (string) $row['rechnungsnummer'];
$jahr     = rechnungJahrAusNummer($nummer);
$snapshot = rechnungSnapshotAusRow($row);

// --- Pflicht-Ablage in Google Drive: ohne geht der Versand NICHT raus ---
if (!driveConfigured()) {
    $_SESSION['flash_error'] = 'Google Drive ist nicht konfiguriert — die Rechnung wurde NICHT versendet. '
        . 'Bitte Drive einrichten (storage/config.php) und den Vorgang erneut starten.';
    header('Location: ../rechnungen.php');
    exit;
}
$ordnerId = driveFindeRechnungsordner($jahr);
if ($ordnerId === null) {
    $_SESSION['flash_error'] = 'Der Ablageordner „' . driveRechnungsordnerPfad($jahr) . '" fehlt in Google Drive — '
        . 'die Rechnung wurde NICHT versendet. Bitte den Ordner anlegen und den Vorgang erneut starten.';
    header('Location: ../rechnungen.php');
    exit;
}

// PDF rendern + in eine Temp-Datei (für Drive-Upload und Mail-Anhang)
$pdfBytes = rechnungPdfErzeugen($snapshot, $nummer);
$tmp = tempnam(sys_get_temp_dir(), 'rech') . '.pdf';
file_put_contents($tmp, $pdfBytes);

// Dateiname: Jahr zuerst, dann Sponsor
$firmaSafe = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $row['empfaenger_firma']);
$driveName = $jahr . '_' . trim($firmaSafe, '-') . '_Rechnung_' . $nummer . '.pdf';

try {
    $driveFileId = driveUploadToFolder($ordnerId, $tmp, $driveName, 'application/pdf');
} catch (Throwable $e) {
    @unlink($tmp);
    logError('Rechnung Drive-Upload: ' . $e->getMessage());
    rechnungVersandLog($pdo, $id, $empfaenger, $userId, null, 'fehler', 'Drive-Upload fehlgeschlagen');
    $_SESSION['flash_error'] = 'Ablage in Google Drive fehlgeschlagen — die Rechnung wurde NICHT versendet. '
        . 'Bitte den Vorgang erneut starten.';
    header('Location: ../rechnungen.php');
    exit;
}

// --- Mail an den Sponsor (HTML-Layout wie die Anschreiben), CC an Kassier ---
$vorlage = sponsorBriefLoad($pdo, 'rechnung', 0);
$ctx     = rechnungMailContext($row);
$subject = sponsorBriefBetreff($vorlage['betreff'], $ctx);
$html    = sponsorBriefRenderHtml($vorlage['koerper_md'], $ctx);
$text    = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);

$attachments = [[
    'path' => $tmp,
    'name' => 'Rechnung_' . $nummer . '.pdf',
    'mime' => 'application/pdf',
]];
// --- ALTFALL-RETROFIT (2026) — nach den 4 Bestandssponsoren rückbaubar ---
// Bedingungen (aus dem Drive-Ordner „_assets Sponsoren") an die Rechnung + Klausel „mit Begleichung
// als vereinbart" (rechnung-Vorlage), weil diese Sponsoren ihre Bestätigung noch OHNE Bedingungen
// erhalten haben. Für Neu-Sponsoren gehen die Bedingungen ohnehin bei Erstanschreiben/Bestätigung
// mit. Rückbau: diesen Block + die Klausel in sponsorBriefDefaults()['rechnung'] + den Hinweis in
// orga/rechnungen.php entfernen.
$attachments = array_merge($attachments, sponsorBedingungenAnhang($pdo));
// --- Ende Altfall-Retrofit ---
// Kassier sichtbar in Kopie (nicht blind): der Sponsor soll sehen, dass die Buchhaltung
// mitliest, und der Kassier erfährt vom Versand genau hier — seit 2026-08-10 gibt es keine
// separate Anstoß-Mail beim Erzeugen des Entwurfs mehr.
$kassier = rechnungStammdaten()['kassier_email'];

$ok = false;
try {
    $ok = sendMail($empfaenger, $subject, $text, $html, $attachments, [], [$kassier]);
} catch (Throwable $e) {
    logError('Rechnung Mailversand: ' . $e->getMessage());
}
@unlink($tmp);

rechnungVersandLog(
    $pdo, $id, $empfaenger, $userId, $driveFileId,
    $ok ? 'ok' : 'fehler',
    $ok ? null : 'PDF liegt in Drive, aber Mailversand fehlgeschlagen'
);

$_SESSION['flash_success'] = $ok
    ? 'Rechnung ' . $nummer . ' an ' . $empfaenger . ' gesendet (Kassier in Kopie) und in Google Drive abgelegt.'
    : '';
if (!$ok) {
    $_SESSION['flash_error'] = 'Die Rechnung liegt in Google Drive, aber der Mailversand ist fehlgeschlagen. Bitte erneut senden.';
}
header('Location: ../rechnungen.php');
exit;
