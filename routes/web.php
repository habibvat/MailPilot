<?php

use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\LocaleController;
use App\Livewire\Campaigns;
use App\Livewire\Contacts;
use App\Livewire\Dashboard;
use App\Livewire\Imports;
use App\Livewire\Settings;
use App\Livewire\SmtpAccounts;
use App\Livewire\Templates;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/contacts', Contacts\Index::class)->name('contacts.index');
Route::get('/contacts/export', ContactExportController::class)->name('contacts.export');

Route::get('/imports', Imports\Index::class)->name('imports.index');
Route::get('/imports/nouveau', Imports\Create::class)->name('imports.create');

Route::get('/templates', Templates\Index::class)->name('templates.index');
Route::get('/templates/nouveau', Templates\Form::class)->name('templates.create');
Route::get('/templates/{template}', Templates\Form::class)->name('templates.edit');

Route::get('/smtp', SmtpAccounts\Index::class)->name('smtp.index');
Route::get('/smtp/nouveau', SmtpAccounts\Form::class)->name('smtp.create');
Route::get('/smtp/{account}', SmtpAccounts\Form::class)->name('smtp.edit');

Route::get('/campagnes', Campaigns\Index::class)->name('campaigns.index');
Route::get('/campagnes/nouvelle', Campaigns\Wizard::class)->name('campaigns.create');
Route::get('/campagnes/{campaign}/modifier', Campaigns\Wizard::class)->name('campaigns.edit');
Route::get('/campagnes/{campaign}', Campaigns\Show::class)->name('campaigns.show');

Route::get('/parametres', Settings::class)->name('settings');
Route::post('/langue', LocaleController::class)->name('locale');
