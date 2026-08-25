@php
    $routes = config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.');
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:subheading>Scheduler health at a glance. Run counts cover the last 24 hours.</flux:subheading>
        </div>

        <flux:button href="{{ route($routes.'index') }}" wire:navigate icon="list-bullet">
            All schedulers
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @include('scheduler-manager::components.stats', [
            'label' => 'Total',
            'value' => $stats['total'],
            'hint' => 'schedulers defined',
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Enabled',
            'value' => $stats['enabled'],
            'color' => 'green',
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Disabled',
            'value' => $stats['disabled'],
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Running',
            'value' => $stats['running'],
            'color' => 'blue',
            'hint' => 'in flight now',
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Succeeded',
            'value' => $stats['succeeded'],
            'color' => 'green',
            'hint' => 'last 24 hours',
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Failed',
            'value' => $stats['failed'],
            'color' => 'red',
            'hint' => 'last 24 hours',
        ])

        @include('scheduler-manager::components.stats', [
            'label' => 'Skipped',
            'value' => $stats['skipped'],
            'color' => 'amber',
            'hint' => 'last 24 hours',
        ])
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Failing</flux:heading>
                <flux:subheading>Latest run ended in failure.</flux:subheading>
            </div>

            @if ($failing->isEmpty())
                <flux:text>Nothing is failing.</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($failing as $scheduler)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="failing-{{ $scheduler->uuid }}">
                            <div class="min-w-0">
                                <flux:link href="{{ route($routes.'runs', $scheduler) }}" wire:navigate variant="ghost">
                                    <span class="truncate font-medium">{{ $scheduler->name }}</span>
                                </flux:link>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $scheduler->latestRun?->started_at?->diffForHumans() }}
                                </div>
                            </div>

                            <flux:badge size="sm" color="red">Failed</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Overdue</flux:heading>
                <flux:subheading>Due more than five minutes ago and still waiting.</flux:subheading>
            </div>

            @if ($overdue->isEmpty())
                <flux:text>Nothing is overdue.</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($overdue as $scheduler)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="overdue-{{ $scheduler->uuid }}">
                            <div class="min-w-0">
                                <flux:link href="{{ route($routes.'edit', $scheduler) }}" wire:navigate variant="ghost">
                                    <span class="truncate font-medium">{{ $scheduler->name }}</span>
                                </flux:link>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $scheduler->next_run_at?->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            <flux:badge size="sm" color="amber">
                                {{ $scheduler->next_run_at?->diffForHumans() }}
                            </flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Upcoming</flux:heading>
                <flux:subheading>Next scheduled to fire.</flux:subheading>
            </div>

            @if ($upcoming->isEmpty())
                <flux:text>Nothing is scheduled.</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($upcoming as $scheduler)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="upcoming-{{ $scheduler->uuid }}">
                            <div class="min-w-0">
                                <flux:link href="{{ route($routes.'edit', $scheduler) }}" wire:navigate variant="ghost">
                                    <span class="truncate font-medium">{{ $scheduler->name }}</span>
                                </flux:link>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $scheduler->next_run_at?->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            <flux:badge size="sm" color="zinc">
                                {{ $scheduler->next_run_at?->diffForHumans() }}
                            </flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    </div>
</div>
