<?php

namespace App\Services;

use App\Models\EmailAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmtpMailer
{
    public function send(
        EmailAccount $account,
        string $to,
        string $subject,
        string $html,
        ?string $text = null,
    ): ?string {
        $messageId = $this->messageIdFor($account);

        $email = (new Email)
            ->from(new Address($account->email, $account->from_name ?: $account->email))
            ->to($to)
            ->subject($subject)
            ->html($html);

        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        if ($text !== null && $text !== '') {
            $email->text($text);
        }

        (new Mailer($this->transportFor($account)))->send($email);

        return $email->getHeaders()->get('Message-ID')?->getBodyAsString();
    }

    public function verify(EmailAccount $account): void
    {
        $transport = $this->transportFor($account);

        try {
            $transport->start();
        } finally {
            $transport->stop();
        }
    }

    protected function transportFor(EmailAccount $account): EsmtpTransport
    {
        $encryption = strtolower($account->smtp_encryption ?: EmailAccount::ENCRYPTION_STARTTLS);
        $directTls = $encryption === EmailAccount::ENCRYPTION_SSL;

        $transport = new EsmtpTransport(
            host: $account->smtp_host,
            port: (int) $account->smtp_port,
            tls: $directTls,
        );

        if ($encryption === EmailAccount::ENCRYPTION_NONE) {
            $transport->setAutoTls(false);
        } elseif ($encryption === EmailAccount::ENCRYPTION_STARTTLS) {
            $transport->setAutoTls(true);
            $transport->setRequireTls(true);
        } else {
            $transport->setAutoTls(false);
        }

        $transport->setUsername($account->smtp_username);
        $transport->setPassword($this->smtpPasswordFor($account));

        return $transport;
    }

    private function smtpPasswordFor(EmailAccount $account): string
    {
        try {
            return (string) $account->smtp_password;
        } catch (DecryptException) {
            throw new RuntimeException('The saved SMTP password could not be decrypted. Re-enter and save the SMTP password in Email Accounts.');
        }
    }

    private function messageIdFor(EmailAccount $account): string
    {
        $domain = substr(strrchr($account->email, '@') ?: '@localhost', 1) ?: 'localhost';

        return bin2hex(random_bytes(16)).'@'.$domain;
    }
}
