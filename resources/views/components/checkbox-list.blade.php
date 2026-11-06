@props(['model', 'options', 'empty' => 'Aucune valeur disponible.'])
<div x-data="{
        q: '',
        options: @js(array_values($options)),
        visible() {
            const q = this.q.toLowerCase();
            return q ? this.options.filter(o => o.toLowerCase().includes(q)) : this.options;
        },
        // « Tout cocher » / « Tout décocher » n'agissent que sur les valeurs affichées par le filtre.
        selectVisible() {
            $wire.$set(@js($model), [...new Set([...($wire.$get(@js($model)) ?? []), ...this.visible()])]);
        },
        selectedCount() {
            return ($wire[@js($model)] ?? []).filter(o => this.options.includes(o)).length;
        },
        counter() {
            const selected = this.selectedCount();
            return @js(__(':selected / :total coché(s)')).replace(':selected', selected).replace(':total', this.options.length);
        },
        clearVisible() {
            const hidden = new Set(this.visible());
            $wire.$set(@js($model), ($wire.$get(@js($model)) ?? []).filter(o => ! hidden.has(o)));
        },
     }" class="rounded-md border border-slate-300">
    @if (count($options) > 0)
        <div class="space-y-2 border-b border-slate-200 p-2">
            @if (count($options) > 8)
                <input type="search" x-model="q" class="input py-1.5 text-xs" placeholder="{{ __('Filtrer…') }}">
            @endif
            <div class="space-y-1 text-xs">
                <div class="text-slate-500" x-text="counter()"></div>
                <div class="flex flex-wrap gap-x-3 gap-y-1 whitespace-nowrap">
                    <button type="button" x-on:click="selectVisible()" class="font-medium text-brand-600 hover:underline"
                            x-text="q ? @js(__('Cocher les résultats')) : @js(__('Tout cocher'))"></button>
                    <button type="button" x-on:click="clearVisible()" class="text-slate-500 hover:text-slate-800 hover:underline"
                            x-text="q ? @js(__('Décocher les résultats')) : @js(__('Tout décocher'))"></button>
                </div>
            </div>
            <p x-show="selectedCount() === options.length" x-cloak class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ __('Tout est coché : les contacts sans valeur pour ce critère sont exclus. Pour n\'exclure personne, laissez la liste vide.') }}</p>
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
