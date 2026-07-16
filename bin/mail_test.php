#!/usr/bin/env php
<?php
/**
 * CLI-Tool: eine einzelne Testmail über sendMail() versenden.
 * Dient der Verifikation des Versandwegs inkl. BCC an info@ (siehe src/channels/mail.php).
 * Niemals als Web-Request laufen lassen.
 *
 * Aufruf (SSH):
 *   MARKTLAUF_CLI=1 php bin/mail_test.php empfaenger@example.de
 *
 * Der Empfänger bekommt die Mail im To, info@ als Blindkopie (BCC).
 * Wähle als Empfänger NICHT info@ selbst — sonst greift der To==Bcc-Dedup.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/channels/mail.php';

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nutzung: MARKTLAUF_CLI=1 php bin/mail_test.php empfaenger@example.de\n");
    exit(1);
}

$bcc = mailBccAddress();
$stamp = date('Y-m-d H:i:s');
$subject = "Testmail Marktlauf-Versand ({$stamp})";
$body = <<<TEXT
Dies ist eine Testmail zur Verifikation des Mail-Versands.

Empfänger (To): {$to}
Blindkopie (BCC): {$bcc}
Zeitstempel: {$stamp}

Wenn diese Mail im To-Postfach UND als Blindkopie im BCC-Postfach ankommt,
funktioniert der Versandweg inkl. BCC korrekt.
TEXT;

echo "Sende Testmail an {$to} (BCC: {$bcc}) …\n";

$mailer = getSmtpMailer();
if ($mailer === null) {
    echo "SMTP nicht konfiguriert — mail()-Fallback, keine BCC-Diagnose möglich.\n";
    $ok = sendMail($to, $subject, $body);
    echo $ok ? "✓ mail() meldet Erfolg.\n" : "✗ mail() fehlgeschlagen.\n";
    exit($ok ? 0 : 1);
}

$dedup = (strcasecmp($bcc, $to) === 0);
$ok = $mailer->send($to, $subject, $body, '', $dedup ? [] : [$bcc]);

echo $ok ? "✓ Hauptversand an {$to}: OK\n" : "✗ Hauptversand fehlgeschlagen.\n";
echo "--- BCC-Diagnose (SMTP-Ebene) ---\n";
if ($dedup) {
    echo "BCC == To ({$bcc}) → übersprungen (Dedup).\n";
} else {
    $report = $mailer->getBccReport();
    if (empty($report)) {
        echo "Kein BCC-RCPT ausgeführt.\n";
    }
    foreach ($report as $addr => $res) {
        echo "  {$addr}: {$res}\n";
    }
    echo "\nDeutung:\n";
    echo "  'accepted (250)' → Strato hat info@ akzeptiert; kommt es trotzdem nicht an,\n";
    echo "                     liegt es an Google (Spam/Routing/Split-Delivery).\n";
    echo "  'REJECTED: ...'  → Strato lehnt info@ ab (z.B. 550 no such user) →\n";
    echo "                     Domain-/Split-Delivery-Problem, kein Code-Fehler.\n";
}
if ($mailer->getLastError() !== null) {
    echo "lastError: " . $mailer->getLastError() . "\n";
}
exit($ok ? 0 : 1);
