{{--
    Do not put wire:key on a <flux:*> tag. Livewire's SupportCompiledWireKeys
    precompiler injects a <?php ?> block immediately before the attribute, i.e.
    inside the tag, and the Blade component compiler then emits invalid PHP:
    "syntax error, unexpected token endif". Livewire 4 derives loop keys itself
    (config livewire.smart_wire_keys, on by default), so the manual attribute is
    redundant here. On a plain HTML element wire:key is still fine.
--}}
@php
    $routes = config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.');

    $statusColor = fn (?\CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus $status) => match ($status) {
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Success => 'green',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Failed => 'red',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Skipped => 'amber',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Running => 'blue',
        default => 'zinc',
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Schedulers</flux:heading>
            <flux:subheading>Every task this application runs on a cron expression.</flux:subheading>
        </div>

        <flux:button href="{{ route($routes.'create') }}" wire:navigate variant="primary" icon="plus">
            New Scheduler
        </flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search name or identifier…"
            icon="magnifying-glass"
            clearable
            label="Search"
        />

        <flux:select wire:model.live="type" label="Type" placeholder="All types">
            <flux:select.option value="">All types</flux:select.option>
            @foreach ($types as $option)
                <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="state" label="State" placeholder="All states">
            <flux:select.option value="">All states</flux:select.option>
            <flux:select.option value="enabled">Enabled</flux:select.option>
            <flux:select.option value="disabled">Disabled</flux:select.option>
        </flux:select>

        <div class="flex items-end">
            <flux:button href="{{ route($routes.'dashboard') }}" wire:navigate icon="chart-bar" class="w-full">
                Dashboard
            </flux:button>
        </div>
    </div>

    @if ($schedulers->isEmpty())
        <flux:card class="space-y-4 text-center">
            <flux:heading size="lg">No schedulers found</flux:heading>

            <flux:text>
                @if ($search !== '' || $type !== '' || $state !== '')
                    Nothing matches the current filters. Clear them, or create a scheduler.
                @else
                    Nothing is scheduled yet. Create your first scheduler to get started.
                @endif
            </flux:text>

            <div>
                <flux:button href="{{ route($routes.'create') }}" wire:navigate variant="primary" icon="plus">
                    New Scheduler
                </flux:button>
            </div>
        </flux:card>
    @else
        <div class="w-full overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sort === 'name'"
                        :direction="$direction"
                        wire:click="sortBy('name')"
                    >
                        Name
                    </flux:table.column>

                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column>Schedule</flux:table.column>

                    <flux:table.column
                        sortable
                        :sorted="$sort === 'last_run_at'"
                        :direction="$direction"
                        wire:click="sortBy('last_run_at')"
                    >
                        Last Run
                    </flux:table.column>

                    <flux:table.column
                        sortable
                        :sorted="$sort === 'next_run_at'"
                        :direction="$direction"
                        wire:click="sortBy('next_run_at')"
                    >
                        Next Run
                    </flux:table.column>

                    <flux:table.column>Enabled</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($schedulers as $scheduler)
                        <flux:table.row>
                            <flux:table.cell class="max-w-xs">
                                <flux:link href="{{ route($routes.'edit', $scheduler) }}" wire:navigate variant="ghost">
                                    <span class="font-medium">{{ $scheduler->name }}</span>
                                </flux:link>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $scheduler->identifier }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $scheduler->type->label() }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <code class="text-xs">{{ $scheduler->cron }}</code>
                                @unless ($scheduler->isCronValid())
                                    <flux:badge size="sm" color="red" class="ms-1">invalid</flux:badge>
                                @endunless
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $scheduler->resolveTimezone() }}
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($scheduler->latestRun)
                                    <flux:badge size="sm" :color="$statusColor($scheduler->latestRun->status)">
                                        {{ $scheduler->latestRun->status->label() }}
                                    </flux:badge>
                                    <div
                                        class="text-xs text-zinc-500 dark:text-zinc-400"
                                        title="{{ $scheduler->last_run_at?->toDayDateTimeString() }}"
                                    >
                                        {{ $scheduler->last_run_at?->diffForHumans() ?? 'never' }}
                                    </div>
                                @else
                                    <flux:text size="sm">Never run</flux:text>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($scheduler->next_run_at)
                                    <span title="{{ $scheduler->next_run_at->toDayDateTimeString() }}">
                                        {{ $scheduler->next_run_at->diffForHumans() }}
                                    </span>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $scheduler->next_run_at->format('Y-m-d H:i') }}
                                    </div>
                                @else
                                    <flux:text size="sm">Not scheduled</flux:text>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:switch
                                    :checked="$scheduler->enabled"
                                    wire:click="toggle('{{ $scheduler->uuid }}')"
                                    aria-label="Toggle {{ $scheduler->name }}"
                                />
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        size="sm"
                                        icon="clock"
                                        href="{{ route($routes.'runs', $scheduler) }}"
                                        wire:navigate
                                    >
                                        Runs
                                    </flux:button>

                                    <flux:button
                                        size="sm"
                                        icon="pencil-square"
                                        href="{{ route($routes.'edit', $scheduler) }}"
                                        wire:navigate
                                    >
                                        Edit
                                    </flux:button>

                                    <flux:modal.trigger name="run-{{ $scheduler->uuid }}">
                                        <flux:button size="sm" icon="play" variant="primary">Run Now</flux:button>
                                    </flux:modal.trigger>

                                    <flux:modal.trigger name="delete-{{ $scheduler->uuid }}">
                                        <flux:button size="sm" icon="trash" variant="danger">Delete</flux:button>
                                    </flux:modal.trigger>
                                </div>

                                <flux:modal name="run-{{ $scheduler->uuid }}" class="min-w-96 max-w-md">
                                    <div class="space-y-6 text-start">
                                        <div>
                                            <flux:heading size="lg">Run now?</flux:heading>
                                            <flux:subheading>
                                                {{ $scheduler->name }} will be dispatched immediately, outside its
                                                schedule.
                                            </flux:subheading>
                                        </div>

                                        <flux:text class="font-mono text-xs">{{ $scheduler->identifier }}</flux:text>

                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">Cancel</flux:button>
                                            </flux:modal.close>

                                            <flux:button
                                                variant="primary"
                                                icon="play"
                                                wire:click="runNow('{{ $scheduler->uuid }}')"
                                            >
                                                Run Now
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>

                                <flux:modal name="delete-{{ $scheduler->uuid }}" class="min-w-96 max-w-md">
                                    <div class="space-y-6 text-start">
                                        <div>
                                            <flux:heading size="lg">Delete this scheduler?</flux:heading>
                                            <flux:subheading>
                                                {{ $scheduler->name }} and its entire run history will be removed.
                                                This cannot be undone.
                                            </flux:subheading>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">Cancel</flux:button>
                                            </flux:modal.close>

                                            <flux:button
                                                variant="danger"
                                                icon="trash"
                                                wire:click="delete('{{ $scheduler->uuid }}')"
                                            >
                                                Delete
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div>{{ $schedulers->links() }}</div>
    @endif
</div>
