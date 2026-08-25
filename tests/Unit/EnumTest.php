<?php

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\Traitify\Contracts\Enum;

it('exposes scheduler types as select options', function () {
    expect(SchedulerType::values())->toBe(['artisan', 'action'])
        ->and(SchedulerType::labels())->toBe(['Artisan Command', 'Action Class']);

    $options = SchedulerType::options();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toHaveKeys(['value', 'label', 'description'])
        ->and($options[0]['value'])->toBe('artisan');
});

it('exposes run statuses as select options', function () {
    expect(RunStatus::values())->toBe(['running', 'success', 'failed', 'skipped']);

    foreach (RunStatus::options() as $option) {
        expect($option['label'])->not->toBeEmpty()
            ->and($option['description'])->not->toBeEmpty();
    }
});

it('distinguishes terminal run statuses', function () {
    expect(RunStatus::Running->isTerminal())->toBeFalse()
        ->and(RunStatus::Success->isTerminal())->toBeTrue()
        ->and(RunStatus::Failed->isTerminal())->toBeTrue()
        ->and(RunStatus::Skipped->isTerminal())->toBeTrue();
});

it('implements the traitify enum contract', function () {
    expect(SchedulerType::Artisan)->toBeInstanceOf(Enum::class)
        ->and(RunStatus::Success)->toBeInstanceOf(Enum::class);
});
