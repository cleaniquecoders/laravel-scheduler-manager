<?php

use Carbon\CarbonImmutable;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Carbon;

/*
 * Every case here pins the clock with Carbon::setTestNow() and works on an
 * unsaved model, so the cron maths is exercised without touching the database
 * and without any dependency on real wall-clock time.
 */

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    Carbon::setTestNow('2026-01-01 10:00:00');
});

function cronScheduler(string $cron, ?string $timezone = 'UTC'): Scheduler
{
    return new Scheduler(['cron' => $cron, 'timezone' => $timezone]);
}

function atUtc(string $moment): Carbon
{
    return Carbon::parse($moment, 'UTC');
}

/*
 * ---------------------------------------------------------------------------
 * 1. Expression coverage
 * ---------------------------------------------------------------------------
 */

it('reports a scheduler as due only at a matching instant', function (string $cron, string $due, string $notDue) {
    $scheduler = cronScheduler($cron);

    expect($scheduler->isDue(atUtc($due)))->toBeTrue()
        ->and($scheduler->isDue(atUtc($notDue)))->toBeFalse();
})->with([
    'hourly' => ['0 * * * *', '2026-01-01 11:00:00', '2026-01-01 11:30:00'],
    'daily' => ['0 3 * * *', '2026-01-01 03:00:00', '2026-01-01 04:00:00'],
    // 2026-01-05 is a Monday; 2026-01-06 is a Tuesday.
    'weekly' => ['0 0 * * 1', '2026-01-05 00:00:00', '2026-01-06 00:00:00'],
    'monthly' => ['0 0 1 * *', '2026-02-01 00:00:00', '2026-02-02 00:00:00'],
    'step' => ['*/15 * * * *', '2026-01-01 10:15:00', '2026-01-01 10:20:00'],
    'list' => ['0 9,17 * * *', '2026-01-01 17:00:00', '2026-01-01 13:00:00'],
]);

it('also matches the second entry of a list expression', function () {
    expect(cronScheduler('0 9,17 * * *')->isDue(atUtc('2026-01-01 09:00:00')))->toBeTrue();
});

it('ignores seconds when deciding whether a scheduler is due', function () {
    // A tick rarely lands exactly on :00, so the minute must be what matters.
    expect(cronScheduler('0 * * * *')->isDue(atUtc('2026-01-01 11:00:47')))->toBeTrue();
});

it('calculates the next run for each expression shape', function (string $cron, string $expected) {
    // Clock is pinned at 2026-01-01 10:00:00 UTC.
    expect(cronScheduler($cron)->calculateNextRunAt()->toDateTimeString())->toBe($expected);
})->with([
    'hourly' => ['0 * * * *', '2026-01-01 11:00:00'],
    'daily' => ['0 3 * * *', '2026-01-02 03:00:00'],
    'weekly' => ['0 0 * * 1', '2026-01-05 00:00:00'],
    'monthly' => ['0 0 1 * *', '2026-02-01 00:00:00'],
    'step' => ['*/15 * * * *', '2026-01-01 10:15:00'],
    'list' => ['0 9,17 * * *', '2026-01-01 17:00:00'],
]);

it('returns the following slot when the clock sits exactly on a match', function () {
    Carbon::setTestNow('2026-01-01 10:15:00');

    $scheduler = cronScheduler('*/15 * * * *');

    expect($scheduler->isDue())->toBeTrue()
        ->and($scheduler->calculateNextRunAt()->toDateTimeString())->toBe('2026-01-01 10:30:00');
});

/*
 * ---------------------------------------------------------------------------
 * 4. Invalid cron
 * ---------------------------------------------------------------------------
 */

it('treats an unparseable cron as invalid, never due, and without a next run', function (string $cron) {
    $scheduler = cronScheduler($cron);

    expect($scheduler->isCronValid())->toBeFalse()
        ->and($scheduler->calculateNextRunAt())->toBeNull()
        ->and($scheduler->isDue())->toBeFalse()
        ->and($scheduler->isDue(atUtc('2026-01-01 00:00:00')))->toBeFalse();
})->with([
    'prose' => ['not-a-cron'],
    'too few fields' => ['* * *'],
    'out of range minute' => ['99 * * * *'],
    'empty' => [''],
]);

it('accepts the expression shapes the package advertises', function (string $cron) {
    expect(cronScheduler($cron)->isCronValid())->toBeTrue();
})->with(['* * * * *', '0 * * * *', '0 3 * * *', '0 0 * * 1', '0 0 1 * *', '*/15 * * * *', '0 9,17 * * *', '0 0 1-7 * 1']);

/*
 * ---------------------------------------------------------------------------
 * 5. Timezone correctness
 * ---------------------------------------------------------------------------
 */

