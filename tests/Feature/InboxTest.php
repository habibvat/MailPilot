<?php

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\SmtpAccount;
use App\Models\Suppression;
use App\Models\Template;
use App\Services\Inbox\InboundMessage;
use App\Services\Inbox\InboundMessageHandler;

beforeEach(function () {
    $this->account = SmtpAccount::create(['label' => 'C', 'host' => 'h', 'port' => 587, 'encryption' => 'tls', 'from_name' => 'Moi', 'from_email' => 'moi@moi.dz', 'imap_host' => 'imap', 'imap_username' => 'moi@moi.dz']);
    $this->campaign = Campaign::create(['name' => 'C', 'template_id' => Template::create(['name' => 'T', 'subject' => 'S', 'body_html' => 'B'])->id, 'smtp_account_id' => $this->account->id, 'status' => 'running', 'send_days' => [1]]);
    $this->contact = Contact::create(['email' => 'client@client.dz', 'verify_status' => 'valid', 'last_contacted_at' => now()]);
    $this->send = CampaignSend::create(['campaign_id' => $this->campaign->id, 'contact_id' => $this->contact->id, 'status' => 'sent', 'message_id' => 'abc123.mailpilot@moi.dz', 'sent_at' => now()]);
    $this->handler = app(InboundMessageHandler::class);
});

it('détecte un rebond DSN et extrait l\'adresse en échec', function () {
    $dsn = "This is a multi-part message\n--b\nContent-Type: message/delivery-status\n\nReporting-MTA: dns; mx.client.dz\nFinal-Recipient: rfc822; client@client.dz\nAction: failed\nStatus: 5.1.1\n--b\nContent-Type: message/rfc822\n\nMessage-ID: <abc123.mailpilot@moi.dz>\nX-MailPilot-Send: {$this->send->id}\n";
    $result = $this->handler->handle(new InboundMessage(
        from: 'MAILER-DAEMON@mx.moi.dz',
        subject: 'Undelivered Mail Returned to Sender',
        text: 'Delivery failed',
        rawHeaders: 'Content-Type: multipart/report; report-type=delivery-status; boundary="b"',
        rawBody: $dsn,
    ), $this->account);

    expect($result)->toBe(['type' => 'bounce', 'email' => 'client@client.dz'])
        ->and($this->contact->fresh()->status)->toBe('bounced')
        ->and($this->send->fresh()->status)->toBe('bounced')
        ->and(Suppression::firstWhere('email', 'client@client.dz')->reason)->toBe('bounce');
});

it('détecte un rebond sans DSN standard grâce à X-Failed-Recipients', function () {
    $result = $this->handler->handle(new InboundMessage('postmaster@moi.dz', 'Delivery Status Notification (Failure)', 'Adresse introuvable', "X-Failed-Recipients: client@client.dz\n"), $this->account);

    expect($result['email'])->toBe('client@client.dz');
});

it('détecte une désinscription par le sujet ou le corps', function (string $subject, string $body) {
    $result = $this->handler->handle(new InboundMessage('Client@Client.dz', $subject, $body), $this->account);

    expect($result['type'])->toBe('unsubscribe')
        ->and($this->contact->fresh()->status)->toBe('unsubscribed')
        ->and(Suppression::firstWhere('email', 'client@client.dz')->reason)->toBe('unsubscribe');
})->with([
    ['Désinscription', ''],
    ['unsubscribe', ''],
    ['Re: votre offre', "STOP\n\nCordialement"],
    ['Re: votre offre', 'Merci de me désinscrire de votre liste.'],
]);

it('enregistre une réponse via In-Reply-To sans la confondre avec une désinscription citée', function () {
    $body = "Bonjour, nous sommes intéressés. Appelez-nous.\n\nLe lun. 2 nov. 2026 à 10:00, Moi <moi@moi.dz> a écrit :\n> Une proposition pour vous\n> Pour ne plus recevoir nos emails, répondez « STOP » ou écrivez avec l'objet « Désinscription ».";
    $result = $this->handler->handle(new InboundMessage('client@client.dz', 'Re: Une proposition', $body, referencedIds: ['abc123.mailpilot@moi.dz']), $this->account);

    expect($result['type'])->toBe('reply')
        ->and($this->send->fresh()->replied_at)->not->toBeNull()
        ->and($this->contact->fresh()->replied_at)->not->toBeNull()
        ->and($this->contact->fresh()->status)->toBe('active')
        ->and(Suppression::count())->toBe(0);
});

it('reconnaît une réponse par l\'adresse de l\'expéditeur', function () {
    expect($this->handler->handle(new InboundMessage('client@client.dz', 'Question', 'Quel est votre tarif ?'), $this->account)['type'])->toBe('reply');
    expect($this->handler->handle(new InboundMessage('inconnu@x.dz', 'Pub', 'Achetez'), $this->account)['type'])->toBe('other');
});

it('arrête la campagne si les rebonds reçus par email font dépasser le seuil', function () {
    foreach (range(1, 50) as $i) {
        $c = Contact::create(['email' => "c$i@x.dz", 'verify_status' => 'valid']);
        CampaignSend::create(['campaign_id' => $this->campaign->id, 'contact_id' => $c->id, 'status' => $i <= 2 ? 'bounced' : 'sent']);
    }
    $this->handler->handle(new InboundMessage('mailer-daemon@moi.dz', 'Undeliverable', '', rawBody: "Final-Recipient: rfc822; c3@x.dz\nFinal-Recipient: rfc822; c3@x.dz"), $this->account);
    $this->handler->handle(new InboundMessage('mailer-daemon@moi.dz', 'Undeliverable', '', rawBody: 'Final-Recipient: rfc822; c4@x.dz'), $this->account);

    expect($this->campaign->fresh()->status)->toBe('stopped');
});
