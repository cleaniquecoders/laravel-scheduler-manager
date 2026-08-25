<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Exceptions\ActionNotAllowedException;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Runners\ActionRunner;

class SpyAction
{
    public static bool $ran = false;

    public function handle(): string
    {
        static::$ran = true;

        return 'spy ran';
    }
}

class ForbiddenAction
{
    public static bool $ran = false;

    public function handle(): void
    {
        static::$ran = true;
    }
}

beforeEach(function () {
    SpyAction::$ran = false;
    ForbiddenAction::$ran = false;
});

it('runs an action that is present in the whitelist', function () {
    config()->set('scheduler-manager.actions', ['spy' => SpyAction::class]);

    $scheduler = Scheduler::factory()->action('spy')->create();

    (new RunSchedulerJob($scheduler))->handle();

    expect(SpyAction::$ran)->toBeTrue()
        ->and($scheduler->runs()->first()->status)->toBe(RunStatus::Success)
        ->and($scheduler->runs()->first()->output)->toBe('spy ran');
});

it('refuses to resolve a class name that is not whitelisted', function () {
    config()->set('scheduler-manager.actions', []);

    $scheduler = Scheduler::factory()->action(ForbiddenAction::class)->create();

    (new RunSchedulerJob($scheduler))->handle();

    expect(ForbiddenAction::$ran)->toBeFalse();

    $run = $scheduler->runs()->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->exception)->toContain('not registered in the scheduler-manager.actions whitelist');
});

it('throws when the runner is given an unlisted identifier', function () {
    config()->set('scheduler-manager.actions', []);

    $scheduler = Scheduler::factory()->action('nope')->create();

    (new ActionRunner)->for($scheduler)->execute();
})->throws(ActionNotAllowedException::class);

it('supports a closure registered directly in the whitelist', function () {
    config()->set('scheduler-manager.actions', [
        'inline' => fn () => 'closure ran',
    ]);

    $scheduler = Scheduler::factory()->action('inline')->create();

    (new RunSchedulerJob($scheduler))->handle();

    expect($scheduler->runs()->first()->output)->toBe('closure ran');
});

it('rejects an artisan command outside the allow-list', function () {
    config()->set('scheduler-manager.allowed_commands', ['cache:clear']);

    $scheduler = Scheduler::factory()->create(['identifier' => 'config:clear']);

    (new RunSchedulerJob($scheduler))->handle();

    $run = $scheduler->runs()->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->exception)->toContain('not permitted by scheduler-manager.allowed_commands');
});

it('permits any command when the allow-list is empty', function () {
    config()->set('scheduler-manager.allowed_commands', []);

    $scheduler = Scheduler::factory()->create(['identifier' => 'config:clear']);

    (new RunSchedulerJob($scheduler))->handle();

    expect($scheduler->runs()->first()->status)->toBe(RunStatus::Success);
});
