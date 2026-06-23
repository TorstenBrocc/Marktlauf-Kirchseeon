<?php
/**
 * E-Mail-Versand via mail()
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function sendMail(string $to, string $subject, string $body): bool {
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

    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return mail($to, $subject, $body, $headerString);
}

function sendHelferEingangsbestaetigung(string $to, string $name): bool {
    $config = getConfig();
    $appUrl = $config['app']['url'] ?? 'https://atsv-kirchseeon-marktlauf.de';

    $subject = 'Anmeldung als Helfer eingegangen';
    $body = <<<TEXT
Hallo {$name},

vielen Dank für deine Anmeldung als Helfer beim ATSV Kirchseeon Marktlauf!

Deine Anmeldung ist bei uns eingegangen. Das Orga-Team wird sich bei dir melden, sobald wir die Einsatzplanung abgeschlossen haben.

Bei Fragen erreichst du uns unter: info@atsv-kirchseeon-marktlauf.de

Sportliche Grüße
Dein Marktlauf-Team

--
ATSV Kirchseeon Marktlauf
{$appUrl}
TEXT;

    return sendMail($to, $subject, $body);
}
