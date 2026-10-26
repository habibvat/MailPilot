<?php

namespace App\Livewire\Imports;

use App\Models\Import;
use App\Services\VerificationDispatcher;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Imports')]
class Index extends Component
{
    use WithPagination;

    public function verify(int $id, VerificationDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatch(Import::findOrFail($id)->contacts()->where('verify_status', 'pending'));

        $this->dispatch('notify', message: $count > 0 ? __(':count contact(s) envoyés en vérification.', ['count' => $count]) : __('Aucun contact en attente de vérification.'));
    }

    public function render()
    {
        $imports = Import::withCount(['contacts as pending_count' => fn ($q) => $q->where('verify_status', 'pending')])
            ->latest('id')->paginate(15);

        return view('livewire.imports.index', [
            'imports' => $imports,
            'polling' => $imports->contains(fn ($i) => in_array($i->status, ['pending', 'processing'], true) || $i->pending_count > 0),
        ]);
    }
}
