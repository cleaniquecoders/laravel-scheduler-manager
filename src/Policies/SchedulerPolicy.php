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
        return $this->allows($user);
    }

    public function view(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, $scheduler);
    }

    public function create(?Authenticatable $user): bool
    {
        return $this->allows($user);
    }

    public function update(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, $scheduler);
    }

    public function delete(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, $scheduler);
    }

    /**
     * Triggering a run is separate from editing it, so an operator can be
     * allowed to run a task without being allowed to change what it runs.
     */
    public function run(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, $scheduler);
    }

    public function toggle(?Authenticatable $user, Scheduler $scheduler): bool
    {
        return $this->allows($user, $scheduler);
    }

    protected function allows(?Authenticatable $user, ?Scheduler $scheduler = null): bool
    {
        $ability = config('scheduler-manager.gate', 'manage-schedulers');

        if (! Gate::has($ability)) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $scheduler);
    }
}
