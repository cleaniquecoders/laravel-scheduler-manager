<?php

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    Carbon::setTestNow('2026-01-01 10:00:00');
});

it('computes next_run_at on create', function () {
    $scheduler = Scheduler::factory()->cron('0 * * * *')->create();

    expect($scheduler->next_run_at->toDateTimeString())->toBe('2026-01-01 11:00:00');
});

it('recomputes next_run_at when the cron changes', function () {
    $scheduler = Scheduler::factory()->cron('0 * * * *')->create();

    $scheduler->update(['cron' => '30 12 * * *']);

    expect($scheduler->fresh()->next_run_at->toDateTimeString())->toBe('2026-01-01 12:30:00');
});

it('recomputes next_run_at when the timezone changes', function () {
    $scheduler = Scheduler::factory()->timezone('UTC')->cron('0 12 * * *')->create();

    expect($scheduler->next_run_at->toDateTimeString())->toBe('2026-01-01 12:00:00');

    // 12:00 in Kuala Lumpur is 04:00 UTC, which has already passed today.
    $scheduler->update(['timezone' => 'Asia/Kuala_Lumpur']);

    expect($scheduler->fresh()->next_run_at->toDateTimeString())->toBe('2026-01-02 04:00:00');
});

it('stores next_run_at in the application timezone', function () {
    $scheduler = Scheduler::factory()->timezone('Asia/Kuala_Lumpur')->cron('0 20 * * *')->create();

    // 20:00 KL is 12:00 UTC on the same day.
    expect($scheduler->next_run_at->toDateTimeString())->toBe('2026-01-01 12:00:00');
});

it('leaves next_run_at null for an unparseable cron', function () {
    $scheduler = Scheduler::factory()->create(['cron' => 'nonsense']);

    expect($scheduler->next_run_at)->toBeNull()
        ->and($scheduler->isCronValid())->toBeFalse();
});

it('refreshes last_run_at and next_run_at after a run', function () {
    $scheduler = Scheduler::factory()->cron('0 * * * *')->create();

    Carbon::setTestNow('2026-01-01 11:00:05');

    (new RunSchedulerJob($scheduler))->handle();

    $scheduler->refresh();

    expect($scheduler->last_run_at->toDateTimeString())->toBe('2026-01-01 11:00:05')
        ->and($scheduler->next_run_at->toDateTimeString())->toBe('2026-01-01 12:00:00');
});

it('does not advance last_run_at when a run is skipped', function () {
    $scheduler = Scheduler::factory()->preventingOverlap()->create();

    $lock = Cache::lock("scheduler_manager:{$scheduler->id}:lock", 300);
    $lock->get();

    (new RunSchedulerJob($scheduler))->handle();

    expect($scheduler->fresh()->last_run_at)->toBeNull();

    $lock->release();
});
