<?php

namespace App\Services;

use App\Models\EmailAccount;
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
        $email = (new Email)
            ->from(new Address($account->email, $account->from_name ?: $account->email))
            ->to($to)
            ->subject($subject)
            ->html($html);

        if ($text !== null && $text !== '') {
            $email->text($text);
        }

        (new Mailer($this->transportFor($account)))->send($email);

        $messageId = $email->getHeaders()->get('Message-ID');

        return $messageId?->getBodyAsString();
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
        $transport->setPassword($account->smtp_password);

        return $transport;
    }
}
