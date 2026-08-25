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

    // Execution policy ------------------------------------------------------
    /**
     * Optional allow-list of Artisan commands that may be scheduled. An empty
     * array permits any registered command. Populate it to restrict which
     * commands operators can trigger through this package.
     */
    'allowed_commands' => [],

    // Retention --------------------------------------------------------------
    /**
     * How many days of run history to keep. `scheduler-manager:prune` deletes
     * anything older. A scheduler on "* * * * *" writes 1,440 rows a day, so
     * without pruning the scheduler_runs table grows without bound.
     */
    'retention_days' => 30,

    /**
     * Runs to always keep per scheduler regardless of age, so history is never
     * left completely empty for a rarely-run task.
     */
    'retention_keep_last' => 10,

    /**
     * A run still marked "running" after this many seconds is treated as
     * abandoned by `scheduler-manager:reap`. A worker killed mid-job (OOM,
     * deploy, container restart) never reaches its finally block, so the row
     * would otherwise stay "running" forever.
     */
    'stale_run_threshold' => 3600,

    /**
     * Have the tick command reap stale runs opportunistically, so no extra
     * cron entry is required.
     */
    'reap_on_tick' => true,

    // User interface --------------------------------------------------------
    /**
     * Register the package routes and Livewire components. Disable this to
     * install the scheduling engine alone, with no HTTP surface at all.
     *
     * The UI is built on the free tier of Flux (https://fluxui.dev). Flux is
     * intentionally not a hard dependency of this package: install
     * livewire/flux yourself when you want the UI, or publish the views with
     * `--tag=scheduler-manager-views` and restyle them.
     */
    'ui' => [
        'enabled' => true,

        /**
         * Prefix applied to package route names, so they cannot collide with
         * routes in the host application.
         */
        'route_name_prefix' => 'scheduler-manager.',

        /**
         * The Blade layout package views extend. Point this at your own
         * application layout to have the UI inherit your chrome.
         */
        'layout' => 'scheduler-manager::layouts.app',

        /**
         * Rows per page on the listing screens.
         */
        'per_page' => 15,
    ],

    // Authorization ----------------------------------------------------------
    /**
     * Gate ability checked before any scheduler screen or action. The package
     * denies by default: this UI can execute arbitrary Artisan commands on the
     * host, so "any authenticated user" is not a safe default. Define the
     * ability in your AuthServiceProvider to grant access.
     *
     * The closure is called with the user, the scheduler being acted on (null
     * for the index, the dashboard and creation) and the ability requested —
     * one of viewAny, view, create, update, delete, run, toggle — so access can
     * be narrowed per action. Declare only the arguments you need:
     *
     *  Gate::define('manage-schedulers', fn ($user) => $user->isAdmin());
     *
     *  Gate::define('manage-schedulers', fn ($user, ?Scheduler $scheduler, string $ability) =>
     *      $user->isAdmin() || ($ability === 'run' && $user->isOperator()));
     */
    'gate' => 'manage-schedulers',
];
