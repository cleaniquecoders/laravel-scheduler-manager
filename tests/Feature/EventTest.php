<?php

use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunFailed;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunSkipped;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunStarted;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunSucceeded;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * A bare Event::fake() also swallows Eloquent's model events, which is what
 * Traitify's InteractsWithUuid hooks into — schedulers would then be inserted
 * with a null uuid. Fake only the package events.
 */
function fakeSchedulerEvents(): void
{
    Event::fake([
        SchedulerRunStarted::class,
        SchedulerRunSucceeded::class,
        SchedulerRunFailed::class,
        SchedulerRunSkipped::class,
    ]);
}

it('dispatches started and succeeded events on a successful run', function () {
    fakeSchedulerEvents();

    $scheduler = Scheduler::factory()->create();

    (new RunSchedulerJob($scheduler))->handle();

    Event::assertDispatched(SchedulerRunStarted::class);
    Event::assertDispatched(SchedulerRunSucceeded::class);
    Event::assertNotDispatched(SchedulerRunFailed::class);
});

it('dispatches a failed event when the run raises', function () {
    fakeSchedulerEvents();

    config()->set('scheduler-manager.actions', []);
    $scheduler = Scheduler::factory()->action('unknown')->create();

    (new RunSchedulerJob($scheduler))->handle();

    Event::assertDispatched(SchedulerRunFailed::class);
});

it('dispatches a skipped event when overlapping is prevented', function () {
    fakeSchedulerEvents();

    $scheduler = Scheduler::factory()->preventingOverlap()->create();

    $lock = Cache::lock("scheduler_manager:{$scheduler->id}:lock", 300);
    $lock->get();

    (new RunSchedulerJob($scheduler))->handle();

    Event::assertDispatched(SchedulerRunSkipped::class);
    Event::assertNotDispatched(SchedulerRunFailed::class);

    $lock->release();
});
