<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Exécuté au démarrage de l'application desktop (NativePHP).
     */
    public function boot(): void
    {
        Window::open()
            ->title('MailPilot')
            ->width(1280)
            ->height(820)
            ->minWidth(960)
            ->minHeight(640)
            ->rememberState();
    }

    /**
     * Directives php.ini du binaire PHP embarqué.
     */
    public function phpIni(): array
    {
        // Mêmes réglages que php/mailpilot.ini (version web) : imports de 100 000 lignes.
        return [
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
            'memory_limit' => '512M',
            'max_execution_time' => '300',
        ];
    }
}
