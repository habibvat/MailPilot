@props(['title', 'subtitle' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">{{ __($title) }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ __($subtitle) }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
