<?php

use App\Contracts\DnsResolver;
use App\Imports\ContactsImport;
use App\Livewire\Imports\Create;
use App\Models\Contact;
use App\Models\Import;
use App\Models\Setting;
use App\Models\Suppression;
use App\Support\EmailParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->app->instance(DnsResolver::class, new class implements DnsResolver
    {
        public function hasMx(string $domain): bool
        {
            return $domain !== 'sans-mx.dz';
        }
    });
});

function csvImport(string $csv, array $mapping): Import
{
    Storage::disk('local')->put('imports/test.csv', $csv);

    $import = Import::create([
        'filename' => 'test.csv',
        'path' => 'imports/test.csv',
        'mapping' => $mapping + ['_has_header' => true, '_delimiter' => ';', '_encoding' => 'UTF-8'],
    ]);

    Excel::queueImport(new ContactsImport($import->id), 'imports/test.csv', 'local');

    return $import->fresh();
}

it('corrige les fautes de frappe évidentes dans les adresses', function () {
    expect(EmailParser::split('societe@gmail,com'))->toBe(['societe@gmail.com'])
        ->and(EmailParser::split('contact@exemple,dz'))->toBe(['contact@exemple.dz'])
        ->and(EmailParser::split('contact66@gmail'))->toBe(['contact66@gmail.com'])
        ->and(EmailParser::split('ahmed@gmail.'))->toBe(['ahmed@gmail.com'])
        ->and(EmailParser::split('Ali@Gmial.com; sara@hotmial.fr'))->toBe(['ali@gmail.com', 'sara@hotmail.fr'])
        // Une virgule entre deux adresses reste un séparateur.
        ->and(EmailParser::split('a@x.com,fr@y.dz'))->toBe(['a@x.com', 'fr@y.dz'])
        ->and(EmailParser::split('a@x.dz,b@y.dz'))->toBe(['a@x.dz', 'b@y.dz'])
        // Un domaine incomplet sans correction sûre reste invalide.
        ->and(EmailParser::isValid(EmailParser::split('nom@yahoo')[0]))->toBeFalse();
});

it('complète les champs vides d\'un contact existant sans écraser les autres', function () {
    Setting::put('import_algerian_regions', '1');
    Setting::put('import_restore_phone_zero', '1');

    Contact::create(['email' => 'seul@alpha.dz']);
    Contact::create(['email' => 'complet@beta.dz', 'company_name' => 'Beta Spa', 'sector' => 'BTP']);

    $csv = "Raison sociale;Email;Wilaya;Secteur\n"
        ."SARL Alpha;seul@alpha.dz;alger;\n"
        .";seul@alpha.dz;;Imprimerie\n"
        ."Autre nom;complet@beta.dz;Oran;Industrie\n";

    $import = csvImport($csv, ['company_name' => 0, 'email' => 1, 'wilaya' => 2, 'sector' => 3]);

    expect($import->imported)->toBe(0)
        ->and($import->duplicates)->toBe(3)
        ->and($import->enriched)->toBe(2);

    expect(Contact::firstWhere('email', 'seul@alpha.dz')->only('company_name', 'wilaya', 'sector'))
        ->toBe(['company_name' => 'SARL Alpha', 'wilaya' => 'Alger', 'sector' => 'Imprimerie']);
    expect(Contact::firstWhere('email', 'complet@beta.dz')->only('company_name', 'wilaya', 'sector'))
        ->toBe(['company_name' => 'Beta Spa', 'wilaya' => 'Oran', 'sector' => 'BTP']);
});

it('nettoie, découpe les cellules multi-emails, dédoublonne et normalise les wilayas', function () {
    Setting::put('import_algerian_regions', '1');
    Setting::put('import_restore_phone_zero', '1');

    Contact::create(['email' => 'deja@existe.dz']);
    Suppression::add('banni@ex.dz', 'unsubscribe');

    $csv = "Raison sociale;Email;Wilaya;Tel\n"
        ."SARL Alpha; Contact@Alpha.DZ ; alger;550123456\n"
        ."Beta Spa;info@beta.dz, commercial@beta.dz / rh@beta.dz;16 - Alger;\n"
        ."Doublon;contact@alpha.dz;Oran;\n"
        ."Existe;DEJA@existe.dz;bejaia;\n"
        ."Invalide;pas-un-email;Sétif;\n"
        ."Vide;;Blida;\n"
        ."Banni;banni@ex.dz;Tizi-Ouzou;\n";

    $import = csvImport($csv, ['company_name' => 0, 'email' => 1, 'wilaya' => 2, 'phone' => 3]);

    expect($import->status)->toBe('completed')
        ->and($import->total_rows)->toBe(7)
        ->and($import->imported)->toBe(5)
        ->and($import->duplicates)->toBe(2)
        ->and($import->rejected)->toBe(2);

    $alpha = Contact::firstWhere('email', 'contact@alpha.dz');
    expect($alpha->company_name)->toBe('SARL Alpha')
        ->and($alpha->wilaya)->toBe('Alger')
        ->and($alpha->phone)->toBe('0550123456');

    expect(Contact::where('company_name', 'Beta Spa')->pluck('email')->sort()->values()->all())
        ->toBe(['commercial@beta.dz', 'info@beta.dz', 'rh@beta.dz']);
    expect(Contact::firstWhere('email', 'banni@ex.dz')->status)->toBe('unsubscribed');

    // La vérification est lancée automatiquement à la fin de l'import.
    expect($alpha->fresh()->verify_status)->toBe('valid');
    Storage::disk('local')->assertMissing('imports/test.csv');
});

