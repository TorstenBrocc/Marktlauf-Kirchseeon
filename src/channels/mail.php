<?php
/**
 * E-Mail-Versand via SMTP (Strato) mit Fallback auf mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../sponsor_brief.php';
require_once __DIR__ . '/../sponsor_anhaenge.php';
require_once __DIR__ . '/../verein_brief.php';
require_once __DIR__ . '/../google_drive.php';
require_once __DIR__ . '/../offene_todos.php';  // todoTelefonHref() für den ToDo-Digest

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
function sendMail(string $to, string $subject, string $textBody, string $htmlBody = '', array $attachments = [], array $extraBcc = [], array $cc = []): bool {
    $bccAddr = mailBccAddress();
    $bcc = ($bccAddr !== '' && strcasecmp($bccAddr, $to) !== 0) ? [$bccAddr] : [];
    // Zusätzliche BCC-Empfänger — dedupliziert, nie an $to.
    foreach ($extraBcc as $addr) {
        $addr = trim((string) $addr);
        if ($addr !== '' && strcasecmp($addr, $to) !== 0
            && !in_array(strtolower($addr), array_map('strtolower', $bcc), true)) {
            $bcc[] = $addr;
        }
    }
    // Sichtbare Kopie (z. B. kassier@ bei Rechnungen): nie an $to, nie doppelt zu einem BCC.
    $ccListe = [];
    foreach ($cc as $addr) {
        $addr = trim((string) $addr);
        if ($addr === '' || strcasecmp($addr, $to) === 0) {
            continue;
        }
        if (in_array(strtolower($addr), array_map('strtolower', $bcc), true)
            || in_array(strtolower($addr), array_map('strtolower', $ccListe), true)) {
            continue;
        }
        $ccListe[] = $addr;
    }

    $mailer = getSmtpMailer();

    if ($mailer !== null) {
        $result = $mailer->send($to, $subject, $textBody, $htmlBody, $bcc, $attachments, $ccListe);
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

    if (!empty($ccListe)) {
        $headers['Cc'] = implode(', ', $ccListe);
    }
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
    array $excludePlakatFids = [],
    int $sponsorId = 0
): bool {
    if (!sponsorBriefSlugValid($typ)) {
        $typ = 'erstanschreiben';
    }
    if ($userId === 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    $pdo         = getDbConnection();
    $vorlage     = sponsorBriefLoad($pdo, $typ, $userId);
    $ctx         = sponsorBriefContext($pdo, $userId, $anrede, $vorname, $nachname, $firma, $paket, $sponsorId);
    $subject     = sponsorBriefBetreff($vorlage['betreff'], $ctx);
    $htmlBody    = sponsorBriefRenderHtml($vorlage['koerper_md'], $ctx);
    $textBody    = sponsorBriefRenderText($vorlage['koerper_md'], $ctx);
    // Welche Anhänge an welche Vorlage gehören, steht NICHT mehr hier, sondern zentral in
    // sponsorAnhangPlan() — dieselbe Quelle, aus der die Anhang-Kachel auf der Versandseite
    // ihre Liste zeichnet. Damit kann die Kachel nichts anderes behaupten als hier passiert.
    // Die Sponsoring-Bedingungen sind Haus-Konvention: das Dokument liegt im Drive-Ordner
    // (vom Team gepflegt), das System hängt es an, es wird nicht systemseitig erzeugt.
    // Abwahl (Opt-out je Datei) greift überall dort, wo der Plan die Gruppe nicht als 'fest'
    // führt — also bei den Plakaten (Freier Brief, Bestätigung) und den Bestätigungs-Assets.
    $attachments = [];
    foreach (sponsorAnhangPlan($typ) as $gruppe) {
        $exclude = [];
        if (!$gruppe['fest']) {
            $exclude = $gruppe['id'] === 'asset' ? $excludeAssetFids : $excludePlakatFids;
        }
        $attachments = array_merge($attachments, match ($gruppe['quelle']) {
            'plakate'             => plakateAnhang($pdo, $exclude),
            'bestaetigung_assets' => bestaetigungAssetsAnhang($pdo, $exclude),
            'bedingungen'         => sponsorBedingungenAnhang($pdo),
            default               => [],
        });
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

/**
 * Überblick „Offene ToDos Sponsoring" als HTML-Mail.
 *
 * Spiegelt bewusst 1:1 das Design von orga/offene_todos.php (Bauvorlage .hd-card /
 * .hd-table aus css/orga.css): weiße Karte je Gruppe, großer dunkler Titel mit grauer
 * Zählpille, Beschreibungszeile, Tabelle mit grauem Kopf und den Spalten
 * Firma / Info / Status–Frist / Kontakt. Fehler-Gruppen haben einen roten Titel, sonst
 * dieselbe Karte.
 *
 * Warum Tabellen und Inline-Styles: Mailprogramme können weder Grid noch Flexbox noch
 * <style>-Blöcke verlässlich. Der Kasten-Schatten der Seite wird zu einem 1px-Rahmen,
 * weil box-shadow in vielen Clients wegfällt.
 *
 * Die Farb- und Größenwerte sind die aufgelösten Tokens aus css/orga.css (--text #333,
 * --text-light #666, --bg #f5f5f5, --border #ddd, --link #007230, --error #d32f2f).
 * CSS-Variablen funktionieren in Mails nicht, deshalb hier als Literale — ändert sich
 * ein Token, gehört es hier nachgezogen.
 *
 * @param array<int,array{titel:string, sub?:string, ist_fehler?:bool, kopf:array<int,string>,
 *            zeilen:array<int,array{zellen?:array<int,array{t:string,k:string}>, mehr?:string}>}> $gruppen
 */
