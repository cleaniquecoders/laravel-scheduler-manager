<?php

use Illuminate\Support\Facades\Route;

it('mounts the ui at the site root when the prefix is empty', function () {
    expect(route('scheduler-manager.index', absolute: false))->toBe('/')
        ->and(route('scheduler-manager.dashboard', absolute: false))->toBe('/dashboard')
        ->and(Route::getRoutes()->getByName('scheduler-manager.index')->gatherMiddleware())
        ->toContain('web', 'auth');
});