it('fires the same cron at different utc instants in different timezones', function () {
    $kualaLumpur = cronScheduler('0 8 * * *', 'Asia/Kuala_Lumpur');
    $london = cronScheduler('0 8 * * *', 'Europe/London');

    // 08:00 in Kuala Lumpur is 00:00 UTC; 08:00 in London (GMT in January) is 08:00 UTC.
    expect($kualaLumpur->isDue(atUtc('2026-01-01 00:00:00')))->toBeTrue()
        ->and($london->isDue(atUtc('2026-01-01 00:00:00')))->toBeFalse()
        ->and($london->isDue(atUtc('2026-01-01 08:00:00')))->toBeTrue()
        ->and($kualaLumpur->isDue(atUtc('2026-01-01 08:00:00')))->toBeFalse();

    expect($kualaLumpur->calculateNextRunAt()->toDateTimeString())->toBe('2026-01-02 00:00:00')
        ->and($london->calculateNextRunAt()->toDateTimeString())->toBe('2026-01-02 08:00:00');
});

it('evaluates an instant in the scheduler timezone regardless of how it is expressed', function () {
    $scheduler = cronScheduler('30 0 * * *', 'Asia/Kuala_Lumpur');

    $asUtc = atUtc('2025-12-31 16:30:00');
    $asLocal = Carbon::parse('2026-01-01 00:30:00', 'Asia/Kuala_Lumpur');

    expect($asUtc->equalTo($asLocal))->toBeTrue()
        ->and($scheduler->isDue($asUtc))->toBeTrue()
        ->and($scheduler->isDue($asLocal))->toBeTrue();
});

it('crosses the date boundary when the scheduler timezone is ahead of the application', function () {
    $scheduler = cronScheduler('30 0 * * *', 'Asia/Kuala_Lumpur');

    // Clock is 2026-01-01 10:00 UTC, which is 18:00 on 2026-01-01 in Kuala Lumpur,
    // so the next local slot is 00:30 on 2026-01-02 -- 16:30 UTC on 2026-01-01.
    // The stored value therefore carries a different calendar date to the one
    // an operator reading the cron in Kuala Lumpur would expect.
    expect($scheduler->calculateNextRunAt()->toDateTimeString())->toBe('2026-01-01 16:30:00');
});

it('crosses the date boundary backwards when the scheduler timezone is behind the application', function () {
    $scheduler = cronScheduler('30 23 * * *', 'America/New_York');

    // 23:30 on 2026-01-01 in New York (EST, -05:00) is 04:30 UTC on 2026-01-02.
    expect($scheduler->calculateNextRunAt()->toDateTimeString())->toBe('2026-01-02 04:30:00')
        ->and($scheduler->isDue(atUtc('2026-01-02 04:30:00')))->toBeTrue();
});

