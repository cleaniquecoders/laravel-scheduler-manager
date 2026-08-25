<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Livewire\Concerns;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;

trait AuthorizesSchedulers
{
    /**
     * Authorization is asserted inside the component, not only on the route,
     * so a Livewire action can never be reached by a request that skipped the
     * route middleware.
     */
    protected function authorizeScheduler(string $ability, ?Scheduler $scheduler = null): void
    {
        $this->authorize($ability, $scheduler ?? Scheduler::class);
    }

    protected function layout(): string
    {
        return config('scheduler-manager.ui.layout', 'scheduler-manager::layouts.app');
    }

    protected function perPage(): int
    {
        return (int) config('scheduler-manager.ui.per_page', 15);
    }
}
