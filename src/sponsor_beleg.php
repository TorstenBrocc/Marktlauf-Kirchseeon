<?php
/**
 * Sponsor-Beleg: die „Bestätigung Sponsoring" als PDF im Sponsor-Drive-Ordner ablegen.
 * Bewusst getrennt vom Versand (channels/mail.php): sowohl die Versandpfade
 * (Sofort + Queue) als auch ein manueller Retry aus der Sponsoren-Maske nutzen
 * dieselbe Logik — und Fehler werden als klare Meldung nach oben gereicht statt
 * still geloggt. Die Mail wird dabei NIE erneut versendet (nur die Ablage).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sponsor_brief.php';
require_once __DIR__ . '/sponsor_rotation.php';

/**
 * Bestätigung für einen Sponsor aus der Vorlage regenerieren (repräsentativer
 * Ansprechpartner = erster „im Anschreiben") und als PDF im Sponsor-Drive-Ordner
 * ablegen. Idempotent (überschreibt die Jahresdatei). Wirft RuntimeException mit
 * klarer, behebbarer Meldung, wenn etwas fehlt oder fehlschlägt.
 */
function archiveSponsorBestaetigung(PDO $pdo, int $sponsorId, int $userId = 0): void
{
    if ($sponsorId <= 0) {
        throw new RuntimeException('Ungültiger Sponsor.');
    }
    if (!driveConfigured()) {
        throw new RuntimeException('Google Drive ist nicht konfiguriert — Ablage nicht möglich.');
    }

    $s = $pdo->prepare('SELECT firma, paket FROM sponsors WHERE id = :id');
    $s->execute(['id' => $sponsorId]);
    $sp = $s->fetch();
    if (!$sp) {
        throw new RuntimeException('Sponsor nicht gefunden.');
    }
    $firma = (string) $sp['firma'];

    if ($userId === 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }

    // sponsorId durchreichen, damit der Beleg denselben pro-Sponsor-Stand zeigt wie Mail/Vorschau.
    $vorlage  = sponsorBriefLoad($pdo, 'bestaetigung', $userId, $sponsorId);
    // Repräsentativer Ansprechpartner + Sponsor-Platzhalter kommen aus der gemeinsamen Quelle,
    // damit Beleg-PDF, Live-Vorschau und Mail denselben Empfänger und dieselben Werte sehen.
    $ctx      = sponsorBriefKontextFuerSponsor($pdo, $sponsorId, $userId);
    if ($ctx === null) {
        throw new RuntimeException('Sponsor nicht gefunden.');
    }
    $subject  = sponsorBriefBetreff($vorlage['betreff'], $ctx);
    $textBody = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);

    fileSponsorBestaetigungPdf($pdo, $sponsorId, $firma, $subject, $textBody);
}
