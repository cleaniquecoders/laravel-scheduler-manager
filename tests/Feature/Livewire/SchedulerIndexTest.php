<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerIndex;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);
});

/**
 * @return list<string>
 */
function listedSchedulerNames(Testable $component): array
{
    return $component->viewData('schedulers')->pluck('name')->all();
}

it('renders and lists the schedulers', function () {
    Scheduler::factory()->create(['name' => 'Nightly report']);
    Scheduler::factory()->create(['name' => 'Cache warmer']);

    Livewire::test(SchedulerIndex::class)
        ->assertOk()
        ->assertSee('Nightly report')
        ->assertSee('Cache warmer');
});

it('filters by name', function () {
    Scheduler::factory()->create(['name' => 'Nightly report']);
    Scheduler::factory()->create(['name' => 'Cache warmer']);

    $component = Livewire::test(SchedulerIndex::class)->set('search', 'nightly');

    expect(listedSchedulerNames($component))->toBe(['Nightly report']);
});

it('filters by identifier', function () {
    Scheduler::factory()->create(['name' => 'Nightly report', 'identifier' => 'reports:nightly']);
    Scheduler::factory()->create(['name' => 'Cache warmer', 'identifier' => 'cache:clear']);

    $component = Livewire::test(SchedulerIndex::class)->set('search', 'cache:clear');

    expect(listedSchedulerNames($component))->toBe(['Cache warmer']);
});

it('resets to the first page when the search changes', function () {
    config()->set('scheduler-manager.ui.per_page', 2);

    Scheduler::factory()->count(6)->create();

    $component = Livewire::test(SchedulerIndex::class)->call('setPage', 3);

    expect($component->viewData('schedulers')->currentPage())->toBe(3);

    $component->set('search', 'nothing-matches-this');

    expect($component->viewData('schedulers')->currentPage())->toBe(1);
});

it('filters by type', function () {
    Scheduler::factory()->create(['name' => 'An artisan task']);
    Scheduler::factory()->action()->create(['name' => 'An action task']);

    $component = Livewire::test(SchedulerIndex::class)->set('type', SchedulerType::Action->value);

    expect(listedSchedulerNames($component))->toBe(['An action task']);

    $component->set('type', SchedulerType::Artisan->value);

    expect(listedSchedulerNames($component))->toBe(['An artisan task']);
});

it('filters by enabled and disabled state', function () {
    Scheduler::factory()->create(['name' => 'Live one']);
    Scheduler::factory()->disabled()->create(['name' => 'Paused one']);

    $component = Livewire::test(SchedulerIndex::class)->set('state', 'enabled');

    expect(listedSchedulerNames($component))->toBe(['Live one']);

    $component->set('state', 'disabled');

    expect(listedSchedulerNames($component))->toBe(['Paused one']);
});

it('toggles the sort direction and reorders the rows', function () {
    Scheduler::factory()->create(['name' => 'Alpha']);
    Scheduler::factory()->create(['name' => 'Beta']);
    Scheduler::factory()->create(['name' => 'Gamma']);

    $component = Livewire::test(SchedulerIndex::class);

    expect(listedSchedulerNames($component))->toBe(['Alpha', 'Beta', 'Gamma']);

    $component->call('sortBy', 'name')->assertSet('direction', 'desc');

    expect(listedSchedulerNames($component))->toBe(['Gamma', 'Beta', 'Alpha']);

    $component->call('sortBy', 'name')->assertSet('direction', 'asc');

    expect(listedSchedulerNames($component))->toBe(['Alpha', 'Beta', 'Gamma']);
});

it('sorts by another column and starts that column ascending', function () {
    $older = Scheduler::factory()->create(['name' => 'Older']);
    $newer = Scheduler::factory()->create(['name' => 'Newer']);

    $older->update(['last_run_at' => now()->subDays(2)]);
    $newer->update(['last_run_at' => now()->subHour()]);

    $component = Livewire::test(SchedulerIndex::class)
        ->call('sortBy', 'last_run_at')
        ->assertSet('sort', 'last_run_at')
        ->assertSet('direction', 'asc');

    expect(listedSchedulerNames($component))->toBe(['Older', 'Newer']);
});

