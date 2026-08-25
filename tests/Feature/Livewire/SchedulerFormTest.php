<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerForm;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    Gate::define(config('scheduler-manager.gate'), fn ($user = null) => true);
});

it('creates a scheduler', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('type', SchedulerType::Artisan->value)
        ->set('identifier', 'cache:clear')
        ->set('payload', '{"queue":"reports"}')
        ->set('cron', '0 2 * * *')
        ->set('timezone', 'Asia/Kuala_Lumpur')
        ->set('prevent_overlap', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('scheduler-saved', name: 'Nightly report')
        ->assertRedirect(route('scheduler-manager.index'));

    $scheduler = Scheduler::query()->firstOrFail();

    expect($scheduler->name)->toBe('Nightly report')
        ->and($scheduler->type)->toBe(SchedulerType::Artisan)
        ->and($scheduler->identifier)->toBe('cache:clear')
        ->and($scheduler->payload)->toBe(['queue' => 'reports'])
        ->and($scheduler->cron)->toBe('0 2 * * *')
        ->and($scheduler->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($scheduler->prevent_overlap)->toBeTrue()
        ->and($scheduler->enabled)->toBeTrue()
        ->and($scheduler->next_run_at)->not->toBeNull();
});

it('stores no payload when the field is left empty', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('identifier', 'cache:clear')
        ->set('payload', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Scheduler::query()->firstOrFail()->payload)->toBeNull();
});

it('rejects a blank name', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', '')
        ->set('identifier', 'cache:clear')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Scheduler::count())->toBe(0);
});

it('rejects an invalid cron expression', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('identifier', 'cache:clear')
        ->set('cron', 'every other tuesday')
        ->call('save')
        ->assertHasErrors('cron');

    expect(Scheduler::count())->toBe(0);
});

it('rejects an unknown timezone', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('identifier', 'cache:clear')
        ->set('timezone', 'Mars/Olympus')
        ->call('save')
        ->assertHasErrors('timezone');

    expect(Scheduler::count())->toBe(0);
});

it('rejects an action identifier that is not whitelisted in config', function () {
    config()->set('scheduler-manager.actions', ['send-report' => fn () => null]);

    Livewire::test(SchedulerForm::class)
        ->set('name', 'Rogue action')
        ->set('type', SchedulerType::Action->value)
        ->set('identifier', 'App\Actions\DeleteEverything')
        ->call('save')
        ->assertHasErrors('identifier');

    expect(Scheduler::count())->toBe(0);
});

it('accepts an action identifier that is whitelisted in config', function () {
    config()->set('scheduler-manager.actions', ['send-report' => fn () => null]);

    Livewire::test(SchedulerForm::class)
        ->set('name', 'Send the report')
        ->set('type', SchedulerType::Action->value)
        ->set('identifier', 'send-report')
        ->call('save')
        ->assertHasNoErrors();

    expect(Scheduler::query()->firstOrFail()->type)->toBe(SchedulerType::Action);
});

it('rejects an artisan command that is not registered', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('type', SchedulerType::Artisan->value)
        ->set('identifier', 'not:a:command')
        ->call('save')
        ->assertHasErrors('identifier');

    expect(Scheduler::count())->toBe(0);
});

it('rejects an artisan command outside the allow-list', function () {
    config()->set('scheduler-manager.allowed_commands', ['queue:work']);

    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('identifier', 'cache:clear')
        ->call('save')
        ->assertHasErrors('identifier');

    expect(Scheduler::count())->toBe(0);
});

it('rejects a malformed json payload', function () {
    Livewire::test(SchedulerForm::class)
        ->set('name', 'Nightly report')
        ->set('identifier', 'cache:clear')
        ->set('payload', '{"queue": reports')
        ->call('save')
        ->assertHasErrors(['payload' => 'json']);

    expect(Scheduler::count())->toBe(0);
});

