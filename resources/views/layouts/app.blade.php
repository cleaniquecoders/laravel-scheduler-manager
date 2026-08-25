@php
    $routes = config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.');

    /**
     * The package ships no compiled CSS of its own — Flux is styled by the host
     * application's Tailwind build. Use that build when there is one, and fall
     * back to the Tailwind browser build so a bare install is still legible.
     * Point `scheduler-manager.ui.layout` at your own layout to inherit your chrome.
     */
    try {
        $appAssets = app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']);
    } catch (\Throwable) {
        $appAssets = null;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Scheduler Manager' }}</title>

    @if ($appAssets)
        {!! $appAssets !!}
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif

    @fluxAppearance
</head>
<body class="min-h-screen bg-white text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
    <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:brand href="{{ route($routes.'index') }}" name="Scheduler Manager" class="max-lg:hidden" />

        <flux:navbar class="-mb-px max-lg:hidden ms-4">
            <flux:navbar.item
                href="{{ route($routes.'index') }}"
                :current="request()->routeIs($routes.'index')"
                wire:navigate
            >
                Schedulers
            </flux:navbar.item>

            <flux:navbar.item
                href="{{ route($routes.'dashboard') }}"
                :current="request()->routeIs($routes.'dashboard')"
                wire:navigate
            >
                Dashboard
            </flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <flux:navbar class="max-lg:hidden">
            <flux:navbar.item
                href="{{ route($routes.'create') }}"
                icon="plus"
                :current="request()->routeIs($routes.'create')"
                wire:navigate
            >
                New Scheduler
            </flux:navbar.item>
        </flux:navbar>
    </flux:header>

    <flux:navbar scrollable container class="border-b border-zinc-200 px-4 lg:hidden dark:border-zinc-700">
        <flux:navbar.item href="{{ route($routes.'index') }}" :current="request()->routeIs($routes.'index')" wire:navigate>
            Schedulers
        </flux:navbar.item>
        <flux:navbar.item href="{{ route($routes.'dashboard') }}" :current="request()->routeIs($routes.'dashboard')" wire:navigate>
            Dashboard
        </flux:navbar.item>
        <flux:navbar.item href="{{ route($routes.'create') }}" :current="request()->routeIs($routes.'create')" wire:navigate>
            New
        </flux:navbar.item>
    </flux:navbar>

    <flux:main container class="overflow-x-hidden">
        {{ $slot }}
    </flux:main>

    <flux:toast position="top right" />

    @fluxScripts
</body>
</html>
