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
    $editing = (bool) $scheduler?->exists;
@endphp

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $editing ? 'Edit Scheduler' : 'New Scheduler' }}</flux:heading>
            <flux:subheading>
                {{ $editing ? $scheduler->name : 'Define what runs, and how often.' }}
            </flux:subheading>
        </div>

        <flux:button href="{{ route($routes.'index') }}" wire:navigate icon="arrow-left">
            Back to schedulers
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" placeholder="Nightly report" autocomplete="off" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Type</flux:label>
                <flux:select wire:model.live="type">
                    @foreach ($types as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                @foreach ($types as $option)
                    @if ($option['value'] === $type)
                        <flux:description>{{ $option['description'] }}</flux:description>
                    @endif
                @endforeach
                <flux:error name="type" />
            </flux:field>

            <flux:field>
                <flux:label>Identifier</flux:label>

                @if ($type === 'action')
                    @if (count($actions) === 0)
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.heading>No actions registered</flux:callout.heading>
                            <flux:callout.text>
                                Add entries to the <code>actions</code> array in
                                <code>config/scheduler-manager.php</code> before scheduling an action.
                            </flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:select wire:model="identifier" placeholder="Choose an action…">
                            @foreach ($actions as $action)
                                <flux:select.option value="{{ $action }}">{{ $action }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>
                            Only actions allow-listed in config can be scheduled.
                        </flux:description>
                    @endif
                @else
                    <flux:input wire:model="identifier" placeholder="reports:nightly --queue" autocomplete="off" />
                    <flux:description>
                        The Artisan command to run, arguments and options included.
                    </flux:description>
                @endif

                <flux:error name="identifier" />
            </flux:field>

            <flux:field>
                <flux:label>Payload</flux:label>
                <flux:textarea
                    wire:model="payload"
                    rows="5"
                    class="font-mono text-xs"
                    placeholder='{"key": "value"}'
                />
                <flux:description>Optional JSON handed to the runner. Leave empty for none.</flux:description>
                <flux:error name="payload" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-6">
            <flux:field>
                <flux:label>Cron expression</flux:label>
                <flux:input wire:model.live.debounce.300ms="cron" class="font-mono" placeholder="* * * * *" />
                <flux:error name="cron" />
            </flux:field>

            <div class="flex flex-wrap gap-2">
                @foreach ($presets as $label => $expression)
                    <flux:button
                        size="sm"
                        type="button"
                        wire:click="applyPreset('{{ $expression }}')"
                        :variant="$cron === $expression ? 'primary' : 'outline'"
                    >
                        {{ $label }}
                    </flux:button>
                @endforeach
            </div>

            <flux:field>
                <flux:label>Timezone</flux:label>
                <flux:select wire:model.live="timezone" placeholder="Application default">
                    @foreach ($timezones as $identifier)
                        <flux:select.option value="{{ $identifier }}">{{ $identifier }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>The cron expression is evaluated in this timezone.</flux:description>
                <flux:error name="timezone" />
            </flux:field>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading size="sm">Next runs</flux:heading>

                @if (count($upcoming) === 0)
                    <flux:text class="mt-2">
                        No preview available yet — check the cron expression and the timezone.
                    </flux:text>
                @else
                    <ul class="mt-2 space-y-1">
                        @foreach ($upcoming as $index => $run)
                            <li
                                wire:key="upcoming-{{ $index }}"
                                class="font-mono text-sm text-zinc-600 dark:text-zinc-400"
                            >
                                {{ $run }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:switch
                wire:model="enabled"
                label="Enabled"
                description="Disabled schedulers are skipped by the tick command."
            />

            <flux:switch
                wire:model="prevent_overlap"
                label="Prevent overlap"
                description="Skip a run when the previous one is still in flight."
            />

            <flux:error name="enabled" />
            <flux:error name="prevent_overlap" />
        </flux:card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($editing)
                    <flux:modal.trigger name="delete-scheduler">
                        <flux:button type="button" variant="danger" icon="trash">Delete</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:button href="{{ route($routes.'index') }}" wire:navigate variant="ghost">Cancel</flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ $editing ? 'Save changes' : 'Create scheduler' }}
                </flux:button>
            </div>
        </div>
    </form>

    @if ($editing)
        <flux:modal name="delete-scheduler" class="min-w-96 max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete this scheduler?</flux:heading>
                    <flux:subheading>
                        {{ $scheduler->name }} and its entire run history will be removed. This cannot be undone.
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" icon="trash" wire:click="delete">Delete</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
