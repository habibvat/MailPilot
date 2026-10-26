<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Support\ContactFilter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    private const COLUMNS = [
        'company_name' => 'Raison sociale',
        'email' => 'Email',
        'phone' => 'Téléphone',
        'sector' => 'Secteur',
        'wilaya' => 'Région',
        'website' => 'Site web',
        'tags' => 'Tags',
        'verify_status' => 'Vérification',
        'verify_reason' => 'Raison vérification',
        'status' => 'Statut',
        'last_contacted_at' => 'Dernier envoi',
        'replied_at' => 'Réponse le',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $query = ContactFilter::apply(Contact::query(), $request->only(ContactFilter::KEYS));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM pour qu'Excel lise l'UTF-8
            fputcsv($out, array_map(fn ($label) => __($label), array_values(self::COLUMNS)), ';', '"', '');

            $query->orderBy('id')->chunkById(1000, function ($contacts) use ($out) {
                foreach ($contacts as $contact) {
                    fputcsv($out, [
                        $contact->company_name,
                        $contact->email,
                        $contact->phone,
                        $contact->sector,
                        $contact->wilaya,
                        $contact->website,
                        implode(', ', $contact->tags ?? []),
                        __(Contact::VERIFY_STATUSES[$contact->verify_status] ?? $contact->verify_status),
                        __((string) $contact->verify_reason),
                        __(Contact::STATUSES[$contact->status] ?? $contact->status),
                        $contact->last_contacted_at?->format('Y-m-d H:i'),
                        $contact->replied_at?->format('Y-m-d H:i'),
                    ], ';', '"', '');
                }
            });

            fclose($out);
        }, 'contacts-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
