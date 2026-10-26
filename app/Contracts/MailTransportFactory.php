<?php

namespace App\Contracts;

use App\Models\SmtpAccount;
use Symfony\Component\Mailer\Transport\TransportInterface;

interface MailTransportFactory
{
    public function make(SmtpAccount $account): TransportInterface;
}
