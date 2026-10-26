<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    /** Variables proposées dans l'éditeur, selon la langue de l'email. */
    public const VARIABLES = [
        'fr' => ['entreprise', 'secteur', 'region', 'email'],
        'en' => ['company', 'sector', 'region', 'email'],
    ];

    /** Toutes les écritures acceptées, quelle que soit la langue => champ du contact. */
    public const ALIASES = [
        'entreprise' => 'company_name',
        'company' => 'company_name',
        'secteur' => 'sector',
        'sector' => 'sector',
        'region' => 'wilaya',
        'région' => 'wilaya',
        'wilaya' => 'wilaya',
        'email' => 'email',
    ];

    protected $fillable = ['name', 'locale', 'subject', 'body_html', 'body_text'];

    protected $attributes = ['locale' => 'fr'];

    public static function placeholder(string $variable): string
    {
        return '{'.'{'.$variable.'}'.'}';
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
