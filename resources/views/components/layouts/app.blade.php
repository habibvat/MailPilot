<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? __($title).' · ' : '' }}MailPilot</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        // Appliqué avant l'affichage pour éviter un clignotement de la barre latérale.
        try { if (localStorage.getItem('mailpilot.sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
        function toggleSidebar() {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('mailpilot.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans text-slate-800 antialiased">
@php
    $nav = [
        ['route' => 'dashboard', 'label' => 'Tableau de bord', 'match' => 'dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'contacts.index', 'label' => 'Contacts', 'match' => 'contacts.*', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['route' => 'imports.index', 'label' => 'Imports', 'match' => 'imports.*', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
        ['route' => 'templates.index', 'label' => 'Templates', 'match' => 'templates.*', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['route' => 'campaigns.index', 'label' => 'Campagnes', 'match' => 'campaigns.*', 'icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8'],
        ['route' => 'smtp.index', 'label' => 'Comptes SMTP', 'match' => 'smtp.*', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['route' => 'settings', 'label' => 'Paramètres', 'match' => 'settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ];
@endphp
<div class="flex min-h-full">
    <aside class="fixed inset-y-0 left-0 z-20 flex w-60 flex-col border-r border-slate-200 bg-white transition-[width] duration-200 collapsed:w-16">
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5 collapsed:justify-center collapsed:px-0">
            <img src="{{ asset('images/logo-icon.png') }}" alt="MailPilot" class="h-10 w-10 shrink-0">
            <div class="min-w-0 flex-1 collapsed:hidden">
                <div class="text-base font-extrabold leading-tight tracking-tight"><span class="text-[#071b3f]">Mail</span><span class="text-[#0f50f5]">Pilot</span></div>
                <div class="text-[11px] text-slate-500">{{ __('Prospection par email') }}</div>
            </div>
        </div>
        <nav class="flex-1 space-y-0.5 p-3 collapsed:px-2">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate title="{{ __($item['label']) }}"
                   class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium collapsed:justify-center collapsed:px-0 {{ $active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                    <span class="truncate collapsed:hidden">{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('locale') }}" class="flex gap-1 border-t border-slate-200 px-4 py-3 collapsed:flex-col collapsed:px-2">
            @csrf
            @foreach (\App\Support\Locale::SUPPORTED as $code => $language)
                <button type="submit" name="locale" value="{{ $code }}" title="{{ $language }}"
                        class="flex-1 rounded-md px-2 py-1 text-xs font-semibold uppercase {{ app()->getLocale() === $code ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">{{ $code }}</button>
            @endforeach
        </form>
        <div class="border-t border-slate-200 p-4 text-[11px] text-slate-400 collapsed:hidden">
            {{ __('Application locale') }} · {{ now()->timezone(config('mailpilot.timezone'))->format('d/m/Y H:i') }}
            <div class="mt-1">Developed by <span class="font-medium text-slate-500">Habib Belayachi</span></div>
        </div>
        <button type="button" onclick="toggleSidebar()"
                class="flex h-11 items-center gap-3 border-t border-slate-200 px-6 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-900 collapsed:justify-center collapsed:px-0"
                title="{{ __('Replier / déplier le menu') }}" aria-label="{{ __('Replier / déplier le menu') }}">
            <svg class="h-5 w-5 shrink-0 transition-transform collapsed:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            <span class="collapsed:hidden">{{ __('Replier le menu') }}</span>
        </button>
    </aside>

    <main class="ml-60 min-w-0 flex-1 transition-[margin] duration-200 collapsed:ml-16">
        <div class="mx-auto px-8 py-8 {{ ($wide ?? false) ? 'max-w-none' : 'max-w-7xl' }}">
            {{ $slot }}
        </div>
    </main>
</div>

<div x-data="{ toasts: [] }"
     x-on:notify.window="let id = Date.now() + Math.random(); toasts.push({ id, type: $event.detail.type ?? 'success', message: $event.detail.message }); setTimeout(() => toasts = toasts.filter(t => t.id !== id), 5000)"
     class="pointer-events-none fixed bottom-5 right-5 z-50 flex w-96 flex-col gap-2">
    @if (session('notify'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="pointer-events-auto rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow">{{ session('notify') }}</div>
    @endif
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto rounded-md border px-4 py-3 text-sm shadow"
             :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
             x-text="toast.message"></div>
    </template>
</div>

@livewireScripts
</body>
</html>
