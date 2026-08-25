<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerRuns;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);
});

it('renders the run history newest first', function () {
    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    $oldest = SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subHours(3),
    ]);

    $newest = SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subMinutes(5),
    ]);

    $middle = SchedulerRun::factory()->skipped()->create([
        'scheduler_id' => $scheduler->id,
        'started_at' => now()->subHour(),
    ]);

    $component = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->assertOk()
        ->assertSee('Nightly report');

    expect($component->viewData('runs')->pluck('id')->all())
        ->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('shows only the runs of the scheduler it was mounted with', function () {
    $scheduler = Scheduler::factory()->create();
    $other = Scheduler::factory()->create();

    $mine = SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);
    SchedulerRun::factory()->successful()->create(['scheduler_id' => $other->id]);

    $component = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler]);

    expect($component->viewData('runs')->pluck('id')->all())->toBe([$mine->id]);
});

it('lists every scheduler when mounted without one', function () {
    SchedulerRun::factory()->successful()->create(['started_at' => now()->subMinute()]);
    SchedulerRun::factory()->failed()->create(['started_at' => now()]);

    $component = Livewire::test(SchedulerRuns::class)->assertOk();

    expect($component->viewData('runs')->total())->toBe(2);
});

it('filters by status', function () {
    $scheduler = Scheduler::factory()->create();

    $failed = SchedulerRun::factory()->failed()->create(['scheduler_id' => $scheduler->id]);
    $succeeded = SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);
    SchedulerRun::factory()->skipped()->create(['scheduler_id' => $scheduler->id]);

    $component = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->set('status', RunStatus::Failed->value);

    expect($component->viewData('runs')->pluck('id')->all())->toBe([$failed->id]);

    $component->set('status', RunStatus::Success->value);

    expect($component->viewData('runs')->pluck('id')->all())->toBe([$succeeded->id]);

    $component->set('status', '');

    expect($component->viewData('runs')->total())->toBe(3);
});

it('paginates using the configured page size', function () {
    config()->set('scheduler-manager.ui.per_page', 2);

    $scheduler = Scheduler::factory()->create();

    SchedulerRun::factory()->count(5)->successful()->create(['scheduler_id' => $scheduler->id]);

    $runs = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])->viewData('runs');

    expect($runs->perPage())->toBe(2)
        ->and($runs->count())->toBe(2)
        ->and($runs->total())->toBe(5);
});

it('expands and collapses a run', function () {
    $scheduler = Scheduler::factory()->create();

    $run = SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'output' => 'Report written to storage.',
    ]);

    $component = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->assertSet('expanded', null)
        ->assertDontSee('Report written to storage.');

    $component->call('expand', $run->id)
        ->assertSet('expanded', $run->id)
        ->assertSee('Report written to storage.');

    $component->call('expand', $run->id)
        ->assertSet('expanded', null)
        ->assertDontSee('Report written to storage.');
});

it('expands one run at a time', function () {
    $scheduler = Scheduler::factory()->create();

    $first = SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);
    $second = SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);

    Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->call('expand', $first->id)
        ->call('expand', $second->id)
        ->assertSet('expanded', $second->id);
});

/*
 * Command output is attacker-influenced in the general case: anything a
 * scheduled task prints — a filename, a URL it fetched, an error message
 * quoting user input — ends up here verbatim. Rendering it unescaped would
 * hand script execution to whoever controls that input, on a page whose
 * viewers can run arbitrary Artisan commands.
 */
it('escapes run output rather than rendering it as markup', function () {
    $scheduler = Scheduler::factory()->create();

    $run = SchedulerRun::factory()->failed()->create([
        'scheduler_id' => $scheduler->id,
        'output' => 'Fetched <script>alert(1)</script> from the queue',
        'exception' => 'RuntimeException: <img src=x onerror="alert(2)"> was rejected',
    ]);

    $html = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->call('expand', $run->id)
        ->html();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('&lt;img src=x onerror=');
});

it('escapes a scheduler name rather than rendering it as markup', function () {
    $scheduler = Scheduler::factory()->create(['name' => '<script>alert(3)</script>']);

    SchedulerRun::factory()->successful()->create(['scheduler_id' => $scheduler->id]);

    $html = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])->html();

    expect($html)->not->toContain('<script>alert(3)</script>');
});

it('truncates unbounded output so one noisy run cannot swamp the page', function () {
    $scheduler = Scheduler::factory()->create();

    $run = SchedulerRun::factory()->successful()->create([
        'scheduler_id' => $scheduler->id,
        'output' => str_repeat('a', 25000),
    ]);

    $html = Livewire::test(SchedulerRuns::class, ['scheduler' => $scheduler])
        ->call('expand', $run->id)
        ->html();

    expect($html)->toContain('Output truncated at 20,000 characters.')
        ->and(substr_count($html, 'a'))->toBeLessThan(25000);
});
