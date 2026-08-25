<?php

namespace CleaniqueCoders\LaravelSchedulerManager;

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Database\Eloquent\Builder;

class LaravelSchedulerManager
{
    /**
     * Query builder for scheduler records.
     */
    public function schedulers(): Builder
    {
        return Scheduler::query();
    }

    /**
     * The configured action whitelist.
     *
     * Only identifiers present here may be executed by an "action" scheduler.
     *
     * @return array<string, class-string|callable>
     */
    public function actions(): array
    {
        return config('scheduler-manager.actions', []);
    }

    /**
     * Whether the given identifier is an allowed action.
     */
    public function allowsAction(string $identifier): bool
    {
        return array_key_exists($identifier, $this->actions());
    }

    /**
     * Dispatch a scheduler immediately, off-schedule.
     */
    public function run(Scheduler|string $scheduler): void
    {
        $scheduler = $scheduler instanceof Scheduler
            ? $scheduler
            : Scheduler::query()->uuid($scheduler)->firstOrFail();

        RunSchedulerJob::dispatch($scheduler);
    }
}