it('loads the existing values when editing', function () {
    $scheduler = Scheduler::factory()->create([
        'name' => 'Nightly report',
        'identifier' => 'cache:clear',
        'payload' => ['queue' => 'reports'],
        'cron' => '0 2 * * *',
        'timezone' => 'Asia/Kuala_Lumpur',
        'prevent_overlap' => true,
    ]);

    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->assertOk()
        ->assertSet('name', 'Nightly report')
        ->assertSet('type', SchedulerType::Artisan->value)
        ->assertSet('identifier', 'cache:clear')
        ->assertSet('cron', '0 2 * * *')
        ->assertSet('timezone', 'Asia/Kuala_Lumpur')
        ->assertSet('enabled', true)
        ->assertSet('prevent_overlap', true)
        ->assertSee('Edit Scheduler');

    expect(json_decode(Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])->get('payload'), true))
        ->toBe(['queue' => 'reports']);
});

it('updates an existing scheduler instead of creating another', function () {
    $scheduler = Scheduler::factory()->create(['name' => 'Nightly report']);

    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->set('name', 'Renamed report')
        ->set('enabled', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('scheduler-manager.index'));

    expect(Scheduler::count())->toBe(1)
        ->and($scheduler->fresh()->name)->toBe('Renamed report')
        ->and($scheduler->fresh()->enabled)->toBeFalse();
});

it('recalculates the next run when the cron changes', function () {
    $scheduler = Scheduler::factory()->create([
        'cron' => '0 0 1 1 *',
        'timezone' => 'UTC',
    ]);

    $before = $scheduler->fresh()->next_run_at;

    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->set('cron', '*/5 * * * *')
        ->call('save')
        ->assertHasNoErrors();

    $after = $scheduler->fresh()->next_run_at;

    expect($after)->not->toBeNull()
        ->and($after->equalTo($before))->toBeFalse()
        ->and($after->lessThan(now()->addMinutes(6)))->toBeTrue()
        ->and($after->greaterThanOrEqualTo(now()))->toBeTrue();
});

it('applies a cron preset', function () {
    Livewire::test(SchedulerForm::class)
        ->assertSet('cron', '* * * * *')
        ->call('applyPreset', '0 0 * * 1')
        ->assertSet('cron', '0 0 * * 1');
});

it('previews the next five runs for a valid expression', function () {
    $component = Livewire::test(SchedulerForm::class)
        ->set('timezone', 'UTC')
        ->set('cron', '0 * * * *');

    $upcoming = $component->viewData('upcoming');

    expect($upcoming)->toHaveCount(5)
        ->and($upcoming[0])->toEndWith('UTC')
        ->and($upcoming)->toBe(array_values(array_unique($upcoming)));

    $timestamps = array_map(
        fn (string $entry) => Carbon::parse(substr($entry, 0, 16), 'UTC'),
        $upcoming
    );

    // Hourly, so each preview entry is one hour after the previous one.
    expect($timestamps[1]->diffInMinutes($timestamps[0]))->toBe(-60.0);
});

it('previews nothing for an expression it cannot parse', function () {
    $component = Livewire::test(SchedulerForm::class)
        ->set('cron', 'every other tuesday');

    expect($component->viewData('upcoming'))->toBe([])
        ->and($component->html())->toContain('No preview available yet');
});

it('previews nothing, rather than erroring, for a timezone that does not exist', function () {
    $component = Livewire::test(SchedulerForm::class)
        ->set('timezone', 'Mars/Olympus')
        ->assertOk();

    expect($component->viewData('upcoming'))->toBe([]);
});

it('deletes the scheduler being edited', function () {
    $scheduler = Scheduler::factory()->create();

    Livewire::test(SchedulerForm::class, ['scheduler' => $scheduler])
        ->call('delete')
        ->assertRedirect(route('scheduler-manager.index'));

    expect(Scheduler::count())->toBe(0);
});

it('does nothing when deleting from the create form', function () {
    Livewire::test(SchedulerForm::class)
        ->call('delete')
        ->assertNoRedirect();
});
