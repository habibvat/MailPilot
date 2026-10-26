<?php

use App\Models\Contact;
use App\Models\Setting;

it('affiche l\'interface en français par défaut et en anglais après changement de langue', function () {
    Contact::create(['email' => 'a@alpha.dz', 'verify_status' => 'valid']);

    $this->get('/')->assertOk()->assertSee('Tableau de bord')->assertSee('<html lang="fr"', false);

    $this->from('/contacts')->post('/langue', ['locale' => 'en'])->assertRedirect('/contacts');
    expect(Setting::get('locale'))->toBe('en');

    $this->get('/')->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Dashboard')
        ->assertSee('Email verification')
        ->assertDontSee('Tableau de bord');
    $this->get('/contacts')->assertOk()->assertSee('Verify pending contacts')->assertSee('Valid');

    $this->post('/langue', ['locale' => 'fr']);
    $this->get('/')->assertSee('Tableau de bord');
});

it('refuse une langue non prise en charge', function () {
    $this->post('/langue', ['locale' => 'de'])->assertSessionHasErrors('locale');
    expect(Setting::get('locale'))->toBe('fr');
});

it('a une traduction anglaise pour chaque texte de l\'interface', function () {
    $english = json_decode(file_get_contents(lang_path('en.json')), true);

    $missing = [];
    $checked = 0;
    foreach ([resource_path('views'), app_path()] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/__\\('((?:[^'\\\\\\\\]|\\\\\\\\.)*)'/", file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $key) {
                $checked++;
                $key = str_replace("\\'", "'", $key);
                if (! array_key_exists($key, $english)) {
                    $missing[] = $file->getFilename().' : '.$key;
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(300)
        ->and($missing)->toBe([]);
});
