<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Scheduling\Schedule;

it('imports a command based event', function () {
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    $scheduler = Scheduler::query()->where('identifier', 'inspire')->sole();

    expect($scheduler->type)->toBe(SchedulerType::Artisan)
        ->and($scheduler->identifier)->toBe('inspire')
        ->and($scheduler->cron)->toBe('0 * * * *')
        ->and($scheduler->name)->not->toBeEmpty();
});

it('uses the event description as the scheduler name', function () {
    app(Schedule::class)->command('inspire')->daily()->description('Daily Inspiration');

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->sole()->name)->toBe('Daily Inspiration');
});

it('imports the event timezone', function () {
    app(Schedule::class)->command('inspire')->daily()->timezone('Asia/Kuala_Lumpur');

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->sole()->timezone)->toBe('Asia/Kuala_Lumpur');
});

it('imports as disabled by default', function () {
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->sole()->enabled)->toBeFalse();
});

it('imports as enabled when the flag is passed', function () {
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import', ['--enabled' => true])->assertSuccessful();

    expect(Scheduler::query()->sole()->enabled)->toBeTrue();
});

it('is idempotent across repeated runs', function () {
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    $this->artisan('scheduler-manager:import')
        ->expectsOutputToContain('Skipped (existing)')
        ->assertSuccessful();

    expect(Scheduler::query()->count())->toBe(1);
});

it('imports the same command again when the cron differs', function () {
    app(Schedule::class)->command('inspire')->hourly();
    app(Schedule::class)->command('inspire')->daily();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->where('identifier', 'inspire')->count())->toBe(2);
});

it('reports closure tasks as unsupported instead of importing them', function () {
    app(Schedule::class)->call(fn () => null)->daily();

    $this->artisan('scheduler-manager:import')
        ->expectsOutputToContain('unsupported')
        ->assertSuccessful();

    expect(Scheduler::query()->count())->toBe(0);
});

it('imports supported entries alongside unsupported ones', function () {
    app(Schedule::class)->call(fn () => null)->daily();
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->pluck('identifier')->all())->toBe(['inspire']);
});

it('writes nothing on a dry run', function () {
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import', ['--dry-run' => true])
        ->expectsOutputToContain('inspire')
        ->expectsOutputToContain('nothing was written')
        ->assertSuccessful();

    expect(Scheduler::query()->count())->toBe(0);
});

it('succeeds when the application has no scheduled entries', function () {
    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->count())->toBe(0);
});

/*
 * The README instructs the host application to schedule `scheduler-manager:tick`
 * every minute, so it is always in the schedule being imported. Stored as a
 * scheduler row it would dispatch a job that runs the tick, which dispatches the
 * tick again — amplifying without bound every minute.
 */
it('never imports the tick, which would make the scheduler dispatch itself', function () {
    app(Schedule::class)->command('scheduler-manager:tick')->everyMinute();

    $this->artisan('scheduler-manager:import', ['--enabled' => true])
        ->expectsOutputToContain('Skipped (own commands)')
        ->assertSuccessful();

    expect(Scheduler::query()->count())->toBe(0);
});

it('never imports its own maintenance commands', function () {
    app(Schedule::class)->command('scheduler-manager:prune')->daily();
    app(Schedule::class)->command('scheduler-manager:reap')->hourly();
    app(Schedule::class)->command('inspire')->hourly();

    $this->artisan('scheduler-manager:import')->assertSuccessful();

    expect(Scheduler::query()->pluck('identifier')->all())->toBe(['inspire']);
});
