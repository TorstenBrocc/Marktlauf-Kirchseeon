<?php
/**
 * E-Mail-Versand via SMTP (Strato) mit Fallback auf mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../logger.php';

function sendMail(string $to, string $subject, string $textBody, string $htmlBody = ''): bool {
    $mailer = getSmtpMailer();

    if ($mailer !== null) {
        $result = $mailer->send($to, $subject, $textBody, $htmlBody);
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
 * Persönliche Anrede aus Anrede-Feld + Nachname bilden.
 * Fallback auf "Sehr geehrte Damen und Herren," wenn kein Nachname vorliegt.
 */
function sponsorAnrede(string $anrede, string $nachname): string {
    $nachname = trim($nachname);
    if ($nachname === '') {
        return 'Sehr geehrte Damen und Herren,';
    }
    if ($anrede === 'Frau') {
        return "Sehr geehrte Frau {$nachname},";
    }
    if ($anrede === 'Herr') {
        return "Sehr geehrter Herr {$nachname},";
    }
    return 'Sehr geehrte Damen und Herren,';
}

/**
 * Sponsor-Anschreiben versenden (nativer SMTP-Mailer).
 *
 * Zwei Varianten (intern/sponsor-crm-ausbau.md §5.1):
 *   - 'erstanschreiben' — Erstkontakt
 *   - 'folgejahr'       — Bestandssponsor / vertrauterer Ton
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │ PLATZHALTER-TEXTE — vor dem ersten echten Versand ersetzen!           │
 * │ Der finale Wortlaut (aus dem bisherigen Versand-Script/OneDrive)      │
 * │ gehört in $textErst / $textFolge unten. Struktur/Personalisierung     │
 * │ ({anrede}, {firma}) sind bereits verdrahtet.                          │
 * └─────────────────────────────────────────────────────────────────────┘
 */
function sendSponsorAnschreiben(
    string $to,
    string $anrede,
    string $nachname,
    string $firma,
    string $typ = 'erstanschreiben'
): bool {
    $begruessung = sponsorAnrede($anrede, $nachname);
    $firma = trim($firma) !== '' ? trim($firma) : 'Ihr Unternehmen';

    if ($typ === 'folgejahr') {
        $subject = 'Auch 2026 wieder dabei? – Marktlauf Kirchseeon';
        $text = <<<TEXT
{$begruessung}

schön, dass {$firma} den Marktlauf Kirchseeon im vergangenen Jahr unterstützt hat –
darüber haben wir uns sehr gefreut!

[PLATZHALTER: Folgejahr-Anschreiben – finalen Text hier einsetzen.]

Wir würden uns freuen, wenn Sie auch beim Marktlauf 2026 wieder mit an Bord wären.

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
info@atsv-kirchseeon-marktlauf.de
https://atsv-kirchseeon-marktlauf.de
TEXT;
    } else {
        $subject = 'Sponsoring-Anfrage – Marktlauf Kirchseeon 2026';
        $text = <<<TEXT
{$begruessung}

der ATSV Kirchseeon veranstaltet 2026 wieder den Marktlauf – ein Laufevent für die
ganze Region – und wir suchen dafür Unterstützerinnen und Unterstützer aus der
lokalen Wirtschaft.

[PLATZHALTER: Erstanschreiben – finalen Text hier einsetzen.]

Über eine Rückmeldung von {$firma} würden wir uns sehr freuen.

Sportliche Grüße
Dein Marktlauf-Team
──────────────────────────
ATSV Kirchseeon Marktlauf
info@atsv-kirchseeon-marktlauf.de
https://atsv-kirchseeon-marktlauf.de
TEXT;
    }

    return sendMail($to, $subject, $text);
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
