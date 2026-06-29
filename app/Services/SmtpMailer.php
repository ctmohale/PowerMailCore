<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SmtpMailer
{
    private $socket = null;

    public function send(array $account, string $to, string $subject, string $html, ?string $text = null): string
    {
        $messageId = sprintf('%s@%s', bin2hex(random_bytes(16)), parse_url(config_value('app.url'), PHP_URL_HOST) ?: 'powermail.local');

        $this->connect($account);
        $this->command('MAIL FROM:<'.$account['email'].'>', [250]);
        $this->command('RCPT TO:<'.$to.'>', [250, 251]);
        $this->command('DATA', [354]);
        $this->write($this->buildMessage($account, $to, $subject, $html, $text, $messageId)."\r\n.");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        $this->close();

        return $messageId;
    }

    private function connect(array $account): void
    {
        $host = $account['smtp_host'];
        $port = (int) $account['smtp_port'];
        $encryption = strtolower((string) $account['smtp_encryption']);
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$port;

        $this->socket = @stream_socket_client($target, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);

        if (! $this->socket) {
            throw new RuntimeException("SMTP connection failed: {$errstr}");
        }

        stream_set_timeout($this->socket, 30);
        $this->expect([220]);
        $this->command('EHLO '.(parse_url(config_value('app.url'), PHP_URL_HOST) ?: 'localhost'), [250]);

        if ($encryption === 'starttls') {
            $this->command('STARTTLS', [220]);

            if (! stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed.');
            }

            $this->command('EHLO '.(parse_url(config_value('app.url'), PHP_URL_HOST) ?: 'localhost'), [250]);
        }

        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode((string) $account['smtp_username']), [334]);
        $this->command(base64_encode((string) decrypt_secret($account['smtp_password'])), [235]);
    }

    private function buildMessage(array $account, string $to, string $subject, string $html, ?string $text, string $messageId): string
    {
        $fromName = trim((string) ($account['from_name'] ?: $account['email']));
        $from = sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $account['email']);
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $boundary = 'pmc_'.bin2hex(random_bytes(12));
        $text = $text ?: trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));

        $headers = [
            'Date: '.date(DATE_RFC2822),
            'Message-ID: <'.$messageId.'>',
            'From: '.$from,
            'To: <'.$to.'>',
            'Subject: '.$encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];

        $body = [
            '--'.$boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($text),
            '--'.$boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($html),
            '--'.$boundary.'--',
        ];

        return implode("\r\n", $headers)."\r\n\r\n".$this->dotStuff(implode("\r\n", $body));
    }

    private function command(string $command, array $expected): string
    {
        $this->write($command);

        return $this->expect($expected);
    }

    private function write(string $line): void
    {
        if (! $this->socket) {
            throw new RuntimeException('SMTP socket is not connected.');
        }

        fwrite($this->socket, $line."\r\n");
    }

    private function expect(array $codes): string
    {
        if (! $this->socket) {
            throw new RuntimeException('SMTP socket is not connected.');
        }

        $response = '';

        do {
            $line = fgets($this->socket, 515);

            if ($line === false) {
                throw new RuntimeException('SMTP server stopped responding.');
            }

            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);

        if (! in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: '.trim($response));
        }

        return $response;
    }

    private function dotStuff(string $body): string
    {
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }
}
