{{--
    A single dashboard stat tile.

    Included rather than used as an <x-…> tag: the package registers its views
    with loadViewsFrom() only, which does not register an anonymous component
    namespace, so `<x-scheduler-manager::stats />` would not resolve.

    @include('scheduler-manager::components.stats', [
        'label' => 'Failed',
        'value' => $stats['failed'],
        'color' => 'red',
        'hint'  => 'last 24 hours',
    ])
--}}
@php
    $color ??= 'zinc';
    $hint ??= null;

    $valueClasses = match ($color) {
        'green' => 'text-green-600 dark:text-green-400',
        'red' => 'text-red-600 dark:text-red-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        'blue' => 'text-blue-600 dark:text-blue-400',
        default => 'text-zinc-800 dark:text-zinc-100',
    };
@endphp

<flux:card size="sm" class="space-y-1">
    <flux:subheading size="sm">{{ $label }}</flux:subheading>

    <div class="text-2xl font-semibold {{ $valueClasses }}">{{ $value }}</div>

    @if ($hint)
        <flux:text size="sm">{{ $hint }}</flux:text>
    @endif
</flux:card>
