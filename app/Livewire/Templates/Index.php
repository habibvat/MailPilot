<?php

namespace App\Livewire\Templates;

use App\Models\Template;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Templates')]
class Index extends Component
{
    public function duplicate(int $id): void
    {
        $copy = Template::findOrFail($id)->replicate();
        $copy->name .= ' ('.__('copie').')';
        $copy->save();

        $this->dispatch('notify', message: __('Template dupliqué.'));
    }

    public function delete(int $id): void
    {
        $template = Template::withCount(['campaigns' => fn ($q) => $q->whereIn('status', ['draft', 'running', 'paused'])])->findOrFail($id);
        if ($template->campaigns_count > 0) {
            $this->dispatch('notify', type: 'error', message: __('Ce template est utilisé par une campagne active ou en brouillon.'));

            return;
        }

        $template->delete();
        $this->dispatch('notify', message: __('Template supprimé.'));
    }

    public function render()
    {
        return view('livewire.templates.index', [
            'templates' => Template::withCount('campaigns')->orderBy('name')->get(),
        ]);
    }
}
