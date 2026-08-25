<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidCronExpression;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidSchedulerIdentifier;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidTimezone;
use Illuminate\Support\Facades\Validator;

function failures(array $data, array $rules): array
{
    return Validator::make($data, $rules)->errors()->all();
}

it('accepts a valid cron expression', function () {
    expect(failures(['cron' => '*/5 * * * *'], ['cron' => [new ValidCronExpression]]))->toBeEmpty();
});

it('rejects an invalid cron expression', function () {
    expect(failures(['cron' => 'nope'], ['cron' => [new ValidCronExpression]]))
        ->toContain('The cron must be a valid cron expression.');
});

it('accepts a real timezone', function () {
    expect(failures(['timezone' => 'Asia/Kuala_Lumpur'], ['timezone' => [new ValidTimezone]]))->toBeEmpty();
});

it('rejects an unknown timezone', function () {
    expect(failures(['timezone' => 'Mars/Olympus'], ['timezone' => [new ValidTimezone]]))
        ->toContain('The timezone must be a valid timezone identifier.');
});

it('allows an empty timezone so the app default applies', function () {
    expect(failures(['timezone' => null], ['timezone' => [new ValidTimezone]]))->toBeEmpty();
});

it('rejects an action identifier that is not whitelisted', function () {
    config()->set('scheduler-manager.actions', ['known' => fn () => null]);

    $rule = new ValidSchedulerIdentifier(SchedulerType::Action);

    expect(failures(['identifier' => 'unknown'], ['identifier' => [$rule]]))
        ->toContain('The identifier must be one of the actions registered in config/scheduler-manager.php.');

    expect(failures(['identifier' => 'known'], ['identifier' => [$rule]]))->toBeEmpty();
});

it('rejects an unregistered artisan command', function () {
    $rule = new ValidSchedulerIdentifier(SchedulerType::Artisan);

    expect(failures(['identifier' => 'not:a:command'], ['identifier' => [$rule]]))
        ->toContain('The identifier must be a registered Artisan command.');

    expect(failures(['identifier' => 'cache:clear'], ['identifier' => [$rule]]))->toBeEmpty();
});

it('rejects a command outside the allow-list', function () {
    config()->set('scheduler-manager.allowed_commands', ['cache:clear']);

    $rule = new ValidSchedulerIdentifier(SchedulerType::Artisan);

    expect(failures(['identifier' => 'config:clear'], ['identifier' => [$rule]]))
        ->toContain('The identifier is not permitted by scheduler-manager.allowed_commands.');
});
