<?php

/**
 * Scheduler Manager configuration.
 *
 * This file returns an array of configuration values used by the
 * Laravel Scheduler Manager package. The shape below is provided for
 * IDE autocompletion and static analysis tools (PHPStan/Psalm).
 *
 * @return array{
 *     actions: array<string, class-string|callable>,
 *     route_prefix: string,
 *     middleware: list<string>,
 *     lock_ttl: int,
 * }
 *
 * Keys:
 * - actions: map action keys (string) to a resolvable class name or
 *   a callable. Action entries are used when a scheduler has type
 *   "action" and the scheduler's `identifier` refers to one of these
 *   keys. Example: 'send-report' => App\Actions\SendReport::class
 *
 * - route_prefix: URL prefix used when the package registers routes
 *   for the UI (e.g. /scheduler-manager). Can be changed to mount the
 *   UI under a different path or grouped route.
 *
 * - middleware: list of middleware applied to the package routes. By
 *   default the UI is protected by ['web','auth'] — change as needed
 *   for your app's auth/guard configuration.
 *
 * - lock_ttl: default lock TTL in seconds used when `prevent_overlap`
 *   is enabled on a scheduler. This value is applied to the cache
 *   lock acquired before running a scheduler; increase it if your
 *   scheduled tasks can run longer than the default.
 */

return [
    /**
     * Map action keys to resolvable classes or callables.
     *
     * Use this when a scheduler has `type === 'action'` and the
     * scheduler's `identifier` refers to one of these keys.
     * Values may be a class-string (resolvable via the container)
     * or a PHP callable. The action will receive the scheduler's
     * `payload` when executed.
     *
     * Examples:
     *  'send-report' => App\\Actions\\SendReport::class,
     *  'cleanup'     => fn(array $payload = []) => null,
     */
    'actions' => [
        // 'send-report' => App\Actions\SendReport::class,
    ],

    // Route and UI settings -------------------------------------------------
    /**
     * URL prefix for the package routes. Change this to mount the UI
     * under a different path (e.g. 'admin/scheduler-manager'). To
     * register at the site root use an empty string ''.
     */
    'route_prefix' => 'scheduler-manager',

    /**
     * Middleware applied to package routes. By default the UI uses the
     * 'web' session middleware and 'auth' so only authenticated users
     * can access it. Adjust to match your application's guards or add
     * additional middleware like 'can:manage-schedulers'.
     */
    'middleware' => ['web', 'auth'],

    // Locking ----------------------------------------------------------------
    /**
     * Default cache lock time-to-live (seconds) used when a scheduler
     * enables `prevent_overlap`. This value is applied as the default
     * TTL for Cache::lock(...)->get() and can be increased if your
     * scheduled tasks typically run longer than the default.
     */
    'lock_ttl' => 3600,
];