it('falls back to the application timezone when the scheduler has none', function () {
    config()->set('app.timezone', 'Asia/Kuala_Lumpur');

    $scheduler = cronScheduler('30 0 * * *', null);

    expect($scheduler->resolveTimezone())->toBe('Asia/Kuala_Lumpur')
        ->and($scheduler->isDue(atUtc('2025-12-31 16:30:00')))->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * 5b. DST transitions
 * ---------------------------------------------------------------------------
 */

it('shifts a spring forward slot that does not exist into the hour after the gap', function () {
    // America/New_York moves 02:00 EST -> 03:00 EDT on 2026-03-08, so 02:30
    // never happens locally that day.
    //
    // OBSERVED: dragonmantank/cron-expression does not skip the run. It lands
    // on 03:30 EDT (07:30 UTC) -- one hour later in absolute terms than the
    // 06:30 UTC it would have used the day before. The job is preserved rather
    // than dropped, which is the safer of the two choices, but see the
    // duplicate-slot case below for the cost.
    $scheduler = cronScheduler('30 2 * * *', 'America/New_York');

    $next = $scheduler->calculateNextRunAt(Carbon::parse('2026-03-08 00:00:00', 'America/New_York'));

    expect($next->toDateTimeString())->toBe('2026-03-08 07:30:00');

    // The day before and the day after are ordinary 02:30 local runs.
    expect($scheduler->calculateNextRunAt(Carbon::parse('2026-03-07 00:00:00', 'America/New_York'))->toDateTimeString())
        ->toBe('2026-03-07 07:30:00')
        ->and($scheduler->calculateNextRunAt(Carbon::parse('2026-03-09 00:00:00', 'America/New_York'))->toDateTimeString())
        ->toBe('2026-03-09 06:30:00');
});

it('reports a skipped spring forward slot as due during the hour after the gap', function () {
    // SURPRISING: because 02:30 was folded forward, "30 2 * * *" is due at
    // 03:30 EDT -- the same instant at which "30 3 * * *" is due. Two
    // schedulers written for different times therefore run together on this
    // one day of the year.
    $skipped = cronScheduler('30 2 * * *', 'America/New_York');
    $normal = cronScheduler('30 3 * * *', 'America/New_York');

    $duringGap = atUtc('2026-03-08 06:30:00');   // 01:30 EST, before the jump
    $afterGap = atUtc('2026-03-08 07:30:00');    // 03:30 EDT, after the jump

    expect($skipped->isDue($duringGap))->toBeFalse()
        ->and($skipped->isDue($afterGap))->toBeTrue()
        ->and($normal->isDue($afterGap))->toBeTrue();
});

it('reports an autumn back slot as due at both occurrences of the repeated hour', function () {
    // Europe/London moves 02:00 BST -> 01:00 GMT on 2026-10-25, so 01:30 local
    // happens twice, one hour apart.
    //
    // SURPRISING AND ARGUABLY WRONG: "30 1 * * *" is due at BOTH instants, so a
    // per-minute tick dispatches this scheduler twice on that day. prevent_overlap
    // does not help -- the two runs are an hour apart, so the lock is long gone.
    $scheduler = cronScheduler('30 1 * * *', 'Europe/London');

    $firstPass = atUtc('2026-10-25 00:30:00');   // 01:30 BST (+01:00)
    $secondPass = atUtc('2026-10-25 01:30:00');  // 01:30 GMT (+00:00)

    expect($firstPass->copy()->setTimezone('Europe/London')->format('H:i'))->toBe('01:30')
        ->and($secondPass->copy()->setTimezone('Europe/London')->format('H:i'))->toBe('01:30')
        ->and($scheduler->isDue($firstPass))->toBeTrue()
        ->and($scheduler->isDue($secondPass))->toBeTrue();
});

it('walks the repeated autumn back hour one occurrence at a time', function () {
    $scheduler = cronScheduler('30 1 * * *', 'Europe/London');

    // From just before the first 01:30 the next run is the BST occurrence...
    expect($scheduler->calculateNextRunAt(atUtc('2026-10-25 00:00:00'))->toDateTimeString())
        ->toBe('2026-10-25 00:30:00')
        // ...and from that instant the next run is the GMT repeat an hour later,
        // rather than 01:30 the following day.
        ->and($scheduler->calculateNextRunAt(atUtc('2026-10-25 00:30:00'))->toDateTimeString())
        ->toBe('2026-10-25 01:30:00')
        ->and($scheduler->calculateNextRunAt(atUtc('2026-10-25 01:30:00'))->toDateTimeString())
        ->toBe('2026-10-26 01:30:00');
});

/*
 * ---------------------------------------------------------------------------
 * 6. next_run_at is stored in the application timezone
 *    (the base case lives in tests/Feature/NextRunAtTest.php; these are the
 *    DST and date-boundary variants)
 * ---------------------------------------------------------------------------
 */

it('returns next run in the application timezone across a dst transition', function () {
    config()->set('app.timezone', 'Asia/Kuala_Lumpur');

    $scheduler = cronScheduler('30 1 * * *', 'Europe/London');

    // 01:30 BST on 2026-10-25 is 00:30 UTC, which is 08:30 in Kuala Lumpur.
    $next = $scheduler->calculateNextRunAt(atUtc('2026-10-25 00:00:00'));

    expect($next->getTimezone()->getName())->toBe('Asia/Kuala_Lumpur')
        ->and($next->toDateTimeString())->toBe('2026-10-25 08:30:00');
});

it('returns next run in the application timezone when the local date differs', function () {
    config()->set('app.timezone', 'America/New_York');

    $scheduler = cronScheduler('30 0 * * *', 'Asia/Kuala_Lumpur');

    // 00:30 on 2026-01-02 in Kuala Lumpur is 16:30 UTC on 2026-01-01, which is
    // 11:30 on 2026-01-01 in New York: three different calendar readings of one
    // instant, and the stored one is always the application's.
    $next = $scheduler->calculateNextRunAt(atUtc('2026-01-01 10:00:00'));

    expect($next->getTimezone()->getName())->toBe('America/New_York')
        ->and($next->toDateTimeString())->toBe('2026-01-01 11:30:00');
});

/*
 * ---------------------------------------------------------------------------
 * 7. Explicit $from argument
 * ---------------------------------------------------------------------------
 */

it('honours an explicit from argument instead of the current time', function () {
    // Clock is pinned at 2026-01-01 10:00 UTC; the argument must win.
    $next = cronScheduler('0 3 * * *')->calculateNextRunAt(atUtc('2026-06-15 09:00:00'));

    expect($next->toDateTimeString())->toBe('2026-06-16 03:00:00');
});

it('honours a from argument expressed in any timezone', function () {
    $scheduler = cronScheduler('0 8 * * *', 'Asia/Kuala_Lumpur');

    $fromUtc = atUtc('2026-01-01 10:00:00');
    $fromLocal = Carbon::parse('2026-01-01 18:00:00', 'Asia/Kuala_Lumpur');

    // Same instant, two representations, one answer: 08:00 KL on 2026-01-02.
    expect($scheduler->calculateNextRunAt($fromUtc)->toDateTimeString())->toBe('2026-01-02 00:00:00')
        ->and($scheduler->calculateNextRunAt($fromLocal)->toDateTimeString())->toBe('2026-01-02 00:00:00');
});

it('does not mutate the from argument it is handed', function () {
    $from = atUtc('2026-01-01 10:00:00');

    cronScheduler('0 8 * * *', 'Asia/Kuala_Lumpur')->calculateNextRunAt($from);

    expect($from->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($from->getTimezone()->getName())->toBe('UTC');
});

it('accepts an immutable from argument', function () {
    $from = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');

    expect(cronScheduler('0 3 * * *')->calculateNextRunAt($from)->toDateTimeString())
        ->toBe('2026-01-02 03:00:00');
});
