<?php

namespace App\Console\Commands;

use App\Support\SystemTimezone;
use Illuminate\Console\Command;

/**
 * Au premier lancement, règle le fuseau horaire du fichier .env d'après celui du système.
 * Il sert aux créneaux d'envoi des campagnes et à l'affichage des dates.
 */
class ConfigureEnvironment extends Command
{
    protected $signature = 'mailpilot:configure-env';

    protected $description = 'Règle APP_TIMEZONE du fichier .env d\'après le fuseau horaire du système';

    public function handle(): int
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->error('Fichier .env introuvable.');

            return self::FAILURE;
        }

        $timezone = SystemTimezone::detect();
        file_put_contents($path, $this->set(file_get_contents($path), 'APP_TIMEZONE', $timezone));

        $this->info("Fuseau horaire : $timezone");

        return self::SUCCESS;
    }

    private function set(string $env, string $key, string $value): string
    {
        $line = $key.'='.$value;

        return preg_match("/^$key=.*$/m", $env)
            ? preg_replace("/^$key=.*$/m", $line, $env)
            : rtrim($env)."\n".$line."\n";
    }
}
