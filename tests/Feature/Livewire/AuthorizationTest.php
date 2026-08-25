<?php

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Dashboard;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerForm;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerIndex;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerRuns;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Every screen of the UI, mounted the way its route mounts it.
 */
function mountSchedulerScreen(string $screen): Testable
{
    return match ($screen) {
        'index' => Livewire::test(SchedulerIndex::class),
        'dashboard' => Livewire::test(Dashboard::class),
        'create' => Livewire::test(SchedulerForm::class),
        'edit' => Livewire::test(SchedulerForm::class, ['scheduler' => Scheduler::factory()->create()]),
        'runs' => Livewire::test(SchedulerRuns::class, ['scheduler' => Scheduler::factory()->create()]),
        'all-runs' => Livewire::test(SchedulerRuns::class),
    };
}

dataset('screens', ['index', 'dashboard', 'create', 'edit', 'runs', 'all-runs']);

/*
 * The package grants nothing on its own. An application that installs it and
 * forgets to define the gate must end up with a UI nobody can reach, not one
 * every logged-in user can run Artisan commands from.
 */
it('denies every screen when the gate is not defined', function (string $screen) {
    expect(Gate::has(config('scheduler-manager.gate')))->toBeFalse();

    mountSchedulerScreen($screen)->assertForbidden();
})->with('screens');

it('denies every screen when the gate returns false', function (string $screen) {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => false);

    mountSchedulerScreen($screen)->assertForbidden();
})->with('screens');

it('allows every screen when the gate returns true', function (string $screen) {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);

    mountSchedulerScreen($screen)->assertOk();
})->with('screens');

it('denies every screen to a guest when the gate requires a user', function (string $screen) {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => $user !== null);

    expect(auth()->check())->toBeFalse();

    mountSchedulerScreen($screen)->assertForbidden();
})->with('screens');

it('allows every screen to an authenticated user when the gate requires one', function (string $screen) {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => $user !== null);

    $this->actingAs(tap(new User)->forceFill(['id' => 1]));

    mountSchedulerScreen($screen)->assertOk();
})->with('screens');

it('denies every write on the index when the gate returns false', function (string $method) {
    Gate::define(config('scheduler-manager.gate'), function ($user = null, $scheduler = null, ?string $ability = null) {
        return $ability === 'viewAny';
    });

    Queue::fake();

    $scheduler = Scheduler::factory()->create();

    Livewire::test(SchedulerIndex::class)
        ->assertOk()
        ->call($method, $scheduler->uuid)
        ->assertForbidden();

    Queue::assertNothingPushed();

    expect($scheduler->fresh())->not->toBeNull()
        ->and($scheduler->fresh()->enabled)->toBeTrue();
})->with(['toggle', 'runNow', 'delete']);

/*
 * Triggering a run and editing what a run executes are different privileges:
 * an on-call operator may need to kick off a nightly report by hand without
 * being able to change it into "rm -rf". The policy checks them as separate
 * abilities, so the configured gate is told which one is being asked for.
 */
it('checks run separately from update', function () {
    Gate::define(config('scheduler-manager.gate'), function ($user = null, $scheduler = null, ?string $ability = null) {
        return $ability !== 'run';
    });

    Queue::fake();

    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerIndex::class)
        ->call('runNow', $scheduler->uuid)
        ->assertForbidden();

    Queue::assertNothingPushed();

    // The very same user may still edit the scheduler.
    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->assertOk()
        ->set('name', 'Renamed report')
        ->call('save')
        ->assertHasNoErrors();

    expect($scheduler->fresh()->name)->toBe('Renamed report');
});

it('allows run to be granted without granting update', function () {
    Gate::define(config('scheduler-manager.gate'), function ($user = null, $scheduler = null, ?string $ability = null) {
        return in_array($ability, ['viewAny', 'view', 'run'], true);
    });

    Queue::fake();

    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerIndex::class)
        ->call('runNow', $scheduler->uuid)
        ->assertOk()
        ->assertDispatched('scheduler-dispatched', name: 'Nightly report');

    Queue::assertPushed(RunSchedulerJob::class);

    // …but not edit it, delete it, or turn it off.
    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])->assertForbidden();
    Livewire::test(SchedulerForm::class)->assertForbidden();
    Livewire::test(SchedulerIndex::class)->call('toggle', $scheduler->uuid)->assertForbidden();
    Livewire::test(SchedulerIndex::class)->call('delete', $scheduler->uuid)->assertForbidden();

    expect($scheduler->fresh()->enabled)->toBeTrue();
});

it('denies deletion from the form when the gate denies it', function () {
    Gate::define(config('scheduler-manager.gate'), function ($user = null, $scheduler = null, ?string $ability = null) {
        return $ability !== 'delete';
    });

    $scheduler = Scheduler::factory()->create();

    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->assertOk()
        ->call('delete')
        ->assertForbidden();

    expect(Scheduler::count())->toBe(1);
});

it('keeps working for a gate that only declares the user argument', function () {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);

    $scheduler = Scheduler::factory()->create();

    Livewire::test(SchedulerIndex::class)->call('toggle', $scheduler->uuid)->assertOk();
    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])->assertOk();

    expect($scheduler->fresh()->enabled)->toBeFalse();
});

it('serves a 403 over http when the gate is not defined', function (string $route, bool $needsScheduler) {
    $parameters = $needsScheduler ? [Scheduler::factory()->create()] : [];

    $this->withoutMiddleware(Authenticate::class)
        ->get(route($route, $parameters))
        ->assertForbidden();
})->with([
    ['scheduler-manager.index', false],
    ['scheduler-manager.dashboard', false],
    ['scheduler-manager.create', false],
    ['scheduler-manager.edit', true],
    ['scheduler-manager.runs', true],
]);
