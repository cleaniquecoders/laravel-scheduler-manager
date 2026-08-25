<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

it('registers no routes when the ui is disabled', function () {
    foreach (['index', 'dashboard', 'create', 'edit', 'runs'] as $name) {
        expect(Route::getRoutes()->getByName('scheduler-manager.'.$name))->toBeNull();
    }
});

it('registers no livewire components when the ui is disabled', function () {
    expect(fn () => Livewire\Livewire::new('scheduler-manager::dashboard'))->toThrow(Exception::class);
});

it('still registers the scheduling engine when the ui is disabled', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('scheduler-manager:tick');
});