function sendOffeneTodosDigest(string $to, string $name, int $gesamt, array $gruppen, string $modus = 'voll'): bool {
    $subject = $modus === 'neu'
        ? '🔔 Neu heute: ' . $gesamt . ' offene ToDos Sponsoring – Marktlauf'
        : '🔔 Offene ToDos Sponsoring (' . $gesamt . ') – Marktlauf';

    $anrede = trim($name) !== '' ? 'Hallo ' . $name . ',' : 'Hallo,';
    $einleitung = $modus === 'neu'
        ? 'heute sind ' . $gesamt . ' Punkte dazugekommen, für die du zuständig bist:'
        : 'diese ' . $gesamt . ' Punkte liegen bei dir offen:';
    $seite = 'https://atsv-kirchseeon-marktlauf.de/orga/offene_todos.php';

    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    // Zellen-Styles je Sorte — Gegenstück zu .hd-firma/.hd-info/.hd-status/.hd-kontakt.
    $zellStil = [
        'firma'   => 'font-weight:600',
        'info'    => 'color:#666666;font-style:italic',
        'status'  => 'color:#666666',
        'kontakt' => 'white-space:nowrap',
        'plain'   => '',
    ];

    // ---- Text-Fassung (Fallback) ----
    $text = $anrede . "\n\n" . $einleitung . "\n";
    foreach ($gruppen as $g) {
        if (empty($g['zeilen'])) {
            continue;
        }
        $text .= "\n" . $g['titel'] . "\n";
        foreach ($g['zeilen'] as $z) {
            if (isset($z['mehr'])) {
                $text .= '  ' . $z['mehr'] . "\n";
                continue;
            }
            $werte = [];
            foreach ($z['zellen'] as $zelle) {
                if (trim($zelle['t']) !== '') {
                    $werte[] = trim($zelle['t']);
                }
            }
            $text .= '  - ' . implode(' · ', $werte) . "\n";
        }
    }
    $text .= "\nAlles auf einen Blick: " . $seite
        . "\n\nFragen? info@atsv-kirchseeon-marktlauf.de\n\nSportliche Grüße\nDein Marktlauf-Team";

    // ---- HTML-Fassung ----
    $html = '<div style="font:14px/1.5 -apple-system,\'Segoe UI\',Helvetica,Arial,sans-serif;'
          . 'color:#333333;background:#f5f5f5;padding:16px">'
          . '<div style="max-width:660px;margin:0 auto">'
          . '<p style="margin:0 0 12px 0">' . $e($anrede) . '</p>'
          . '<p style="margin:0 0 18px 0">' . $e($einleitung) . '</p>';

    foreach ($gruppen as $g) {
        if (empty($g['zeilen'])) {
            continue;
        }
        $titelFarbe = !empty($g['ist_fehler']) ? '#d32f2f' : '#333333';
        $anzahl = count(array_filter($g['zeilen'], static fn (array $z): bool => !isset($z['mehr'])));

        $html .= '<div style="background:#ffffff;border:1px solid #dddddd;border-radius:8px;'
               . 'padding:16px;margin:0 0 16px 0">'
               . '<h2 style="font-size:20px;font-weight:700;color:' . $titelFarbe . ';margin:0 0 6px 0">'
               . $e($g['titel'])
               . ' <span style="font-size:11px;font-weight:700;color:#666666;background:#f5f5f5;'
               . 'border-radius:999px;padding:2px 8px;vertical-align:middle">' . $anzahl . '</span></h2>';

        if (trim((string) ($g['sub'] ?? '')) !== '') {
            $html .= '<p style="font-size:12px;color:#666666;margin:0 0 12px 0">' . $e((string) $g['sub']) . '</p>';
        }

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"'
               . ' style="border-collapse:collapse;width:100%"><tr>';
        foreach ($g['kopf'] as $spalte) {
            $html .= '<th align="left" style="background:#f5f5f5;font-size:11px;text-transform:uppercase;'
                   . 'letter-spacing:0.05em;color:#666666;font-weight:700;padding:7px 10px;'
                   . 'border-bottom:1px solid #dddddd">' . $e($spalte) . '</th>';
        }
        $html .= '</tr>';

        $letzter = count($g['zeilen']) - 1;
        foreach ($g['zeilen'] as $i => $z) {
            $u = $i === $letzter ? '0' : '1px solid #dddddd';
            if (isset($z['mehr'])) {
                $html .= '<tr><td colspan="' . count($g['kopf']) . '" style="padding:8px 10px;'
                       . 'font-size:12px;color:#666666;border-bottom:' . $u . '">'
                       . $e($z['mehr']) . '</td></tr>';
                continue;
            }
            $html .= '<tr>';
            foreach ($z['zellen'] as $zelle) {
                $inhalt = $zelle['k'] === 'kontakt'
                    ? todoKontaktMailZelle($zelle['t'])
                    : $e($zelle['t']);
                $html .= '<td style="padding:8px 10px;font-size:14px;vertical-align:top;border-bottom:' . $u . ';'
                       . $zellStil[$zelle['k']] . '">' . $inhalt . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></div>';
    }

    $social = marktlaufSocialLinks();
    $html .= '<p style="margin:4px 0 0 0"><a href="' . $seite . '"'
           . ' style="display:inline-block;background:#007230;color:#ffffff;text-decoration:none;'
           . 'padding:10px 16px;border-radius:8px;font-weight:600">Offene ToDos öffnen</a></p>'
           . '<p style="margin:14px 0 0 0;color:#666666;font-size:12px">'
           . 'Fragen? info@atsv-kirchseeon-marktlauf.de</p>'
           . '<div style="margin-top:16px">' . $social['html'] . '</div>'
           . '</div></div>';

    return sendMail($to, $subject, $text . "\n\n" . $social['text'], $html);
}

/**
 * Kontakt-Zelle der Mail: Telefonnummer als tel:-Link, Mailadresse als ✉.
 * Erwartet "telefon|email" — die Zusammensetzung passiert im Digest, damit hier keine
 * zweite Formatierungslogik entsteht.
 */
function todoKontaktMailZelle(string $wert): string {
    [$tel, $mail] = array_pad(explode('|', $wert, 2), 2, '');
    $tel = trim($tel);
    $mail = trim($mail);
    $teile = [];
    if ($tel !== '') {
        $teile[] = '<a href="tel:' . htmlspecialchars(todoTelefonHref($tel), ENT_QUOTES, 'UTF-8') . '"'
                 . ' style="color:#007230;text-decoration:none;white-space:nowrap">'
                 . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    if ($mail !== '') {
        $teile[] = '<a href="mailto:' . htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') . '"'
                 . ' style="color:#007230;text-decoration:none">✉&nbsp;Mail</a>';
    }
    return $teile === [] ? '' : implode('<br>', $teile);
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
