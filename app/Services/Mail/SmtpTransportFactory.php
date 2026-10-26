<?php

namespace App\Services\Mail;

use App\Contracts\MailTransportFactory;
use App\Models\SmtpAccount;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

class SmtpTransportFactory implements MailTransportFactory
{
    public function make(SmtpAccount $account): TransportInterface
    {
        $transport = new EsmtpTransport($account->host, $account->port, $account->encryption === 'ssl');

        match ($account->encryption) {
            'tls' => $transport->setRequireTls(true),
            'none' => $transport->setAutoTls(false),
            default => null,
        };

        if (filled($account->username)) {
            $transport->setUsername($account->username);
            $transport->setPassword((string) $account->password);
        }

        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout(30);
        }

        return $transport;
    }
}
