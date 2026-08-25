<?php

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
 * The plain dispatch decisions -- a due scheduler dispatches, a not-due one
 * does not, a disabled one never does, and an invalid cron does not abort the
 * loop -- are covered in tests/Feature/TickCommandTest.php. What follows are
 * the cases those do not reach: realistic (non "* * * * *") expressions at a
 * pinned instant, a batch where the invalid scheduler sits between valid ones,
 * and the DST transitions.
 */

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    config()->set('scheduler-manager.reap_on_tick', false);
});

it('dispatches only the schedulers whose expression matches the pinned instant', function () {
    Queue::fake();

    Carbon::setTestNow('2026-01-01 03:00:00');

    $daily = Scheduler::factory()->cron('0 3 * * *')->create();
    $quarterly = Scheduler::factory()->cron('*/15 * * * *')->create();
    Scheduler::factory()->cron('0 9,17 * * *')->create();
    Scheduler::factory()->cron('0 0 * * 1')->create();
    Scheduler::factory()->cron('0 0 1 * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 2);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($daily));
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($quarterly));
});

it('dispatches nothing when no scheduler is due', function () {
    Queue::fake();

    // 03:07 matches none of these: not the hour mark, not a quarter hour.
    Carbon::setTestNow('2026-01-01 03:07:00');

    Scheduler::factory()->cron('0 3 * * *')->create();
    Scheduler::factory()->cron('*/15 * * * *')->create();
    Scheduler::factory()->cron('0 0 * * 1')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('never dispatches a disabled scheduler whose expression matches', function () {
    Queue::fake();

    Carbon::setTestNow('2026-01-05 00:00:00'); // a Monday

    Scheduler::factory()->disabled()->cron('0 0 * * 1')->create();
    $enabled = Scheduler::factory()->cron('0 0 * * 1')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 1);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($enabled));
});

it('keeps walking the batch when an invalid cron sits between valid due schedulers', function () {
    Queue::fake();

    Carbon::setTestNow('2026-01-01 03:00:00');

    $before = Scheduler::factory()->cron('0 3 * * *')->create();
    Scheduler::factory()->create(['cron' => 'every-so-often']);
    $after = Scheduler::factory()->cron('0 3 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    // The invalid one is reported and stepped over, and crucially the scheduler
    // created after it is still reached.
    Queue::assertPushed(RunSchedulerJob::class, 2);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($before));
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($after));
});

it('dispatches on scheduler local time when the application date has already rolled over', function () {
    Queue::fake();

    // 2026-01-01 16:30 UTC is 00:30 on 2026-01-02 in Kuala Lumpur.
    Carbon::setTestNow('2026-01-01 16:30:00');

    $kualaLumpur = Scheduler::factory()->timezone('Asia/Kuala_Lumpur')->cron('30 0 * * *')->create();
    Scheduler::factory()->timezone('Asia/Kuala_Lumpur')->cron('30 16 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 1);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($kualaLumpur));
});

it('dispatches a spring forward scheduler in the hour after the missing slot', function () {
    Queue::fake();

    // America/New_York skips 02:00-03:00 on 2026-03-08, so 02:30 local never
    // arrives. The tick at 01:30 EST (06:30 UTC) finds nothing due...
    Carbon::setTestNow('2026-03-08 06:30:00');

    Scheduler::factory()->timezone('America/New_York')->cron('30 2 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('folds a spring forward scheduler onto the same instant as the following hour', function () {
    Queue::fake();

    // ...and the tick at 03:30 EDT (07:30 UTC) dispatches BOTH the 02:30
    // scheduler, whose slot no longer exists, and the ordinary 03:30 one.
    Carbon::setTestNow('2026-03-08 07:30:00');

    $skipped = Scheduler::factory()->timezone('America/New_York')->cron('30 2 * * *')->create();
    $ordinary = Scheduler::factory()->timezone('America/New_York')->cron('30 3 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 2);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($skipped));
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($ordinary));
});

it('dispatches an autumn back scheduler twice across the repeated hour', function () {
    // Europe/London repeats 01:00-02:00 on 2026-10-25, so 01:30 local occurs at
    // 00:30 UTC and again at 01:30 UTC. The tick dispatches on both passes: one
    // scheduler, two runs, an hour apart. prevent_overlap cannot deduplicate
    // them because the first run's lock has long since been released.
    Queue::fake();

    Carbon::setTestNow('2026-10-25 00:30:00');

    $scheduler = Scheduler::factory()->timezone('Europe/London')->cron('30 1 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Carbon::setTestNow('2026-10-25 01:30:00');

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertPushed(RunSchedulerJob::class, 2);
    Queue::assertPushed(RunSchedulerJob::class, fn (RunSchedulerJob $job) => $job->scheduler->is($scheduler));
});

it('does not dispatch the autumn back scheduler on either side of the repeated hour', function () {
    Queue::fake();

    Carbon::setTestNow('2026-10-25 02:30:00'); // 02:30 GMT, an hour past the repeat

    Scheduler::factory()->timezone('Europe/London')->cron('30 1 * * *')->create();

    $this->artisan('scheduler-manager:tick')->assertSuccessful();

    Queue::assertNothingPushed();
});
