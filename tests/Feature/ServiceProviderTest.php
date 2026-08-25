<?php

use CleaniqueCoders\LaravelSchedulerManager\Livewire\Dashboard;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerForm;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerIndex;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerRuns;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Policies\SchedulerPolicy;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

it('registers every console command', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain(
        'scheduler-manager:tick',
        'scheduler-manager:run',
        'scheduler-manager:list',
        'scheduler-manager:toggle',
        'scheduler-manager:prune',
        'scheduler-manager:reap',
        'scheduler-manager:import',
    );
});

it('mounts the routes under the configured prefix', function () {
    expect(route('scheduler-manager.index', absolute: false))->toBe('/scheduler-manager')
        ->and(route('scheduler-manager.dashboard', absolute: false))->toBe('/scheduler-manager/dashboard')
        ->and(route('scheduler-manager.create', absolute: false))->toBe('/scheduler-manager/create');
});

it('applies the configured middleware to the routes', function () {
    expect(Route::getRoutes()->getByName('scheduler-manager.index')->gatherMiddleware())
        ->toContain('web', 'auth');
});

it('registers the livewire components under the package namespace', function () {
    foreach ([
        'scheduler-manager::scheduler-index' => SchedulerIndex::class,
        'scheduler-manager::scheduler-form' => SchedulerForm::class,
        'scheduler-manager::scheduler-runs' => SchedulerRuns::class,
        'scheduler-manager::dashboard' => Dashboard::class,
    ] as $alias => $class) {
        expect(Livewire\Livewire::new($alias))->toBeInstanceOf($class);
    }
});

it('registers the scheduler policy', function () {
    expect(Gate::getPolicyFor(Scheduler::class))->toBeInstanceOf(SchedulerPolicy::class);
});

/*
 * End-to-end smoke checks: the route, the Livewire alias, the package views and
 * the layout all have to line up for these to return 200, which is what catches
 * a wiring regression that unit-level assertions miss.
 */
it('serves every screen', function (string $name, bool $needsScheduler) {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);

    $parameters = $needsScheduler ? [Scheduler::factory()->create()] : [];

    $this->withoutMiddleware(Authenticate::class)
        ->get(route($name, $parameters))
        ->assertOk();
})->with([
    ['scheduler-manager.index', false],
    ['scheduler-manager.dashboard', false],
    ['scheduler-manager.create', false],
    ['scheduler-manager.edit', true],
    ['scheduler-manager.runs', true],
]);
