<?php

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Support\Facades\Queue;

it('lists schedulers', function () {
    Scheduler::factory()->create(['name' => 'Nightly Report']);

    $this->artisan('scheduler-manager:list')
        ->expectsOutputToContain('Nightly Report')
        ->assertSuccessful();
});

it('reports when there are no schedulers', function () {
    $this->artisan('scheduler-manager:list')
        ->expectsOutputToContain('No schedulers found.')
        ->assertSuccessful();
});

it('filters the listing to failing schedulers', function () {
    $ok = Scheduler::factory()->create(['name' => 'Healthy Task']);
    SchedulerRun::factory()->successful()->create(['scheduler_id' => $ok->id]);

    $bad = Scheduler::factory()->create(['name' => 'Broken Task']);
    SchedulerRun::factory()->failed()->create(['scheduler_id' => $bad->id]);

    $this->artisan('scheduler-manager:list', ['--failing' => true])
        ->expectsOutputToContain('Broken Task')
        ->doesntExpectOutputToContain('Healthy Task')
        ->assertSuccessful();
});

it('dispatches a scheduler by uuid', function () {
    Queue::fake();

    $scheduler = Scheduler::factory()->create();

    $this->artisan('scheduler-manager:run', ['uuid' => $scheduler->uuid])
        ->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class);
});

it('runs a scheduler synchronously', function () {
    $scheduler = Scheduler::factory()->create();

    $this->artisan('scheduler-manager:run', ['uuid' => $scheduler->uuid, '--sync' => true])
        ->assertSuccessful();

    expect($scheduler->runs()->count())->toBe(1);
});

it('fails when the uuid is unknown', function () {
    $this->artisan('scheduler-manager:run', ['uuid' => 'missing'])
        ->expectsOutputToContain('No scheduler found')
        ->assertFailed();
});

it('toggles a scheduler', function () {
    $scheduler = Scheduler::factory()->create(['enabled' => true]);

    $this->artisan('scheduler-manager:toggle', ['uuid' => $scheduler->uuid])->assertSuccessful();
    expect($scheduler->fresh()->enabled)->toBeFalse();

    $this->artisan('scheduler-manager:toggle', ['uuid' => $scheduler->uuid])->assertSuccessful();
    expect($scheduler->fresh()->enabled)->toBeTrue();
});

it('forces a scheduler state', function () {
    $scheduler = Scheduler::factory()->disabled()->create();

    $this->artisan('scheduler-manager:toggle', ['uuid' => $scheduler->uuid, '--enable' => true])
        ->assertSuccessful();

    expect($scheduler->fresh()->enabled)->toBeTrue();
});
