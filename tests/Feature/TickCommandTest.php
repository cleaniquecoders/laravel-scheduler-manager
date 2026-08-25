<?php

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Facades\Queue;

it('dispatches a job for a due scheduler', function () {
    Queue::fake();

    $scheduler = Scheduler::factory()->create(['cron' => '* * * * *']);

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(
        RunSchedulerJob::class,
        fn (RunSchedulerJob $job) => $job->scheduler->is($scheduler)
    );
});

it('does not dispatch a scheduler that is not due', function () {
    Queue::fake();

    Carbon\Carbon::setTestNow('2026-01-01 10:30:00');

    Scheduler::factory()->create(['cron' => '0 0 * * *']);

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('does not dispatch a disabled scheduler', function () {
    Queue::fake();

    Scheduler::factory()->disabled()->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('skips an invalid cron without aborting the remaining schedulers', function () {
    Queue::fake();

    Scheduler::factory()->create(['cron' => 'not-a-cron']);
    $valid = Scheduler::factory()->create(['cron' => '* * * * *']);

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(
        RunSchedulerJob::class,
        fn (RunSchedulerJob $job) => $job->scheduler->is($valid)
    );
    Queue::assertPushed(RunSchedulerJob::class, 1);
});

it('evaluates the cron in the scheduler timezone', function () {
    Queue::fake();

    // 00:30 in Kuala Lumpur is 16:30 UTC the previous day.
    Carbon\Carbon::setTestNow('2026-01-01 16:30:00');
    config()->set('app.timezone', 'UTC');

    $kl = Scheduler::factory()->timezone('Asia/Kuala_Lumpur')->cron('30 0 * * *')->create();
    Scheduler::factory()->timezone('UTC')->cron('30 0 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 1);
    Queue::assertPushed(
        RunSchedulerJob::class,
        fn (RunSchedulerJob $job) => $job->scheduler->is($kl)
    );
});
