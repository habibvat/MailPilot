<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(Locale::SUPPORTED))]]);
        Setting::put('locale', $data['locale']);

        return back();
    }
}
