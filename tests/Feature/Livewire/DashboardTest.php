<?php

use CleaniqueCoders\LaravelSchedulerManager\Livewire\Dashboard;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);
});

/**
 * next_run_at is derived from the cron on save, so a fixture that needs a
 * specific due time has to be written after creation — without touching the
 * cron, which would recalculate it again.
 */
function scheduleNextRunAt(Scheduler $scheduler, ?Carbon $at): Scheduler
{
    $scheduler->update(['next_run_at' => $at]);

    return $scheduler;
}

it('counts schedulers and recent runs', function () {
    $enabled = Scheduler::factory()->count(3)->create();
    Scheduler::factory()->count(2)->disabled()->create();

    $scheduler = $enabled->first();

    SchedulerRun::factory()->count(2)->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subHours(2),
    ]);

    SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subHours(3),
    ]);

    SchedulerRun::factory()->skipped()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subMinutes(10),
    ]);

    SchedulerRun::factory()->running()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now(),
    ]);

    // Outside the 24 hour window the stat tiles describe.
    SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDays(3),
    ]);

    SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDays(3),
    ]);

    $stats = Livewire::test(Dashboard::class)->assertOk()->viewData('stats');

    expect($stats)->toBe([
        'total' => 5,
        'enabled' => 3,
        'disabled' => 2,
        'succeeded' => 2,
        'failed' => 1,
        'skipped' => 1,
        'running' => 1,
    ]);
});

it('lists only the schedulers whose latest run failed', function () {
    $failing = Scheduler::factory()->create(['name' => 'Broken']);
    $recovered = Scheduler::factory()->create(['name' => 'Recovered']);
    Scheduler::factory()->create(['name' => 'Never run']);

    // Failed most recently, after an earlier success.
    SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $failing->id,
        'started_at' => now()->subHours(2),
    ]);
    SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $failing->id,
        'started_at' => now()->subMinutes(10),
    ]);

    // Failed once, but the latest run succeeded.
    SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $recovered->id,
        'started_at' => now()->subHours(2),
    ]);
    SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $recovered->id,
        'started_at' => now()->subMinutes(10),
    ]);

    $component = Livewire::test(Dashboard::class);

    expect($component->viewData('failing')->pluck('name')->all())->toBe(['Broken']);
});

it('lists only enabled schedulers that are past due as overdue', function () {
    $overdue = scheduleNextRunAt(
        Scheduler::factory()->create(['name' => 'Overdue']),
        now()->subMinutes(30)
    );

    scheduleNextRunAt(
        Scheduler::factory()->disabled()->create(['name' => 'Disabled and overdue']),
        now()->subMinutes(30)
    );

    scheduleNextRunAt(
        Scheduler::factory()->create(['name' => 'Due in a moment']),
        now()->addMinutes(30)
    );

    // Inside the five minute grace period, so not yet a problem.
    scheduleNextRunAt(
        Scheduler::factory()->create(['name' => 'Just now']),
        now()->subMinute()
    );

    scheduleNextRunAt(
        Scheduler::factory()->create(['name' => 'Not scheduled']),
        null
    );

    $component = Livewire::test(Dashboard::class);

    expect($component->viewData('overdue')->pluck('name')->all())->toBe(['Overdue'])
        ->and($overdue->fresh()->next_run_at)->not->toBeNull();
});

it('orders the upcoming list by the next run time', function () {
    scheduleNextRunAt(Scheduler::factory()->create(['name' => 'Third']), now()->addHours(3));
    scheduleNextRunAt(Scheduler::factory()->create(['name' => 'First']), now()->addMinutes(5));
    scheduleNextRunAt(Scheduler::factory()->create(['name' => 'Second']), now()->addHour());

    scheduleNextRunAt(
        Scheduler::factory()->disabled()->create(['name' => 'Disabled']),
        now()->addMinutes(10)
    );

    scheduleNextRunAt(Scheduler::factory()->create(['name' => 'Overdue']), now()->subHour());

    $component = Livewire::test(Dashboard::class);

    expect($component->viewData('upcoming')->pluck('name')->all())
        ->toBe(['First', 'Second', 'Third']);
});

it('renders the empty state when nothing is scheduled', function () {
    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Nothing is failing.')
        ->assertSee('Nothing is overdue.')
        ->assertSee('Nothing is scheduled.');
});
