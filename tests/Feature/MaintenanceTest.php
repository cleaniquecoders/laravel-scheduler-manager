<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;

it('reaps a run abandoned beyond the threshold', function () {
    $scheduler = Scheduler::factory()->create();
    $stale = SchedulerRun::factory()->stale(6)->create(['scheduler_id' => $scheduler->id]);
    $fresh = SchedulerRun::factory()->running()->create(['scheduler_id' => $scheduler->id]);

    $this->artisan('scheduler-manager:reap')
        ->expectsOutputToContain('Reaped 1 abandoned run(s).')
        ->assertSuccessful();

    expect($stale->fresh()->status)->toBe(RunStatus::Failed)
        ->and($stale->fresh()->finished_at)->not->toBeNull()
        ->and($stale->fresh()->exception)->toContain('Run abandoned')
        ->and($fresh->fresh()->status)->toBe(RunStatus::Running);
});

it('honours an explicit reap threshold', function () {
    $scheduler = Scheduler::factory()->create();
    SchedulerRun::factory()->stale(2)->create(['scheduler_id' => $scheduler->id]);

    $this->artisan('scheduler-manager:reap', ['--threshold' => 86400])
        ->expectsOutputToContain('Reaped 0 abandoned run(s).')
        ->assertSuccessful();
});

it('never reaps a run that already finished', function () {
    $scheduler = Scheduler::factory()->create();
    $done = SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDays(2),
    ]);

    $this->artisan('scheduler-manager:reap')->assertSuccessful();

    expect($done->fresh()->status)->toBe(RunStatus::Success);
});

it('prunes runs beyond the retention window', function () {
    config()->set('scheduler-manager.retention_keep_last', 0);

    $scheduler = Scheduler::factory()->create();

    SchedulerRun::factory()->successful()->count(3)->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDays(60),
    ]);
    SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDay(),
    ]);

    $this->artisan('scheduler-manager:prune', ['--days' => 30])
        ->expectsOutputToContain('Pruned 3 run(s).')
        ->assertSuccessful();

    expect(SchedulerRun::count())->toBe(1);
});

it('always keeps the most recent runs per scheduler', function () {
    $scheduler = Scheduler::factory()->create();

    foreach (range(1, 5) as $i) {
        SchedulerRun::factory()->successful()->create([
            'scheduler_id' => $scheduler->id,
            'started_at' => now()->subDays(60 + $i),
        ]);
    }

    $this->artisan('scheduler-manager:prune', ['--days' => 30, '--keep-last' => 2])
        ->assertSuccessful();

    expect(SchedulerRun::count())->toBe(2);
});

it('reports without deleting on a dry run', function () {
    config()->set('scheduler-manager.retention_keep_last', 0);

    $scheduler = Scheduler::factory()->create();
    SchedulerRun::factory()->successful()->count(2)->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subDays(90),
    ]);

    $this->artisan('scheduler-manager:prune', ['--days' => 30, '--dry-run' => true])
        ->expectsOutputToContain('2 run(s) would be deleted.')
        ->assertSuccessful();

    expect(SchedulerRun::count())->toBe(2);
});

it('reaps stale runs during a tick', function () {
    config()->set('scheduler-manager.reap_on_tick', true);

    $scheduler = Scheduler::factory()->disabled()->create();
    $stale = SchedulerRun::factory()->stale(6)->create(['scheduler_id' => $scheduler->id]);

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(RunStatus::Failed);
});