it('garde les régions et téléphones tels quels hors règles algériennes (réglage par défaut)', function () {
    $csv = "Entreprise;Email;Région;Tel\n"
        ."Alpha;a@alpha.fr;13 - Bouches-du-Rhône;612345678\n"
        ."Beta;b@beta.co.uk;Greater London;+44 20 7946 0000\n"
        ."Gamma;g@gamma.dz;16;550123456\n";

    csvImport($csv, ['company_name' => 0, 'email' => 1, 'wilaya' => 2, 'phone' => 3]);

    expect(Contact::firstWhere('email', 'a@alpha.fr')->only('wilaya', 'phone'))->toBe(['wilaya' => '13 - Bouches-du-Rhône', 'phone' => '612345678'])
        ->and(Contact::firstWhere('email', 'b@beta.co.uk')->only('wilaya', 'phone'))->toBe(['wilaya' => 'Greater London', 'phone' => '+44 20 7946 0000'])
        ->and(Contact::firstWhere('email', 'g@gamma.dz')->wilaya)->toBe('16');
});

it('affiche un aperçu et devine le mapping depuis les en-têtes', function () {
    $file = UploadedFile::fake()->createWithContent('base.csv', "Raison sociale;E-mail;Téléphone;Secteur;Wilaya;Site web\nA;a@a.dz;0555;BTP;Oran;a.dz\n");

    Livewire::test(Create::class)
        ->set('file', $file)
        ->assertHasNoErrors()
        ->assertSet('delimiter', ';')
        ->assertSet('mapping.company_name', '0')
        ->assertSet('mapping.email', '1')
        ->assertSet('mapping.phone', '2')
        ->assertSet('mapping.sector', '3')
        ->assertSet('mapping.wilaya', '4')
        ->assertSet('mapping.website', '5')
        ->call('start')
        ->assertRedirect(route('imports.index'));

    expect(Contact::firstWhere('email', 'a@a.dz'))->not->toBeNull();
});

it('affiche les pages contacts et imports', function () {
    Contact::create(['email' => 'x@y.dz', 'company_name' => 'Société X', 'tags' => ['btp']]);

    $this->get('/contacts')->assertOk()->assertSee('Société X');
    $this->get('/contacts?tag=btp')->assertOk()->assertSee('x@y.dz');
    $this->get('/imports')->assertOk();
    $this->get('/imports/nouveau')->assertOk();
    $this->get('/contacts/export?tag=btp')->assertOk()->assertStreamed();
});

it('importe la première feuille d\'un fichier XLSX', function () {
    Setting::put('import_algerian_regions', '1');
    Setting::put('import_restore_phone_zero', '1');

    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([
        ['Société', 'Mail', 'Wilaya'],
        ['Gamma', 'gamma@gamma.dz', 'Constantine'],
        ['Delta', 'delta@delta.dz;compta@delta.dz', 'oum el bouaghi'],
    ]);
    $book->createSheet()->fromArray([['Autre', 'ignore@ignore.dz']]);
    Storage::disk('local')->makeDirectory('imports');
    (new Xlsx($book))->save(Storage::disk('local')->path('imports/test.xlsx'));

    $import = Import::create(['filename' => 'test.xlsx', 'path' => 'imports/test.xlsx', 'mapping' => ['company_name' => 0, 'email' => 1, 'wilaya' => 2, '_has_header' => true]]);
    Excel::queueImport(new ContactsImport($import->id), 'imports/test.xlsx', 'local');

    expect($import->fresh()->imported)->toBe(3)
        ->and(Contact::firstWhere('email', 'compta@delta.dz')->wilaya)->toBe('Oum El Bouaghi')
        ->and(Contact::firstWhere('email', 'ignore@ignore.dz'))->toBeNull();
});

it('affiche l\'aperçu d\'un fichier XLSX', function () {
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $book->getActiveSheet()->fromArray([['Raison sociale', 'Email'], ['Epsilon', 'epsilon@epsilon.dz']]);
    $path = sys_get_temp_dir().'/apercu-'.uniqid().'.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
    $file = UploadedFile::fake()->createWithContent('base.xlsx', file_get_contents($path));
    unlink($path);

    Livewire::test(\App\Livewire\Imports\Create::class)
        ->set('file', $file)
        ->assertHasNoErrors()
        ->assertSet('headers', ['Raison sociale', 'Email'])
        ->assertSet('mapping.email', '1')
        ->assertSee('epsilon@epsilon.dz');
});
