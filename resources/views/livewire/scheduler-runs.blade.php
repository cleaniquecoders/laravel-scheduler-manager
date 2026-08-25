@php
    $routes = config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.');

    $statusColor = fn (?\CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus $status) => match ($status) {
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Success => 'green',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Failed => 'red',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Skipped => 'amber',
        \CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus::Running => 'blue',
        default => 'zinc',
    };

    /**
     * Command output and stack traces are unbounded: a chatty command can emit
     * megabytes. Cap what reaches the DOM so one noisy run cannot make the page
     * unusable, and tell the reader when the text was cut.
     */
    $limit = 20000;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">
                {{ $scheduler ? $scheduler->name.' — Runs' : 'All Runs' }}
            </flux:heading>
            <flux:subheading>
                {{ $scheduler?->identifier ?? 'Execution history across every scheduler.' }}
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            @if ($scheduler)
                <flux:button href="{{ route($routes.'edit', $scheduler) }}" wire:navigate icon="pencil-square">
                    Edit scheduler
                </flux:button>
            @endif

            <flux:button href="{{ route($routes.'index') }}" wire:navigate icon="arrow-left">
                Back to schedulers
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="status" label="Status" placeholder="All statuses">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($runs->isEmpty())
        <flux:card class="space-y-2 text-center">
            <flux:heading size="lg">No runs recorded</flux:heading>
            <flux:text>
                Nothing matches this filter yet. Runs appear here once the scheduler tick executes a task.
            </flux:text>
        </flux:card>
    @else
        <div class="w-full overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Started</flux:table.column>
                    @unless ($scheduler)
                        <flux:table.column>Scheduler</flux:table.column>
                    @endunless
                    <flux:table.column>Duration</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Exit code</flux:table.column>
                    <flux:table.column align="end">Detail</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($runs as $run)
                        @php($isExpanded = $expanded === $run->id)

                        <flux:table.row wire:key="run-{{ $run->id }}">
                            <flux:table.cell>
                                <span title="{{ $run->started_at?->toDayDateTimeString() }}">
                                    {{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }}
                                </span>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $run->started_at?->diffForHumans() }}
                                </div>
                            </flux:table.cell>

                            @unless ($scheduler)
                                <flux:table.cell>
                                    <flux:link
                                        href="{{ route($routes.'runs', $run->scheduler) }}"
                                        wire:navigate
                                        variant="ghost"
                                    >
                                        {{ $run->scheduler->name }}
                                    </flux:link>
                                </flux:table.cell>
                            @endunless

                            <flux:table.cell>
                                @if ($run->duration() !== null)
                                    {{ number_format($run->duration(), 2) }}s
                                @else
                                    <flux:text size="sm">in flight</flux:text>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$statusColor($run->status)">
                                    {{ $run->status->label() }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $run->exit_code ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    :icon="$isExpanded ? 'chevron-up' : 'chevron-down'"
                                    wire:click="expand({{ $run->id }})"
                                >
                                    {{ $isExpanded ? 'Hide' : 'Expand' }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>

                        @if ($isExpanded)
                            <flux:table.row wire:key="run-detail-{{ $run->id }}">
                                <flux:table.cell colspan="{{ $scheduler ? 5 : 6 }}">
                                    <div class="space-y-4 whitespace-normal py-2">
                                        <div>
                                            <flux:heading size="sm">Output</flux:heading>

                                            @if (filled($run->output))
                                                <pre class="mt-2 max-h-96 overflow-x-auto overflow-y-auto rounded-lg bg-zinc-100 p-3 text-xs leading-relaxed dark:bg-zinc-800">{{ \Illuminate\Support\Str::limit((string) $run->output, $limit) }}</pre>

                                                @if (\Illuminate\Support\Str::length((string) $run->output) > $limit)
                                                    <flux:text size="sm" class="mt-1">
                                                        Output truncated at {{ number_format($limit) }} characters.
                                                    </flux:text>
                                                @endif
                                            @else
                                                <flux:text size="sm" class="mt-2">No output recorded.</flux:text>
                                            @endif
                                        </div>

                                        @if (filled($run->exception))
                                            <div>
                                                <flux:heading size="sm">Exception</flux:heading>

                                                <pre class="mt-2 max-h-96 overflow-x-auto overflow-y-auto rounded-lg bg-red-50 p-3 text-xs leading-relaxed text-red-800 dark:bg-red-950 dark:text-red-200">{{ \Illuminate\Support\Str::limit((string) $run->exception, $limit) }}</pre>

                                                @if (\Illuminate\Support\Str::length((string) $run->exception) > $limit)
                                                    <flux:text size="sm" class="mt-1">
                                                        Trace truncated at {{ number_format($limit) }} characters.
                                                    </flux:text>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endif
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div>{{ $runs->links() }}</div>
    @endif
</div>
