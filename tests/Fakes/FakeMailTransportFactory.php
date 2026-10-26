<?php

namespace Tests\Fakes;

use App\Contracts\MailTransportFactory;
use App\Models\SmtpAccount;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Transport SMTP factice : enregistre les messages, ou échoue sur demande.
 */
class FakeMailTransportFactory implements MailTransportFactory
{
    /** @var list<Email> */
    public array $sent = [];

    /** Code SMTP à renvoyer en erreur (null = succès). */
    public ?int $failWithCode = null;

    public function make(SmtpAccount $account): TransportInterface
    {
        return new class($this) implements TransportInterface
        {
            public function __construct(private FakeMailTransportFactory $factory) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                if ($this->factory->failWithCode !== null) {
                    throw new TransportException('Erreur SMTP simulée', $this->factory->failWithCode);
                }
                $this->factory->sent[] = $message;

                return new SentMessage($message, $envelope ?? Envelope::create($message));
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_map(fn (Email $email) => $email->getTo()[0]->getAddress(), $this->sent);
    }
}
