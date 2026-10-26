<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

/**
 * Langue de l'interface, choisie dans l'application et stockée dans les paramètres.
 * Les emails envoyés aux contacts ne sont pas concernés.
 */
final class Locale
{
    public const SUPPORTED = ['fr' => 'Français', 'en' => 'English'];

    public static function current(): string
    {
        $locale = Setting::get('locale');

        return isset(self::SUPPORTED[$locale]) ? $locale : config('app.locale');
    }

    public static function apply(): void
    {
        $locale = self::current();
        App::setLocale($locale);
        Carbon::setLocale($locale);
    }
}
