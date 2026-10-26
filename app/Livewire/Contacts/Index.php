<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Services\VerificationDispatcher;
use App\Support\ContactFilter;
use App\Support\WilayaNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Contacts')]
#[Layout('components.layouts.app', ['wide' => true])]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sector = '';

    #[Url(except: '')]
    public string $wilaya = '';

    #[Url(except: '')]
    public string $verify_status = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $tag = '';

    public int $perPage = 25;

    /** @var list<string> identifiants cochés sur la page */
    public array $selected = [];

    /** Sélection étendue à tous les contacts correspondant aux filtres. */
    public bool $selectAll = false;

    public string $bulkTag = '';

    public ?int $editingId = null;

    public array $form = [];

    public function updated(string $property): void
    {
        if (in_array($property, [...ContactFilter::KEYS, 'perPage'], true)) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function filters(): array
    {
        return [
            'search' => $this->search,
            'sector' => $this->sector,
            'wilaya' => $this->wilaya,
            'verify_status' => $this->verify_status,
            'status' => $this->status,
            'tag' => $this->tag,
        ];
    }

    public function resetFilters(): void
    {
        $this->reset(...ContactFilter::KEYS);
        $this->resetPage();
        $this->clearSelection();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * @param  list<string>  $ids
     */
    public function togglePage(array $ids): void
    {
        $this->selectAll = false;
        $ids = array_map('strval', $ids);
        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function selectAllMatching(): void
    {
        $this->selectAll = true;
    }

    // --- Actions groupées -------------------------------------------------

    public function bulkVerify(VerificationDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatch($this->selectionQuery());
        $this->finishBulk(__(':count contact(s) envoyés en vérification.', ['count' => $count]));
    }

    public function verifyPending(VerificationDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatch(Contact::where('verify_status', 'pending'));
        $this->dispatch('notify', message: $count > 0 ? __(':count contact(s) en attente envoyés en vérification.', ['count' => $count]) : __('Aucun contact en attente de vérification.'));
    }

    public function bulkTag(): void
    {
        $tag = trim($this->bulkTag);
        if ($tag === '') {
            $this->addError('bulkTag', __('Saisissez un tag.'));

            return;
        }

        $count = 0;
        $this->selectionQuery()->select(['id', 'tags'])->chunkById(500, function ($contacts) use ($tag, &$count) {
            foreach ($contacts as $contact) {
                $tags = $contact->tags ?? [];
                if (! in_array($tag, $tags, true)) {
                    $contact->tags = [...$tags, $tag];
                    $contact->save();
                }
                $count++;
            }
        });

        $this->bulkTag = '';
        $this->finishBulk(__('Tag « :tag » ajouté à :count contact(s).', ['tag' => $tag, 'count' => $count]));
    }

    public function bulkUntag(): void
    {
        $tag = trim($this->bulkTag);
        if ($tag === '') {
            $this->addError('bulkTag', __('Saisissez le tag à retirer.'));

            return;
        }

        $count = 0;
        $this->selectionQuery()->whereJsonContains('tags', $tag)->select(['id', 'tags'])
            ->chunkById(500, function ($contacts) use ($tag, &$count) {
                foreach ($contacts as $contact) {
                    $contact->tags = array_values(array_diff($contact->tags ?? [], [$tag])) ?: null;
                    $contact->save();
                    $count++;
                }
            });

        $this->bulkTag = '';
        $this->finishBulk(__('Tag « :tag » retiré de :count contact(s).', ['tag' => $tag, 'count' => $count]));
    }

    public function bulkExclude(): void
    {
        $count = $this->selectionQuery()->where('status', 'active')->update(['status' => 'excluded', 'updated_at' => now()]);
        $this->finishBulk(__(':count contact(s) exclus des campagnes.', ['count' => $count]));
    }

    public function bulkReactivate(): void
    {
        $count = $this->selectionQuery()->where('status', 'excluded')->update(['status' => 'active', 'updated_at' => now()]);
        $this->finishBulk(__(':count contact(s) réactivés.', ['count' => $count]));
    }

    // --- Édition ----------------------------------------------------------

    public function edit(int $id): void
    {
        $contact = Contact::findOrFail($id);
        $this->editingId = $contact->id;
        $this->form = [
            'company_name' => $contact->company_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'sector' => $contact->sector,
            'wilaya' => $contact->wilaya,
            'website' => $contact->website,
            'tags' => implode(', ', $contact->tags ?? []),
            'status' => $contact->status,
        ];
        $this->resetValidation();
    }

    public function save(WilayaNormalizer $wilayas): void
    {
        $data = $this->validate([
            'form.company_name' => ['nullable', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('contacts', 'email')->ignore($this->editingId)],
            'form.phone' => ['nullable', 'string', 'max:255'],
            'form.sector' => ['nullable', 'string', 'max:255'],
            'form.wilaya' => ['nullable', 'string', 'max:255'],
            'form.website' => ['nullable', 'string', 'max:255'],
            'form.tags' => ['nullable', 'string'],
            'form.status' => ['required', Rule::in(array_keys(Contact::STATUSES))],
        ], [], [
            'form.email' => 'email',
        ])['form'];

        $contact = Contact::findOrFail($this->editingId);
        $emailChanged = mb_strtolower(trim($data['email'])) !== $contact->email;

        $contact->fill([
            ...$data,
            'wilaya' => $wilayas->normalize($data['wilaya']),
            'tags' => array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $data['tags']))))) ?: null,
        ]);
        if ($emailChanged) {
            $contact->fill(['verify_status' => 'pending', 'verify_reason' => null, 'verified_at' => null]);
        }
        $contact->save();

        $this->editingId = null;
        $this->dispatch('notify', message: __('Contact enregistré.'));
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function render()
    {
        $contacts = $this->query()->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.contacts.index', [
            'contacts' => $contacts,
            'total' => $contacts->total(),
            'selectionCount' => $this->selectAll ? $contacts->total() : count($this->selected),
            'pageIds' => $contacts->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'sectors' => Contact::distinctValues('sector'),
            'wilayas' => Contact::distinctValues('wilaya'),
            'tags' => Contact::distinctTags(),
            'exportUrl' => route('contacts.export', array_filter($this->filters())),
        ]);
    }

    private function query(): Builder
    {
        return ContactFilter::apply(Contact::query(), $this->filters());
    }

    private function selectionQuery(): Builder
    {
        return $this->selectAll ? $this->query() : Contact::whereIn('id', $this->selected);
    }

    private function finishBulk(string $message): void
    {
        $this->clearSelection();
        $this->dispatch('notify', message: $message);
    }
}
