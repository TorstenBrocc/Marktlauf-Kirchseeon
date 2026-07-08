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
 * Persönliche Anrede bilden (kaskadierender Fallback wie im bisherigen Versand-Script):
 *   Herr/Frau + Nachname → "Sehr geehrte(r) Frau/Herr {Nachname},"
 *   sonst Firma vorhanden → "Sehr geehrte Damen und Herren der {Firma},"
 *   sonst                 → "Sehr geehrte Damen und Herren,"
 */
function sponsorAnrede(string $anrede, string $nachname, string $firma = ''): string {
    $nachname = trim($nachname);
    if ($nachname !== '' && $anrede === 'Frau') {
        return "Sehr geehrte Frau {$nachname},";
    }
    if ($nachname !== '' && $anrede === 'Herr') {
        return "Sehr geehrter Herr {$nachname},";
    }
    $firma = trim($firma);
    if ($firma !== '') {
        return "Sehr geehrte Damen und Herren der {$firma},";
    }
    return 'Sehr geehrte Damen und Herren,';
}

/**
 * Paketabhängiger Textbaustein (aus dem bisherigen Versand-Script).
 */
function sponsorLevelText(string $paket): string {
    return match ($paket) {
        'gold', 'hauptsponsor' =>
            'Als führender regionaler Akteur würden wir uns besonders freuen, '
            . 'Sie als Gold-Sponsor auf unserer zentralen Bühne präsentieren zu dürfen.',
        'silber' =>
            'Mit einem Silber-Sponsoring sichern Sie sich hervorragende Sichtbarkeit '
            . 'direkt auf den Laufshirts und Startnummern unserer Teilnehmer.',
        default =>
            'Schon mit unserem Bronze-Paket leisten Sie einen wertvollen Beitrag '
            . 'für die Gemeinschaft und sind auf allen digitalen Kanälen präsent.',
    };
}

/**
 * Signatur-Block aus der Config (nicht im Repo — enthält Name/Telefon).
 * Fallback auf generische Team-Signatur, falls nicht konfiguriert.
 * @return array{name:string, role:string, phone:string}
 */
function sponsorSignatur(): array {
    $cfg = getConfig()['sponsor_mail'] ?? [];
    return [
        'name'  => $cfg['sender_name'] ?? 'Orga-Team Marktlauf Kirchseeon',
        'role'  => $cfg['sender_role'] ?? 'Sponsoring · Marktlauf Kirchseeon, ATSV Kirchseeon e.V.',
        'phone' => $cfg['sender_phone'] ?? '',
    ];
}

/**
 * Sponsor-Anschreiben versenden (nativer SMTP-Mailer, HTML + Text-Fallback).
 *
 * Zwei Varianten (intern/sponsor-crm-ausbau.md §5.1):
 *   - 'erstanschreiben' — Erstkontakt (Text aus bisherigem Versand-Script)
 *   - 'folgejahr'       — Bestandssponsor / vertrauterer Ton (angepasst)
 *
 * Event-Eckdaten (Datum, Ort, Pakete/Preise, Rückmeldefrist) sind bewusst hier
 * hinterlegt — bei Jahres-/Preiswechsel hier anpassen.
 */
