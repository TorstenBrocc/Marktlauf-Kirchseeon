<?php
/**
 * Native SMTP Mailer für Strato Shared Hosting
 * Kein Composer, keine externe Dependency.
 */

declare(strict_types=1);

class SmtpMailer
{
    private string $host;
    private int $port;
    private string $user;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private int $timeout = 30;
    private ?string $lastError = null;

    /** @var array<string,string> Diagnose: Ergebnis je BCC-RCPT (accepted / REJECTED: ...) */
    private array $bccReport = [];

    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->host = $config['smtp_host'] ?? '';
        $this->port = (int) ($config['smtp_port'] ?? 587);
        $this->user = $config['smtp_user'] ?? '';
        $this->password = $config['smtp_password'] ?? '';
        $this->fromAddress = $config['smtp_from'] ?? $this->user;
        $this->fromName = $config['smtp_from_name'] ?? '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** @return array<string,string> Ergebnis je BCC-RCPT (Diagnose). */
    public function getBccReport(): array
    {
        return $this->bccReport;
    }

    /**
     * @param string[] $bcc Blindkopie-Empfänger (kein Header, nur zusätzliches RCPT TO).
     *                       Ein fehlgeschlagenes BCC-RCPT bricht den Hauptversand NICHT ab.
     */
    public function send(string $to, string $subject, string $textBody, string $htmlBody = '', array $bcc = []): bool
    {
        $this->lastError = null;
        $this->bccReport = [];

        if (empty($this->host) || empty($this->user) || empty($this->password)) {
            $this->lastError = 'SMTP not configured';
            return false;
        }

        try {
            $this->connect();
            $this->ehlo();
            $this->startTls();
            $this->ehlo();
            $this->authenticate();
            $this->mailFrom();
            $this->rcptTo($to);
            foreach ($bcc as $bccAddr) {
                $bccAddr = trim((string) $bccAddr);
                if ($bccAddr === '' || strcasecmp($bccAddr, $to) === 0) {
                    continue; // leer oder identisch mit To -> keine Doppelzustellung
                }
                $accepted = $this->rcptToOptional($bccAddr);
                $this->bccReport[$bccAddr] = $accepted ? 'accepted (250)' : ('REJECTED: ' . ($this->lastError ?? '?'));
            }
            $this->data($to, $subject, $textBody, $htmlBody);
            $this->quit();
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->close();
            return false;
        }
    }

    private function connect(): void
    {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->expectCode(220);
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->sendCommand("EHLO $hostname");
        $this->expectCode(250, true);
    }

    private function startTls(): void
    {
        $this->sendCommand('STARTTLS');
        $this->expectCode(220);

        $crypto = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            throw new Exception('STARTTLS handshake failed');
        }
    }

    private function authenticate(): void
    {
        $this->sendCommand('AUTH LOGIN');
        $this->expectCode(334);

        $this->sendCommand(base64_encode($this->user));
        $this->expectCode(334);

        $this->sendCommand(base64_encode($this->password));
        $this->expectCode(235);
    }

    private function mailFrom(): void
    {
        $this->sendCommand("MAIL FROM:<{$this->fromAddress}>");
        $this->expectCode(250);
    }

    private function rcptTo(string $to): void
    {
        $this->sendCommand("RCPT TO:<$to>");
        $this->expectCode(250);
    }

    /**
     * RCPT TO für Blindkopien: lehnt der Server ab, wird das protokolliert,
     * der Versand an den Hauptempfänger läuft aber weiter.
     */
    private function rcptToOptional(string $to): bool
    {
        $this->sendCommand("RCPT TO:<$to>");
        try {
            $this->expectCode(250);
            return true;
        } catch (Exception $e) {
            $this->lastError = "BCC <$to> abgelehnt: " . $e->getMessage();
            return false;
        }
    }

    private function data(string $to, string $subject, string $textBody, string $htmlBody): void
    {
        $this->sendCommand('DATA');
        $this->expectCode(354);

        $message = $this->buildMessage($to, $subject, $textBody, $htmlBody);
        $this->sendCommand($message . "\r\n.");
        $this->expectCode(250);
    }

    private function quit(): void
    {
        $this->sendCommand('QUIT');
        $this->close();
    }

    private function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function sendCommand(string $command): void
    {
        if (!$this->socket) {
            throw new Exception('Not connected');
        }

        fwrite($this->socket, $command . "\r\n");
    }

    private function expectCode(int $expected, bool $multiline = false): string
    {
        $response = '';

        do {
            $line = fgets($this->socket, 512);
            if ($line === false) {
                throw new Exception('Connection lost');
            }
            $response .= $line;
            $continue = isset($line[3]) && $line[3] === '-';
        } while ($multiline && $continue);

        $code = (int) substr($response, 0, 3);
        if ($code !== $expected) {
            throw new Exception("Expected $expected, got $code: " . trim($response));
        }

        return $response;
    }

    private function buildMessage(string $to, string $subject, string $textBody, string $htmlBody): string
    {
        $boundary = '----=_Part_' . bin2hex(random_bytes(16));
        $date = date('r');
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . parse_url($this->host, PHP_URL_HOST) . '>';

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = $this->fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?='
            : '';

        $from = $encodedFromName !== ''
            ? "$encodedFromName <{$this->fromAddress}>"
            : $this->fromAddress;

        $headers = [
            "Date: $date",
            "From: $from",
            "To: $to",
            "Subject: $encodedSubject",
            "Message-ID: $messageId",
            'MIME-Version: 1.0',
        ];

        if ($htmlBody !== '') {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
            $body = $this->buildMultipartBody($textBody, $htmlBody, $boundary);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($textBody));
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function buildMultipartBody(string $textBody, string $htmlBody, string $boundary): string
    {
        $parts = [];

        $parts[] = "--$boundary";
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($textBody));

        $parts[] = "--$boundary";
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($htmlBody));

        $parts[] = "--$boundary--";

        return implode("\r\n", $parts);
    }
}

function getSmtpMailer(): ?SmtpMailer
{
    $config = getConfig();

    if (empty($config['smtp_host'])) {
        return null;
    }

    return new SmtpMailer($config);
}
