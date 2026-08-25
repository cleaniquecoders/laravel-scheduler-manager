<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Facades\Cache;

it('runs an artisan command and records the output', function () {
    $scheduler = Scheduler::factory()->create(['identifier' => 'cache:clear']);

    (new RunSchedulerJob($scheduler))->handle();

    $run = $scheduler->runs()->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(RunStatus::Success)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->exit_code)->toBe(0)
        ->and($run->output)->toBeString();

    expect($scheduler->fresh()->last_run_at)->not->toBeNull();
});

it('marks the run as skipped when an overlapping run holds the lock', function () {
    $scheduler = Scheduler::factory()->preventingOverlap()->create();

    $lock = Cache::lock("scheduler_manager:{$scheduler->id}:lock", 300);
    $lock->get();

    (new RunSchedulerJob($scheduler))->handle();

    $run = $scheduler->runs()->first();

    expect($run->status)->toBe(RunStatus::Skipped)
        ->and($run->exception)->toContain('overlapping prevented');

    $lock->release();
});

it('records a failed run when the action cannot be resolved', function () {
    $scheduler = Scheduler::factory()->action('does-not-exist')->create();

    (new RunSchedulerJob($scheduler))->handle();

    $run = $scheduler->runs()->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->exception)->not->toBeNull();
});
