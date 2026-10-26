@props(['model', 'options', 'empty' => 'Aucune valeur disponible.'])
<div x-data="{ q: '' }" class="rounded-md border border-slate-300">
    @if (count($options) > 8)
        <div class="border-b border-slate-200 p-2">
            <input type="search" x-model="q" class="input py-1.5 text-xs" placeholder="{{ __('Filtrer…') }}">
        </div>
    @endif
    <div class="max-h-52 space-y-0.5 overflow-y-auto p-2">
        @forelse ($options as $option)
            <label class="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-slate-50"
                   x-show="!q || @js(mb_strtolower($option)).includes(q.toLowerCase())">
                <input type="checkbox" value="{{ $option }}" wire:model.live="{{ $model }}" class="rounded border-slate-300">
                <span class="truncate">{{ $option }}</span>
            </label>
        @empty
            <p class="px-1.5 py-1 text-xs text-slate-500">{{ __($empty) }}</p>
        @endforelse
    </div>
</div>
