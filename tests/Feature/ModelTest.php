<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Support\Str;

it('auto-generates a uuid on a scheduler', function () {
    $scheduler = Scheduler::factory()->create();

    expect($scheduler->uuid)->not->toBeNull()
        ->and($scheduler->uuid)->toMatch('/^[0-9a-f\-]{36}$/i');
});

it('auto-generates a uuid on a scheduler run', function () {
    $run = SchedulerRun::factory()->create();

    expect($run->uuid)->not->toBeNull();
});

it('accepts an explicitly supplied uuid on a scheduler run', function () {
    $uuid = (string) Str::uuid();

    $run = SchedulerRun::factory()->create(['uuid' => $uuid]);

    expect($run->uuid)->toBe($uuid);
});

it('resolves route bindings by uuid', function () {
    $scheduler = Scheduler::factory()->create();

    expect($scheduler->getRouteKeyName())->toBe('uuid')
        ->and($scheduler->getRouteKey())->toBe($scheduler->uuid);
});

it('scopes a query by uuid', function () {
    $scheduler = Scheduler::factory()->create();
    Scheduler::factory()->create();

    expect(Scheduler::query()->uuid($scheduler->uuid)->first()->is($scheduler))->toBeTrue();
});

it('casts type and status to enums', function () {
    $scheduler = Scheduler::factory()->action()->create();
    $run = SchedulerRun::factory()->failed()->create();

    expect($scheduler->type)->toBe(SchedulerType::Action)
        ->and($run->status)->toBe(RunStatus::Failed);
});

it('relates schedulers to their runs', function () {
    $scheduler = Scheduler::factory()->create();
    SchedulerRun::factory()->count(3)->create(['scheduler_id' => $scheduler->id]);

    expect($scheduler->runs)->toHaveCount(3)
        ->and($scheduler->runs->first()->scheduler->is($scheduler))->toBeTrue();
});

it('resolves the latest run without an n+1', function () {
    $scheduler = Scheduler::factory()->create();

    SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subHour(),
    ]);
    $latest = SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now(),
    ]);

    $loaded = Scheduler::with('latestRun')->find($scheduler->id);

    expect($loaded->latestRun->is($latest))->toBeTrue();
});

it('falls back to the app timezone when none is set', function () {
    config()->set('app.timezone', 'Asia/Kuala_Lumpur');

    $scheduler = Scheduler::factory()->create(['timezone' => null]);

    expect($scheduler->resolveTimezone())->toBe('Asia/Kuala_Lumpur');
});

it('computes run duration once finished', function () {
    $run = SchedulerRun::factory()->create([
        'started_at' => now(),
        'finished_at' => now()->addSeconds(5),
        'status' => RunStatus::Success,
    ]);

    expect($run->duration())->toEqualWithDelta(5.0, 0.1);

    $running = SchedulerRun::factory()->running()->create();

    expect($running->duration())->toBeNull();
});

it('filters runs by status scope', function () {
    $scheduler = Scheduler::factory()->create();
    SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);
    SchedulerRun::factory()->failed()->count(2)->create(['scheduler_id' => $scheduler->id]);

    expect(SchedulerRun::query()->status(RunStatus::Failed)->count())->toBe(2);
});
