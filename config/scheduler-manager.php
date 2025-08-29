<?php

return [
    // Map action keys to resolvable classes or callables
    'actions' => [
        // 'send-report' => App\Actions\SendReport::class,
    ],

    // Route and UI settings
    'route_prefix' => 'scheduler-manager',
    'middleware' => ['web', 'auth'],

    // Default cache lock seconds when prevent_overlap is enabled
    'lock_ttl' => 3600,
];
