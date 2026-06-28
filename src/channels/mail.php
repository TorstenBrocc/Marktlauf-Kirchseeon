<?php
/**
 * E-Mail-Versand via SMTP (Strato) mit Fallback auf mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer.php';

function sendMail(string $to, string $subject, string $textBody, string $htmlBody = ''): bool {
    $mailer = getSmtpMailer();

    if ($mailer !== null) {
        $result = $mailer->send($to, $subject, $textBody, $htmlBody);
        if (!$result) {
            error_log('SMTP error: ' . $mailer->getLastError(), 3, __DIR__ . '/../../storage/logs/php_errors.log');
        }
        return $result;
    }

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