function sendSponsorAnschreiben(
    string $to,
    string $anrede,
    string $nachname,
    string $firma,
    string $typ = 'erstanschreiben',
    string $paket = ''
): bool {
    $begruessung = sponsorAnrede($anrede, $nachname, $firma);
    $firmaText = trim($firma) !== '' ? trim($firma) : 'Ihr Unternehmen';
    $levelText = sponsorLevelText($paket);
    $sig = sponsorSignatur();

    // Signatur (HTML + Text)
    $sigPhoneHtml = $sig['phone'] !== '' ? 'T: ' . htmlspecialchars($sig['phone']) . ' | ' : '';
    $sigHtml = '<p>Herzliche Grüße<br><br>'
        . '<strong>' . htmlspecialchars($sig['name']) . '</strong><br>'
        . htmlspecialchars($sig['role']) . '<br>'
        . $sigPhoneHtml . 'W: <a href="https://atsv-kirchseeon-marktlauf.de">atsv-kirchseeon-marktlauf.de</a></p>';
    $sigPhoneText = $sig['phone'] !== '' ? "T: {$sig['phone']} | " : '';
    $sigText = "Herzliche Grüße\n\n{$sig['name']}\n{$sig['role']}\n"
        . "{$sigPhoneText}W: atsv-kirchseeon-marktlauf.de";

    $paketTabelle = <<<HTML
        <h3>Unsere Sponsoring-Pakete im Überblick:</h3>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr style="background-color: #f2f2f2;">
                <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Paket</th>
                <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Investition</th>
                <th style="border: 1px solid #dddddd; text-align: left; padding: 8px;">Highlights</th>
            </tr>
            <tr>
                <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Bronze</strong></td>
                <td style="border: 1px solid #dddddd; padding: 8px;">250 &euro;</td>
                <td style="border: 1px solid #dddddd; padding: 8px;">Logo auf Website, Startet&uuml;ten-Branding, Urkunde, Dankesschreiben</td>
            </tr>
            <tr style="background-color: #fafafa;">
                <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Silber</strong></td>
                <td style="border: 1px solid #dddddd; padding: 8px;">500 &euro;</td>
                <td style="border: 1px solid #dddddd; padding: 8px;">+ Logo auf Startnummer &amp; Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startpl&auml;tze</td>
            </tr>
            <tr>
                <td style="border: 1px solid #dddddd; padding: 8px;"><strong>Gold</strong></td>
                <td style="border: 1px solid #dddddd; padding: 8px;">1.000 &euro;</td>
                <td style="border: 1px solid #dddddd; padding: 8px;">+ Banner zentral im Start-/Zielbereich, eigener Stand inkl. Fl&auml;che, 5 Startpl&auml;tze, Moderations-Erw&auml;hnungen</td>
            </tr>
        </table>
HTML;

    $paketTextListe = "Sponsoring-Pakete:\n"
        . "- Bronze (250 €): Logo auf Website, Startetüten-Branding, Urkunde, Dankesschreiben\n"
        . "- Silber (500 €): + Logo auf Startnummer & Streckenbanner, Namensnennung Presse, Logo auf Lauf-Shirt, 3 Startplätze\n"
        . "- Gold (1.000 €): + Banner zentral im Start-/Zielbereich, eigener Stand, 5 Startplätze, Moderations-Erwähnungen";

    if ($typ === 'folgejahr') {
        $subject = "Auch 2026 wieder dabei? Marktlauf Kirchseeon – {$firmaText}";
        $intro = "schön, dass {$firmaText} beim 1. Marktlauf Kirchseeon dabei war – "
            . "dafür noch einmal herzlichen Dank!";
        $absatz2 = 'Am <strong>20. September 2026</strong> geht der <strong>2. Marktlauf Kirchseeon</strong> '
            . 'auf dem Westring an den Start – gemeinsam mit der Gemeinde im Rahmen des Energie- und Umwelttags, '
            . 'diesmal noch größer: <strong>300 Läufer, rund 900 Gäste</strong>. Wir würden uns sehr freuen, '
            . 'wenn Sie auch 2026 wieder mit an Bord wären.';
        $absatz2Text = 'Am 20. September 2026 geht der 2. Marktlauf Kirchseeon auf dem Westring an den Start – '
            . 'gemeinsam mit der Gemeinde im Rahmen des Energie- und Umwelttags, diesmal noch größer: '
            . '300 Läufer, rund 900 Gäste. Wir würden uns sehr freuen, wenn Sie auch 2026 wieder mit an Bord wären.';
    } else {
        $subject = "Gemeinsam für Kirchseeon: Sponsoring-Chance für {$firmaText}";
        $intro = 'wir machen es wieder – und diesmal noch größer.';
        $absatz2 = 'Am <strong>20. September 2026</strong> startet der <strong>2. Marktlauf Kirchseeon</strong> '
            . 'auf dem Westring, gemeinsam mit der Gemeinde Kirchseeon im Rahmen des Energie- und Umwelttags. '
            . 'Beim ersten Marktlauf 2025 haben wir gezeigt, was in Kirchseeon steckt. 2026 wollen wir das ausbauen: '
            . '<strong>300 Läufer, rund 900 Gäste</strong> – Familien, Sportler, Nachbarinnen und Nachbarn aus der ganzen Region.';
        $absatz2Text = 'Am 20. September 2026 startet der 2. Marktlauf Kirchseeon auf dem Westring, gemeinsam mit der '
            . 'Gemeinde Kirchseeon im Rahmen des Energie- und Umwelttags. Beim ersten Marktlauf 2025 haben wir gezeigt, '
            . 'was in Kirchseeon steckt. 2026 wollen wir das ausbauen: 300 Läufer, rund 900 Gäste – Familien, Sportler, '
            . 'Nachbarinnen und Nachbarn aus der ganzen Region.';
    }

    $begruessungHtml = htmlspecialchars($begruessung);

    $htmlBody = <<<HTML
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <p>{$begruessungHtml}</p>
    <p>{$intro}</p>
    <p>{$absatz2}</p>
    <p>Gerade als lokales Unternehmen sind Sie hier mittendrin statt nur dabei: Ihre Kundinnen und Kunden laufen, jubeln oder schauen direkt vor Ihrer Haust&uuml;r zu. Ich w&uuml;rde mich sehr freuen, wenn Sie mit Ihrer Marke ein Teil davon sind.</p>
    {$paketTabelle}
    <p>{$levelText}</p>
    <p>Sachsponsoring (z.&nbsp;B. Verpflegung, Preise f&uuml;r die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit m&ouml;glich &ndash; einfach kurz melden.</p>
    <p><strong>R&uuml;ckmeldung erbeten bis zum 30. August 2026</strong> &ndash; so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.</p>
    <p>Ich freue mich auf Ihre R&uuml;ckmeldung und darauf, Sie am 20. September pers&ouml;nlich begr&uuml;&szlig;en zu d&uuml;rfen.</p>
    {$sigHtml}
</body>
</html>
HTML;

    $textBody = <<<TEXT
{$begruessung}

{$intro}

{$absatz2Text}

Gerade als lokales Unternehmen sind Sie hier mittendrin statt nur dabei: Ihre Kundinnen und Kunden laufen, jubeln oder schauen direkt vor Ihrer Haustür zu. Ich würde mich sehr freuen, wenn Sie mit Ihrer Marke ein Teil davon sind.

{$paketTextListe}

{$levelText}

Sachsponsoring (z. B. Verpflegung, Preise für die Siegerehrung) und individuelle Absprachen sind ebenfalls jederzeit möglich – einfach kurz melden.

Rückmeldung erbeten bis zum 30. August 2026 – so stellen wir sicher, dass Sie auf allen Druckmaterialien (Startnummern, Shirts) optimal platziert sind.

Ich freue mich auf Ihre Rückmeldung und darauf, Sie am 20. September persönlich begrüßen zu dürfen.

{$sigText}
TEXT;

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
