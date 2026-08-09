<?php
/**
 * E-Mail-Versand via SMTP (Strato) mit Fallback auf mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../sponsor_brief.php';
require_once __DIR__ . '/../verein_brief.php';
require_once __DIR__ . '/../google_drive.php';

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

/**
 * @param array<array{path:string,name:string,mime:string}> $attachments Dateianhänge.
 */
function sendMail(string $to, string $subject, string $textBody, string $htmlBody = '', array $attachments = [], array $extraBcc = []): bool {
    $bccAddr = mailBccAddress();
    $bcc = ($bccAddr !== '' && strcasecmp($bccAddr, $to) !== 0) ? [$bccAddr] : [];
    // Zusätzliche BCC-Empfänger (z. B. kassier@ bei Rechnungen) — dedupliziert, nie an $to.
    foreach ($extraBcc as $addr) {
        $addr = trim((string) $addr);
        if ($addr !== '' && strcasecmp($addr, $to) !== 0
            && !in_array(strtolower($addr), array_map('strtolower', $bcc), true)) {
            $bcc[] = $addr;
        }
    }

    $mailer = getSmtpMailer();

    if ($mailer !== null) {
        $result = $mailer->send($to, $subject, $textBody, $htmlBody, $bcc, $attachments);
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

/**
 * Ergänzt eine reine Text-Mail um einen HTML-Teil, damit die Social-Logos
 * genauso erscheinen wie in den Anschreiben-Signaturen.
 *
 * Hintergrund: die Transaktions-Mails unten waren text/plain ohne HTML-Teil,
 * und Logos brauchen zwingend HTML. Statt fünf HTML-Vorlagen zu pflegen wird
 * hier derselbe Text in HTML überführt — so bleibt der Wortlaut an einer
 * Stelle und kann nicht auseinanderlaufen.
 *
 * Die Text-Fassung bekommt die URLs ausgeschrieben, weil sendMail() ohne SMTP
 * auf text/plain zurückfällt und den HTML-Teil dann verwirft.
 *
 * Reihenfolge beim HTML: erst escapen, dann verlinken. Umgekehrt würde
 * htmlspecialchars die gerade erzeugten Tags wieder zerlegen.
 */
function marktlaufMailBody(string $text): array {
    $social = marktlaufSocialLinks();

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Satzzeichen am Ende nicht mit in den Link ziehen
    $linked = preg_replace(
        '~(https?://[^\s<]*[^\s<.,;:!?)])~',
        '<a href="$1" style="color:#009640">$1</a>',
        $escaped
    );

    $html = '<div style="font:14px/1.6 -apple-system,\'Segoe UI\',Helvetica,Arial,sans-serif;color:#222222">'
        . nl2br($linked, false)
        . '<div style="margin-top:16px">' . $social['html'] . '</div>'
        . '</div>';

    return [
        'text' => rtrim($text) . "\n\n" . $social['text'],
        'html' => $html,
    ];
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

    $mail = marktlaufMailBody($body);
    return sendMail($to, $subject, $mail['text'], $mail['html']);
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

    $mail = marktlaufMailBody($body);
    return sendMail($to, $subject, $mail['text'], $mail['html']);
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

    $mail = marktlaufMailBody($body);
    return sendMail($to, $subject, $mail['text'], $mail['html']);
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

    $mail = marktlaufMailBody($body);
    return sendMail($to, $subject, $mail['text'], $mail['html']);
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
    int $userId = 0,
    array $excludeAssetFids = [],
    array $excludePlakatFids = []
): bool {
    if (!sponsorBriefSlugValid($typ)) {
        $typ = 'erstanschreiben';
    }
    if ($userId === 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    $pdo         = getDbConnection();
    $vorlage     = sponsorBriefLoad($pdo, $typ, $userId);
    $ctx         = sponsorBriefContext($pdo, $userId, $anrede, $vorname, $nachname, $firma, $paket);
    $subject     = sponsorBriefBetreff($vorlage['betreff'], $ctx);
    $htmlBody    = sponsorBriefRenderHtml($vorlage['koerper_md'], $ctx);
    $textBody    = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);
    // Freier Brief + Bestätigung: aktuelle Plakate anhängen (Abschnitt „Plakate anbei").
    // Zusätzlich hängt die Bestätigung den designierten Bestätigungs-Anhang-Ordner an
    // (Absperrgitter-Bemaßungen etc.) — mit stateless Opt-out über $excludeAssetFids.
    // Plakat-Abwahl greift nur bei der Bestätigung; der freie Brief hängt weiterhin alle an.
    $attachments = [];
    if (in_array($typ, ['frei', 'bestaetigung'], true)) {
        $attachments = plakateAnhang($pdo, $typ === 'bestaetigung' ? $excludePlakatFids : []);
    }
    if ($typ === 'bestaetigung') {
        $attachments = array_merge($attachments, bestaetigungAssetsAnhang($pdo, $excludeAssetFids));
    }
    // Sponsoring-Bedingungen aus dem Drive-Ordner „_assets Sponsoren" anhängen — Haus-Konvention:
    // Das Dokument liegt im Ordner (vom Team gepflegt), das System hängt es an; es wird NICHT
    // systemseitig erzeugt. Greift bei Erstansprache/Folgejahr/Bestätigung/Nachreich-Mail; vom
    // freien Brief bewusst ausgenommen. Nicht abwählbar.
    if (in_array($typ, ['erstanschreiben', 'folgejahr', 'bestaetigung', 'bedingungen'], true)) {
        $attachments = array_merge($attachments, sponsorBedingungenAnhang($pdo));
    }
    return sendMail($to, $subject, $textBody, $htmlBody, $attachments);
}

/**
 * Aktuelle Plakat-PDFs aus dem designierten Plakate-Ordner als Anhang-Array laden.
 * $excludeFids: vom Versender abgewählte Drive-Datei-IDs (nur Bestätigungs-Versand; sonst []).
 * @param array<int,string> $excludeFids
 * @return array<array{path:string,name:string,mime:string}>
 */
function plakateAnhang(PDO $pdo, array $excludeFids = []): array {
    if (!driveConfigured()) {
        return [];
    }
    $jahr     = driveRennJahr($pdo);
    $folderId = drivePlakatFolderId($pdo, $jahr);
    if ($folderId === null) {
        logError('plakateAnhang: kein Plakate-Ordner für Renn-Jahr ' . $jahr . ' festgelegt');
        return [];
    }
    return driveFolderAnhang($folderId, 'plakat', $excludeFids, 'application/pdf');
}

/**
 * Dateien aus dem designierten Bestätigungs-Anhang-Ordner als Anhang-Array laden.
 * Opt-out: $excludeFids listet vom Versender abgewählte Drive-Datei-IDs (stateless, pro Versand).
 * @param array<int,string> $excludeFids
 * @return array<array{path:string,name:string,mime:string}>
 */
function bestaetigungAssetsAnhang(PDO $pdo, array $excludeFids = []): array {
    if (!driveConfigured()) {
        return [];
    }
    $folderId = driveBestaetigungAssetsFolderId($pdo);
    if ($folderId === null) {
        logError('bestaetigungAssetsAnhang: kein Bestätigungs-Anhang-Ordner festgelegt');
        return [];
    }
    return driveFolderAnhang($folderId, 'bestaetigung', $excludeFids, 'application/octet-stream');
}

/**
 * Sponsoring-Bedingungen aus dem Drive-Ordner „_assets Sponsoren" (unter Orga/Sponsoren) als
 * pfadbasierte Anhänge. Angehängt werden nur Dateien mit „Bedingung" im Namen — so bleiben andere
 * Assets im Ordner unberührt. Das Dokument wird NICHT systemseitig erzeugt (Haus-Konvention); die
 * Vorlage zum Ablegen liefert `src/sponsor_bedingungen_pdf.php` (Download im Briefvorlagen-Editor).
 * @return array<array{path:string,name:string,mime:string}>
 */
function sponsorBedingungenAnhang(PDO $pdo): array {
    if (!driveConfigured()) {
        return [];
    }
    require_once __DIR__ . '/../sponsor_rotation.php';
    $folderId = driveFindFolder('_assets Sponsoren', sponsorDriveRootId($pdo));
    if ($folderId === null) {
        logError('sponsorBedingungenAnhang: Ordner „_assets Sponsoren" nicht gefunden');
        return [];
    }
    try {
        $files = driveListChildren($folderId);
    } catch (RuntimeException $e) {
        logError('sponsorBedingungenAnhang list: ' . $e->getMessage());
        return [];
    }
    $result = [];
    foreach ($files as $f) {
        if ($f['isFolder'] || $f['id'] === '' || stripos($f['name'], 'bedingung') === false) {
            continue;
        }
        try {
            $bytes = driveDownload($f['id']);
        } catch (RuntimeException $e) {
            logError('sponsorBedingungenAnhang download: ' . $e->getMessage());
            continue;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'spbed_');
        if ($tmp === false) {
            continue;
        }
        file_put_contents($tmp, $bytes);
        register_shutdown_function(static function () use ($tmp) {
            @unlink($tmp);
        });
        $result[] = [
            'path' => $tmp,
            'name' => $f['name'],
            'mime' => $f['mimeType'] !== '' ? $f['mimeType'] : 'application/pdf',
        ];
    }
    return $result;
}

/**
 * Alle Dateien eines Drive-Ordners in pfadbasierte Anhänge verwandeln (Bytes -> Temp-Datei,
 * via Shutdown-Hook aufgeräumt, damit der pfadbasierte SMTP-Mailer unverändert bleibt).
 * Ordner-Einträge werden übersprungen; $excludeFids filtert abgewählte Datei-IDs heraus.
 * @param array<int,string> $excludeFids
 * @return array<array{path:string,name:string,mime:string}>
 */
function driveFolderAnhang(string $folderId, string $tmpPrefix, array $excludeFids, string $mimeFallback): array {
    try {
        $files = driveListChildren($folderId);
    } catch (RuntimeException $e) {
        logError($tmpPrefix . 'Anhang list: ' . $e->getMessage());
        return [];
    }
    $exclude = array_flip(array_map('strval', $excludeFids));
    $result  = [];
    foreach ($files as $f) {
        if ($f['isFolder'] || $f['id'] === '' || isset($exclude[$f['id']])) {
            continue;
        }
        try {
            $bytes = driveDownload($f['id']);
        } catch (RuntimeException $e) {
            logError($tmpPrefix . 'Anhang download: ' . $e->getMessage());
            continue;
        }
        $tmp = tempnam(sys_get_temp_dir(), $tmpPrefix . '_');
        if ($tmp === false) {
            continue;
        }
        file_put_contents($tmp, $bytes);
        register_shutdown_function(static function () use ($tmp) {
            @unlink($tmp);
        });
        $result[] = [
            'path' => $tmp,
            'name' => $f['name'],
            'mime' => $f['mimeType'] !== '' ? $f['mimeType'] : $mimeFallback,
        ];
    }
    return $result;
}

/**
 * Vereins-/Laufevent-Anschreiben versenden (nativer SMTP-Mailer, HTML + Text).
 *
 * Inhalt (Betreff + Körper) stammt aus der editierbaren Vorlage
 * verein_briefvorlagen bzw. – solange dort nichts gespeichert ist – aus dem
 * Code-Default (vereinBriefDefaults). Rendering nutzt bewusst die generischen,
 * abgesicherten Renderer der Sponsoren (sponsorBriefRenderHtml/Text).
 *
 * $kategorie ∈ verein | laufevent   (bestimmt Anrede-Ton)
 * $typ       ∈ verein | laufevent   (Vorlagen-Slug; Default = $kategorie)
 */
function sendVereinAnschreiben(
    string $to,
    string $anrede,
    string $vorname,
    string $nachname,
    string $name,
    string $kategorie = 'verein',
    string $typ = '',
    int $userId = 0
): bool {
    if ($kategorie !== 'laufevent') {
        $kategorie = 'verein';
    }
    if (!vereinBriefSlugValid($typ)) {
        $typ = $kategorie;
    }
    if ($userId === 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    $pdo         = getDbConnection();
    $vorlage     = vereinBriefLoad($pdo, $typ);
    $ctx         = vereinBriefContext($pdo, $userId, $kategorie, $anrede, $vorname, $nachname, $name);
    $subject     = sponsorBriefBetreff($vorlage['betreff'], $ctx);
    $htmlBody    = sponsorBriefRenderHtml($vorlage['koerper_md'], $ctx);
    $textBody    = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);
    $attachments = plakateAnhang($pdo);
    return sendMail($to, $subject, $textBody, $htmlBody, $attachments);
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

    $mail = marktlaufMailBody($body);
    return sendMail($to, $subject, $mail['text'], $mail['html']);
}
