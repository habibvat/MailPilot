<?php

namespace App\Services\Mail;

use App\Models\Contact;
use App\Models\Setting;
use App\Models\SmtpAccount;
use App\Models\Template;

/**
 * Remplace les variables ({{entreprise}} ou {{company}}, {{secteur}} ou {{sector}}, {{region}}, {{email}})
 * et ajoute le pied de page légal, dans la langue du template.
 */
class TemplateRenderer
{
    /** Valeur utilisée quand le champ du contact est vide, par langue (textes traduits via lang/*.json). */
    private const FALLBACKS = [
        'company_name' => 'votre entreprise',
        'sector' => 'votre secteur',
        'wilaya' => 'votre région',
        'email' => '',
    ];

    public function render(Template $template, Contact $contact, SmtpAccount $account): RenderedEmail
    {
        return $this->renderParts($template->subject, $template->body_html, $template->body_text, $contact, $account, $template->locale);
    }

    public function renderParts(string $subject, string $html, ?string $text, Contact $contact, SmtpAccount $account, string $locale = 'fr'): RenderedEmail
    {
        $values = $this->values($contact, $locale);
        $unsubscribe = $this->unsubscribeLink($account, $locale);

        $text = filled($text) ? $text : $this->htmlToText($html);

        return new RenderedEmail(
            subject: trim(preg_replace('/\s+/', ' ', $this->replace($subject, $values))),
            html: $this->wrapHtml($this->replace($html, array_map('e', $values)), $this->footerHtml($unsubscribe, $locale), $locale),
            text: rtrim($this->replace($text, $values))."\n\n".$this->footerText($account, $locale),
        );
    }

    /**
     * @return array<string,string> champ du contact => valeur
     */
    public function values(Contact $contact, string $locale = 'fr'): array
    {
        $values = [];
        foreach (self::FALLBACKS as $field => $fallback) {
            $value = (string) $contact->{$field};
            $values[$field] = trim($value) === '' ? ($fallback === '' ? '' : __($fallback, [], $locale)) : $value;
        }

        return $values;
    }

    public function unsubscribeLink(SmtpAccount $account, string $locale = 'fr'): string
    {
        return 'mailto:'.$account->unsubscribeAddress().'?subject='.rawurlencode(__('Désinscription', [], $locale));
    }

    public function footerHtml(string $unsubscribeLink, string $locale = 'fr'): string
    {
        $link = '<a href="'.e($unsubscribeLink).'" style="color:#475569">'.e(__('cliquez ici pour vous désinscrire', [], $locale)).'</a>';

        return '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.5;color:#64748b">'
            .(($identity = $this->identity($locale, escape: true)) ? '<p style="margin:0 0 6px">'.$identity.'</p>' : '')
            .'<p style="margin:0">'.e(__('Vous recevez ce message car votre adresse professionnelle est publiquement associée à votre entreprise.', [], $locale)).' '
            .str_replace(':link', $link, e(__('Pour ne plus recevoir nos emails, :link ou répondez « STOP ».', [], $locale))).'</p></div>';
    }

    public function footerText(SmtpAccount $account, string $locale = 'fr'): string
    {
        return "--\n"
            .(($identity = $this->identity($locale, escape: false)) ? $identity."\n" : '')
            .__('Pour ne plus recevoir nos emails, répondez « STOP » ou écrivez à :address avec l\'objet « :subject ».', [
                'address' => $account->unsubscribeAddress(),
                'subject' => __('Désinscription', [], $locale),
            ], $locale);
    }

    private function identity(string $locale, bool $escape): string
    {
        $phone = Setting::get('legal_phone');
        $parts = array_filter([
            Setting::get('legal_company'),
            Setting::get('legal_address'),
            filled($phone) ? __('Tél. : :phone', ['phone' => $phone], $locale) : null,
        ]);

        return implode(' · ', $escape ? array_map('e', $parts) : $parts);
    }

    public function htmlToText(string $html): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $text = preg_replace('/<li\b[^>]*>/i', '- ', $text);
        $text = preg_replace('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $text);
        $text = preg_replace('/<br\s*\/?>|<\/(p|div|li|h[1-6]|tr|ul|ol)>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private function replace(string $content, array $values): string
    {
        $names = implode('|', array_map('preg_quote', array_keys(Template::ALIASES)));

        return preg_replace_callback('/\{\{\s*('.$names.')\s*\}\}/iu', fn ($m) => $values[Template::ALIASES[mb_strtolower($m[1])]], $content);
    }

    private function wrapHtml(string $body, string $footer, string $locale): string
    {
        if (preg_match('/<\/body>/i', $body)) {
            return preg_replace('/<\/body>/i', $footer.'</body>', $body, 1);
        }

        return '<!DOCTYPE html><html lang="'.e($locale).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            .'<body style="margin:0;padding:24px;background:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1e293b">'
            .'<div style="max-width:640px;margin:0 auto">'.$body.$footer.'</div></body></html>';
    }
}
