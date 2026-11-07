<?php

namespace App\Support;

use Native\Desktop\Support\Timezones;

/**
 * Fuseau horaire du système, au format IANA (Europe/Paris…). Il sert aux créneaux d'envoi
 * des campagnes et à l'affichage des dates.
 */
final class SystemTimezone
{
    public static function detect(): string
    {
        // ICU lit le fuseau du système (macOS, Linux et Windows) et le renvoie au format IANA.
        $timezone = class_exists(\IntlTimeZone::class) ? \IntlTimeZone::createDefault()->getID() : null;

        if (! $timezone && is_link('/etc/localtime')) {
            $timezone = preg_replace('#^.*/zoneinfo/#', '', (string) readlink('/etc/localtime'));
        }

        // Windows sans intl (binaire de l'application desktop) : nom Windows converti par NativePHP.
        if (! $timezone && PHP_OS_FAMILY === 'Windows' && class_exists(Timezones::class)) {
            $timezone = (new Timezones)->translateFromWindowsString(trim((string) @exec('tzutil /g')));
        }

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }
}