it('ignores a sort column that is not sortable', function () {
    Scheduler::factory()->create(['name' => 'Alpha']);

    Livewire::test(SchedulerIndex::class)
        ->call('sortBy', 'identifier')
        ->assertSet('sort', 'name');
});

it('never orders by a column the client invented', function () {
    Scheduler::factory()->create(['name' => 'Alpha']);

    // The property is bound to the query string, so it arrives from user input
    // without ever passing through sortBy().
    $component = Livewire::test(SchedulerIndex::class)
        ->set('sort', 'name" collate nocase, (select 1)--')
        ->assertOk();

    expect(listedSchedulerNames($component))->toBe(['Alpha']);
});

it('paginates using the configured page size', function () {
    config()->set('scheduler-manager.ui.per_page', 4);

    Scheduler::factory()->count(10)->create();

    $schedulers = Livewire::test(SchedulerIndex::class)->viewData('schedulers');

    expect($schedulers->perPage())->toBe(4)
        ->and($schedulers->count())->toBe(4)
        ->and($schedulers->total())->toBe(10)
        ->and($schedulers->lastPage())->toBe(3);
});

it('flips enabled and announces the toggle', function () {
    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerIndex::class)
        ->call('toggle', $scheduler->uuid)
        ->assertDispatched('scheduler-toggled', name: 'Nightly report', enabled: false);

    expect($scheduler->fresh()->enabled)->toBeFalse();

    Livewire::test(SchedulerIndex::class)
        ->call('toggle', $scheduler->uuid)
        ->assertDispatched('scheduler-toggled', name: 'Nightly report', enabled: true);

    expect($scheduler->fresh()->enabled)->toBeTrue();
});

it('dispatches the run job when running now', function () {
    Queue::fake();

    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerIndex::class)
        ->call('runNow', $scheduler->uuid)
        ->assertDispatched('scheduler-dispatched', name: 'Nightly report');

    Queue::assertPushed(
        RunSchedulerJob::class,
        fn (RunSchedulerJob $job) => $job->scheduler->is($scheduler)
    );
});

it('deletes a scheduler', function () {
    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerIndex::class)
        ->call('delete', $scheduler->uuid)
        ->assertDispatched('scheduler-deleted', name: 'Nightly report');

    expect(Scheduler::query()->whereKey($scheduler->id)->exists())->toBeFalse();
});

it('refuses to act on an unknown uuid rather than touching the wrong row', function () {
    Scheduler::factory()->create();

    expect(fn () => Livewire::test(SchedulerIndex::class)->call('delete', 'not-a-real-uuid'))
        ->toThrow(ModelNotFoundException::class);

    expect(Scheduler::count())->toBe(1);
});

/*
 * The last-run column reads a relation on every row, which is exactly where an
 * index page grows a query per row. Render the same page with three rows and
 * with ten and require the query count to be identical: a per-row query would
 * make the second render cost seven more.
 */
it('does not run a query per row for the last-run column', function () {
    $seed = function (int $count): void {
        Scheduler::factory()
            ->count($count)
            ->create()
            ->each(fn (Scheduler $scheduler) => SchedulerRun::factory()
                ->count(3)
                ->successful()
                ->create(['scheduler_id' => $scheduler->id])
            );
    };

    $render = function (): int {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        Livewire::test(SchedulerIndex::class)->assertOk();

        $queries = count(DB::connection()->getQueryLog());

        DB::connection()->disableQueryLog();

        return $queries;
    };

    $seed(3);
    $withThreeRows = $render();

    $seed(7);
    $withTenRows = $render();

    expect(Scheduler::count())->toBe(10)
        ->and($withTenRows)->toBe($withThreeRows)
        ->and($withTenRows)->toBeLessThanOrEqual(5);
});
