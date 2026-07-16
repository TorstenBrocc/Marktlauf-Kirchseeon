<?php
/**
 * E-Mail-Versand via SMTP (Strato) mit Fallback auf mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../sponsor_brief.php';

/**
 * BCC-Adresse für ausgehende Mails: bei JEDEM Versand bekommt info@ eine
 * Blindkopie. Reihenfolge: config['smtp_bcc'] > config['mail']['from_address']
 * > hartkodierter info@-Fallback. Leerer smtp_bcc => Fallback greift.
 */
function mailBccAddress(): string {
    $config = getConfig();
    $bcc = trim((string) ($config['smtp_bcc'] ?? ''));
    if ($bcc === '') {
        $bcc = trim((string) ($config['mail']['from_address'] ?? ''));
    }
    if ($bcc === '') {
        $bcc = 'info@atsv-kirchseeon-marktlauf.de';
    }
    return $bcc;
}

function sendMail(string $to, string $subject, string $textBody, string $htmlBody = ''): bool {
    $bccAddr = mailBccAddress();
    $bcc = ($bccAddr !== '' && strcasecmp($bccAddr, $to) !== 0) ? [$bccAddr] : [];

    $mailer = getSmtpMailer();

    if ($mailer !== null) {
        $result = $mailer->send($to, $subject, $textBody, $htmlBody, $bcc);
        if (!$result) {
            logError('SMTP error: ' . $mailer->getLastError());
        }
        return $result;
    }

    logError('SMTP unavailable, falling back to mail() for: ' . $to);

    $config = getConfig();
    $fromAddress = $config['mail']['from_address'] ?? 'noreply@example.com';
    $fromName = $config['mail']['from_name'] ?? 'Marktlauf';

    $headers = [
        'From'         => sprintf('%s <%s>', $fromName, $fromAddress),
        'Reply-To'     => $fromAddress,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer'     => 'PHP/' . phpversion(),
    ];

    if (!empty($bcc)) {
        $headers['Bcc'] = $bcc[0];
    }

    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return mail($to, $encodedSubject, $textBody, $headerString);
}

function sendHelferEingangsbestaetigung(string $to, string $name): bool {
    $subject = '✅ Du bist dabei – Marktlauf Kirchseeon';
    $body = <<<TEXT
Hallo {$name},

🎉 Deine Anmeldung als Helfer ist eingegangen!

Das Orga-Team meldet sich, sobald die Einsatzplanung abgeschlossen ist – du erhältst dann alle Details.

📧 Fragen? info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
https://atsv-kirchseeon-marktlauf.de
TEXT;

    return sendMail($to, $subject, $body);
}

function sendHelferBestaetigung(string $to, string $name, string $zugangLink): bool {
    $subject = '🎉 Du bist bestätigt – Marktlauf Kirchseeon';
    $body = <<<TEXT
Hallo {$name},

Deine Anmeldung als Helfer beim Marktlauf Kirchseeon wurde bestätigt!

Über deinen persönlichen Zugangslink kannst du jederzeit deine Anmeldung einsehen und weitere Infos abrufen:

{$zugangLink}

Bitte bewahre diesen Link auf – er ist dein persönlicher Zugang zu allen Helfer-Infos.

📧 Fragen? info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
https://atsv-kirchseeon-marktlauf.de
TEXT;

    return sendMail($to, $subject, $body);
}

function sendUserInvite(string $to, string $name, string $inviteLink, string $role): bool {
    $roleName = $role === 'admin' ? 'Administrator' : 'Orga-Mitglied';
    $subject = '🔑 Einladung zum Marktlauf Orga-Bereich';
    $body = <<<TEXT
Hallo {$name},

Du wurdest als {$roleName} zum Orga-Bereich des ATSV Kirchseeon Marktlaufs eingeladen!

Bitte klicke auf folgenden Link, um dein Passwort festzulegen und deinen Account zu aktivieren:

{$inviteLink}

Dieser Link ist 7 Tage gültig.

📧 Fragen? info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
https://atsv-kirchseeon-marktlauf.de
TEXT;

    return sendMail($to, $subject, $body);
}

/**
 * Passwort-Reset-Link versenden (admin-getriggert).
 */
function sendPasswortReset(string $to, string $name, string $resetLink): bool {
    $subject = '🔑 Passwort zurücksetzen — Marktlauf Orga-Bereich';
    $body = <<<TEXT
Hallo {$name},

für deinen Zugang zum Orga-Bereich des ATSV Kirchseeon Marktlaufs wurde ein
Passwort-Reset ausgelöst.

Über folgenden Link kannst du ein neues Passwort festlegen:

{$resetLink}

Dieser Link ist 48 Stunden gültig. Solange du kein neues Passwort setzt, bleibt
dein bisheriges gültig.

📧 Fragen? info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
https://atsv-kirchseeon-marktlauf.de
TEXT;

    return sendMail($to, $subject, $body);
}

/**
 * Sponsor-Anschreiben versenden (nativer SMTP-Mailer, HTML + Text-Fallback).
 *
 * Inhalt (Betreff + Körper) stammt aus der editierbaren Vorlage
 * sponsor_briefvorlagen bzw. – solange dort nichts gespeichert ist – aus dem
 * Code-Default (sponsorBriefDefaults). Dynamische Bestandteile werden über
 * Platzhalter eingesetzt. Vorlagen/Rendering: src/sponsor_brief.php.
 *
 * $typ ∈ erstanschreiben | folgejahr | frei
 */
function sendSponsorAnschreiben(
    string $to,
    string $anrede,
    string $vorname,
    string $nachname,
    string $firma,
    string $typ = 'erstanschreiben',
    string $paket = '',
    int $userId = 0
): bool {
    if (!sponsorBriefSlugValid($typ)) {
        $typ = 'erstanschreiben';
    }
    if ($userId === 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    $pdo      = getDbConnection();
    $vorlage  = sponsorBriefLoad($pdo, $typ);
    $ctx      = sponsorBriefContext($pdo, $userId, $anrede, $vorname, $nachname, $firma, $paket);
    $subject  = sponsorBriefBetreff($vorlage['betreff'], $ctx);
    $htmlBody = sponsorBriefRenderHtml($vorlage['koerper_md'], $ctx);
    $textBody = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);
    return sendMail($to, $subject, $textBody, $htmlBody);
}

function sendAufgabeErinnerung(string $to, string $name, string $aufgabeTitel, string $faelligAm): bool {
    $subject = '⏰ Erinnerung: Aufgabe fällig – ' . $aufgabeTitel;
    $body = <<<TEXT
Hallo {$name},

Erinnerung: Die folgende Aufgabe ist heute fällig:

📋 {$aufgabeTitel}
📅 Fällig am: {$faelligAm}

Bitte melde dich im Orga-Dashboard an, um die Aufgabe zu bearbeiten:
https://atsv-kirchseeon-marktlauf.de/orga/

📧 Fragen? info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
https://atsv-kirchseeon-marktlauf.de
TEXT;

    return sendMail($to, $subject, $body);
}
