<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Policies;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Every ability defers to the configured gate.
 *
 * The package deliberately grants nothing on its own: this UI can execute
 * arbitrary Artisan commands on the host every minute, so defaulting to "any
 * authenticated user" would be unsafe in most applications, where the auth
 * guard covers ordinary end users rather than administrators.
 */
class SchedulerPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return $this->allows($user, 'viewAny');
    }

    public function view(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, 'view', $scheduler);
    }

    public function create(?Authenticatable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, 'update', $scheduler);
    }

    public function delete(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, 'delete', $scheduler);
    }

    /**
     * Triggering a run is separate from editing it, so an operator can be
     * allowed to run a task without being allowed to change what it runs.
     */
    public function run(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, 'run', $scheduler);
    }

    public function toggle(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, 'toggle', $scheduler);
    }

    /**
     * The configured gate receives the scheduler (null for the collection-level
     * abilities) and the ability being checked, so an application can grant
     * "run" without granting "update" — triggering a task by hand and changing
     * what that task executes are very different privileges. A gate that only
     * declares the arguments it cares about keeps working: PHP ignores the
     * extra ones.
     */
    protected function allows(?Authenticatable $user, string $ability, ?Scheduler $scheduler = null): bool
    {
        $gate = config('scheduler-manager.gate', 'manage-schedulers');

        if (! Gate::has($gate)) {
            return false;
        }

        return Gate::forUser($user)->allows($gate, [$scheduler, $ability]);
    }
}
