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
$ok = sendMail($to, $subject, $body);
echo $ok ? "✓ Versand-Funktion meldet Erfolg.\n" : "✗ Versand fehlgeschlagen (siehe storage/logs).\n";
exit($ok ? 0 : 1);
