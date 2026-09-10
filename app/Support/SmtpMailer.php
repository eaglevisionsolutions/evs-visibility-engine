<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal SMTP client (EHLO/AUTH LOGIN/MAIL/RCPT/DATA over a raw socket).
 * No PHPMailer/Symfony Mailer dependency — CLAUDE.md's "no framework"
 * instinct, and the protocol dialog needed here is small and stable.
 * Supports implicit TLS (smtps, port 465) and STARTTLS (port 587).
 */
final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption, // 'tls' (STARTTLS), 'ssl' (implicit), or 'none'
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $transport = $this->encryption === 'ssl' ? 'ssl://' : '';
        $this->socket = @stream_socket_client(
            "{$transport}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            15,
        );

        if ($this->socket === false) {
            throw new RuntimeException("Could not connect to SMTP server: {$errstr} ({$errno})");
        }

        try {
            $this->expect('220');
            $this->command('EHLO ' . gethostname());

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS');
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $this->command('EHLO ' . gethostname());
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN');
                $this->command(base64_encode($this->username));
                $this->command(base64_encode($this->password));
            }

            $this->command("MAIL FROM:<{$this->fromEmail}>");
            $this->command("RCPT TO:<{$to}>");
            $this->command('DATA');

            $headers = implode("\r\n", [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "To: <{$to}>",
                "Subject: {$subject}",
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
            ]);

            $this->write($headers . "\r\n\r\n" . $body . "\r\n.");
            $this->expect('250');

            $this->command('QUIT');
        } finally {
            fclose($this->socket);
        }
    }

    private function command(string $line): void
    {
        $this->write($line);
        $this->expect(substr($line, 0, 4) === 'QUIT' ? '221' : null);
    }

    private function write(string $line): void
    {
        fwrite($this->socket, $line . "\r\n");
    }

    private function expect(?string $expectedCode): void
    {
        $response = '';

        do {
            $line = fgets($this->socket, 515);

            if ($line === false) {
                throw new RuntimeException('SMTP connection closed unexpectedly.');
            }

            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = substr($response, 0, 3);

        if ($expectedCode !== null && $code !== $expectedCode) {
            throw new RuntimeException("Unexpected SMTP response: {$response}");
        }

        if ($expectedCode === null && ($code[0] === '4' || $code[0] === '5')) {
            throw new RuntimeException("SMTP error response: {$response}");
        }
    }
}
