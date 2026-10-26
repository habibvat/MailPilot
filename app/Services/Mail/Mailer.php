<?php

namespace App\Services\Mail;

use App\Contracts\MailTransportFactory;
use App\Models\SmtpAccount;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie un email à un seul destinataire via le compte SMTP choisi.
 */
class Mailer
{
    public function __construct(private MailTransportFactory $transports) {}

    /**
     * @param  array<string,string>  $extraHeaders
     * @return string Message-ID (sans chevrons)
     */
    public function send(SmtpAccount $account, string $to, RenderedEmail $rendered, array $extraHeaders = []): string
    {
        $messageId = bin2hex(random_bytes(12)).'.mailpilot@'.(substr(strrchr($account->from_email, '@') ?: '@localhost', 1));

        $email = (new Email)
            ->from(new Address($account->from_email, (string) $account->from_name))
            ->to($to)
            ->subject($rendered->subject)
            ->html($rendered->html)
            ->text($rendered->text);

        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', $messageId);
        $headers->addTextHeader('List-Unsubscribe', '<mailto:'.$account->unsubscribeAddress().'?subject=unsubscribe>');
        foreach ($extraHeaders as $name => $value) {
            $headers->addTextHeader($name, $value);
        }

        $transport = $this->transports->make($account);
        try {
            $transport->send($email);
        } finally {
            if ($transport instanceof SmtpTransport) {
                $transport->stop();
            }
        }

        return $messageId;
    }

    /**
     * Ouvre puis ferme une session SMTP authentifiée.
     */
    public function testConnection(SmtpAccount $account): void
    {
        $transport = $this->transports->make($account);
        if ($transport instanceof SmtpTransport) {
            $transport->start();
            $transport->stop();
        }
    }
}
