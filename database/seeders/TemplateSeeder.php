<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        Template::firstOrCreate(['name' => 'Offre de services (exemple)'], [
            'locale' => 'fr',
            'subject' => 'Une proposition pour {{entreprise}}',
            'body_html' => <<<'HTML'
<p>Bonjour,</p>
<p>Je me permets de vous contacter car nous accompagnons des entreprises du secteur <strong>{{secteur}}</strong> dans la région <strong>{{region}}</strong> et je pense que nos services pourraient intéresser <strong>{{entreprise}}</strong>.</p>
<p>Concrètement, nous vous proposons :</p>
<ul>
    <li>un audit gratuit de vos besoins, sans engagement ;</li>
    <li>une solution adaptée à votre activité et à votre budget ;</li>
    <li>un interlocuteur dédié et un suivi de proximité.</li>
</ul>
<p>Seriez-vous disponible pour un échange de 15 minutes cette semaine ou la semaine prochaine ? Il vous suffit de répondre à cet email en m'indiquant le créneau qui vous convient.</p>
<p>Bien cordialement,</p>
HTML,
            'body_text' => <<<'TEXT'
Bonjour,

Je me permets de vous contacter car nous accompagnons des entreprises du secteur {{secteur}} dans la région {{region}} et je pense que nos services pourraient intéresser {{entreprise}}.

Concrètement, nous vous proposons :
- un audit gratuit de vos besoins, sans engagement ;
- une solution adaptée à votre activité et à votre budget ;
- un interlocuteur dédié et un suivi de proximité.

Seriez-vous disponible pour un échange de 15 minutes cette semaine ou la semaine prochaine ? Il vous suffit de répondre à cet email en m'indiquant le créneau qui vous convient.

Bien cordialement,
TEXT,
        ]);

        Template::firstOrCreate(['name' => 'Service offer (example)'], [
            'locale' => 'en',
            'subject' => 'A proposal for {{company}}',
            'body_html' => <<<'HTML'
<p>Hello,</p>
<p>I am reaching out because we work with <strong>{{sector}}</strong> companies in <strong>{{region}}</strong>, and I believe our services could be of interest to <strong>{{company}}</strong>.</p>
<p>In practice, we offer:</p>
<ul>
    <li>a free, no-commitment review of your needs;</li>
    <li>a solution tailored to your business and budget;</li>
    <li>a dedicated contact person and close follow-up.</li>
</ul>
<p>Would you be available for a 15-minute call this week or next? Simply reply to this email with a time that suits you.</p>
<p>Best regards,</p>
HTML,
            'body_text' => <<<'TEXT'
Hello,

I am reaching out because we work with {{sector}} companies in {{region}}, and I believe our services could be of interest to {{company}}.

In practice, we offer:
- a free, no-commitment review of your needs;
- a solution tailored to your business and budget;
- a dedicated contact person and close follow-up.

Would you be available for a 15-minute call this week or next? Simply reply to this email with a time that suits you.

Best regards,
TEXT,
        ]);
    }
}
