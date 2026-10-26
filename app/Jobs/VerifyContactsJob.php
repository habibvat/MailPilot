<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\EmailVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyContactsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  list<int>  $contactIds
     */
    public function __construct(public array $contactIds) {}

    public function handle(EmailVerifier $verifier): void
    {
        Contact::whereIn('id', $this->contactIds)->each(fn (Contact $contact) => $verifier->verifyContact($contact));
    }
}
