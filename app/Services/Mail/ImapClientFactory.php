<?php

namespace App\Services\Mail;

use App\Models\SmtpAccount;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapClientFactory
{
    public function make(SmtpAccount $account): Client
    {
        $port = $account->imap_port ?: 993;

        return (new ClientManager)->make([
            'host' => $account->imap_host,
            'port' => $port,
            // 993 : TLS implicite, 143 : STARTTLS.
            'encryption' => $port === 143 ? 'starttls' : 'ssl',
            'validate_cert' => true,
            'username' => $account->imap_username,
            'password' => (string) $account->imap_password,
            'protocol' => 'imap',
            'timeout' => 30,
        ]);
    }

    /**
     * Se connecte et ouvre la boîte de réception ; renvoie le nombre de messages.
     */
    public function test(SmtpAccount $account): int
    {
        $client = $this->make($account);
        $client->connect();
        try {
            $folder = $client->getFolder('INBOX') ?? throw new \RuntimeException(__('Dossier INBOX introuvable.'));

            return (int) ($folder->examine()['exists'] ?? 0);
        } finally {
            $client->disconnect();
        }
    }
}
