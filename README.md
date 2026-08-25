# Laravel Scheduler Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cleaniquecoders/laravel-scheduler-manager.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/laravel-scheduler-manager)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cleaniquecoders/laravel-scheduler-manager/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/cleaniquecoders/laravel-scheduler-manager/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/cleaniquecoders/laravel-scheduler-manager/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/cleaniquecoders/laravel-scheduler-manager/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cleaniquecoders/laravel-scheduler-manager.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/laravel-scheduler-manager)

Move scheduled tasks out of `routes/console.php` and into database rows, so operators can add, edit,
enable, disable and re-run them without a deploy.

Each scheduler row holds a cron expression, a timezone, and either an Artisan command or a
whitelisted action class. Every execution is recorded — start and finish time, exit code, output,
exception — so you can see what ran, what failed, and what is overdue, from the CLI or from the
bundled Livewire UI.

```
┌─────────────────────────┐
│ Laravel scheduler       │  every minute
│ scheduler-manager:tick  │
└───────────┬─────────────┘
            │  reads enabled schedulers, evaluates cron in each row's timezone
            ▼
┌─────────────────────────┐
│ RunSchedulerJob (queue) │  one job per due scheduler
└───────────┬─────────────┘
            │  optional Cache::lock when prevent_overlap is set
            ▼
┌─────────────────────────┐      ┌──────────────────────────────┐
│ ArtisanRunner           │  or  │ ActionRunner                 │
│ Artisan::call()         │      │ resolves from the whitelist  │
└───────────┬─────────────┘      └──────────────┬───────────────┘
            │                                   │
            └──────────────┬────────────────────┘
                           ▼
        ┌──────────────────────────────────────┐
        │ scheduler_runs row + domain event    │
        │ status, exit_code, output, exception │
        └──────────────────────────────────────┘
```

The tick and the work are deliberately decoupled: the minute tick only decides *what* is due and
dispatches, so a task that runs for twenty minutes never blocks the next tick.

## Requirements

- PHP 8.4+
- Laravel 12 or 13, both verified in CI
- A queue worker, unless you are happy running everything on the `sync` driver
- Livewire 3 and `livewire/flux` (free tier) — installed automatically as dependencies

> **Livewire 4 is not supported.** `livewire/livewire` is pinned to `^3.7`. Livewire 4 changes
> component resolution so explicitly registered namespaced components cannot be found, and its
> `wire:key` precompiler emits invalid Blade for `<flux:*>` tags.

## Installation

```bash
composer require cleaniquecoders/laravel-scheduler-manager
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="scheduler-manager-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="scheduler-manager-config"
```

Optionally publish the views, if you want to restyle the UI:

```bash
php artisan vendor:publish --tag="scheduler-manager-views"
```

> **Note on publish tags.** The Composer package is `cleaniquecoders/laravel-scheduler-manager`, but
> the service provider registers the *package name* as `scheduler-manager`. That name is what drives
> the publish tags, the config key, and the view namespace — hence `scheduler-manager-migrations`,
> `config('scheduler-manager.*')` and `scheduler-manager::`, not `laravel-scheduler-manager-*`.

One migration stub creates **both** tables, `schedulers` and `scheduler_runs`. There is no second
migration to publish.

## Register the tick — required

**Without this step the package does nothing at all.** Nothing is due, nothing is dispatched, no run
is ever recorded. The package does not hook itself into your scheduler; you register it, so you stay
in control of the frequency and of which environments run it.

On Laravel 12, add it to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('scheduler-manager:tick')->everyMinute();
```

On Laravel 10 and earlier, in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('scheduler-manager:tick')->everyMinute();
}
```

And make sure the system cron actually calls Laravel's scheduler on the server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Also schedule the pruner, or the run history will grow without bound — see
[Run retention](#run-retention):

```php
Schedule::command('scheduler-manager:prune')->daily();
```

Verify the wiring:

```bash
php artisan schedule:list          # scheduler-manager:tick should appear, every minute
php artisan scheduler-manager:tick # "Dispatched N scheduler(s)."
```

## Creating schedulers

Rows are ordinary Eloquent models, so you can seed them, create them in a migration, or use the UI.

An Artisan scheduler:

```php
use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;

Scheduler::create([
    'name'            => 'Nightly report',
    'type'            => SchedulerType::Artisan,
    'identifier'      => 'report:generate',   // the Artisan command signature name
    'payload'         => ['--queue' => 'reports'], // passed straight to Artisan::call()
    'cron'            => '0 2 * * *',
    'timezone'        => 'Asia/Kuala_Lumpur',
    'enabled'         => true,
    'prevent_overlap' => true,
]);
```

An action scheduler — `identifier` must be a **key** in `config('scheduler-manager.actions')`:

```php
Scheduler::create([
    'name'       => 'Sync inventory',
    'type'       => SchedulerType::Action,
    'identifier' => 'sync-inventory',
    'payload'    => ['warehouse' => 'KUL-1'], // injected as named arguments
    'cron'       => '*/15 * * * *',
]);
```

Notes on the columns:

- `uuid` is generated automatically and is the route key — URLs and CLI commands take the UUID, never
  the numeric id.
- `next_run_at` is computed and persisted whenever `cron` or `timezone` changes, and after every
  non-skipped run. It is stored in the **application** timezone: Eloquent serialises a `Carbon` using
  that instance's own timezone, so persisting a scheduler-timezone instance would write local
  wall-clock and read it back as application time.
- `timezone` is optional; when null the cron is evaluated in `config('app.timezone')`.
- `prevent_overlap` acquires `Cache::lock("scheduler_manager:{id}:lock", lock_ttl)`. If the lock is
  already held, the run is recorded with status `skipped` — it is not silently dropped.

## Security: the actions whitelist

An `action`-type scheduler resolves its `identifier` **strictly** as a key of
`config('scheduler-manager.actions')`:

```php
// config/scheduler-manager.php
'actions' => [
    'sync-inventory' => App\Actions\SyncInventory::class,
    'cleanup'        => fn (string $warehouse = 'all') => Cleanup::run($warehouse),
],
```

An identifier that is not a key in that array throws `ActionNotAllowedException` and the run is
recorded as failed.

**There is deliberately no fallback to treating the identifier as a class name.** `identifier` is an
operator-supplied string column. A fallback would mean anyone who can create a scheduler row — through
the UI, through a compromised admin account, or through any code path that writes to the table — could
have the container instantiate *any* class in the application and invoke its `handle()` or
`__invoke()`. The whitelist is the security boundary; the `ValidSchedulerIdentifier` validation rule
is a usability guard on top of it, not a substitute for it.

Resolved values may be a container-resolvable class-string or a callable. The package invokes
`handle()`, then `__invoke()`, then the callable itself, via `App::call()` so the container can inject
dependencies alongside the payload.

### The Artisan allow-list

`allowed_commands` is the equivalent control for `artisan`-type schedulers:

```php
'allowed_commands' => [
    'report:generate',
    'cache:prune-stale-tags',
],
```

An **empty array permits any registered command** — that is the default, and it preserves the
historical behaviour. Populate it to opt into a stricter policy; a command outside the list throws
`CommandNotAllowedException`.

## Authorization

**The package denies everything by default.** Every policy ability defers to the gate named by
`config('scheduler-manager.gate')` (default `manage-schedulers`), and if that gate is not defined the
answer is `false`. This UI can execute arbitrary Artisan commands on the host every minute, so "any
authenticated user" is not a safe default — in most applications the auth guard covers ordinary end
users, not administrators.

Define the gate in your `AppServiceProvider` (or `AuthServiceProvider`):

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('manage-schedulers', function ($user, $scheduler = null) {
        return $user->hasRole('admin');
    });
}
```

The abilities are `viewAny`, `view`, `create`, `update`, `delete`, `run` and `toggle`. `run` is
separate from `update` on purpose, so an operator can be allowed to trigger a task without being
allowed to change what it runs. Authorization is asserted inside the Livewire components as well as on
the route, so a Livewire action can never be reached by a request that skipped the route middleware.

Route middleware is configured separately via `config('scheduler-manager.middleware')`, default
`['web', 'auth']`.

## Management UI

The UI is built on the **free tier** of [Flux](https://fluxui.dev). Flux is intentionally **not** a
hard dependency of this package, so installing the scheduling engine never forces a UI toolkit on you.
You have three options:

1. **Install Flux yourself** and use the bundled screens as-is:

   ```bash
   composer require livewire/flux
   ```

2. **Run the engine alone** with no HTTP surface at all — set `ui.enabled` to `false`. The commands,
   job, runners and models keep working; no routes and no Livewire components are registered.

3. **Publish the views and restyle them** with your own components:

   ```bash
   php artisan vendor:publish --tag="scheduler-manager-views"
   ```

No Flux Pro component is used anywhere in the package. If you contribute a view, keep it to the free
tier.

The UI mounts under `config('scheduler-manager.route_prefix')` (default `/scheduler-manager`) with
route names prefixed by `ui.route_name_prefix` (default `scheduler-manager.`):

| Route | Name | Component |
|---|---|---|
| `/scheduler-manager` | `scheduler-manager.index` | Search, filter, sort, inline toggle, run now, delete |
| `/scheduler-manager/dashboard` | `scheduler-manager.dashboard` | Counts, failing schedulers, overdue and upcoming |
| `/scheduler-manager/create` | `scheduler-manager.create` | Create form with cron presets |
| `/scheduler-manager/{scheduler}/edit` | `scheduler-manager.edit` | Edit form |
| `/scheduler-manager/{scheduler}/runs` | `scheduler-manager.runs` | Run history with expandable output |

Package views extend `config('scheduler-manager.ui.layout')`. Point that at your own application
layout to have the screens inherit your chrome.

## Artisan commands

| Command | What it does |
|---|---|
| `scheduler-manager:tick` | Evaluate every enabled scheduler and dispatch the due ones. This is the command you register with Laravel's scheduler. |
| `scheduler-manager:run {uuid} [--sync]` | Run one scheduler immediately, off-schedule. |
| `scheduler-manager:list [--enabled] [--failing]` | Table of schedulers with type, cron, timezone, last run status and next run. |
| `scheduler-manager:toggle {uuid} [--enable] [--disable]` | Enable or disable a scheduler. With no flag, it flips the current state. |
| `scheduler-manager:prune [--days=] [--keep-last=] [--dry-run]` | Delete run history beyond the retention window. |
| `scheduler-manager:reap [--threshold=]` | Mark abandoned runs — still `running` past the threshold — as failed. |
| `scheduler-manager:import [--dry-run] [--enabled]` | Read the host application's registered schedule and create scheduler rows from it. |

```bash
# What is registered, and what is broken
php artisan scheduler-manager:list
php artisan scheduler-manager:list --enabled --failing

# Run one now: queued by default, --sync runs it inline in this process
php artisan scheduler-manager:run 9f2c1a4e-... --sync

# Disable without deleting
php artisan scheduler-manager:toggle 9f2c1a4e-... --disable

# See what pruning would remove before it removes it
php artisan scheduler-manager:prune --days=14 --keep-last=5 --dry-run

# Clean up runs left "running" by a killed worker
php artisan scheduler-manager:reap --threshold=1800
```

`--days` and `--keep-last` fall back to `retention_days` and `retention_keep_last`; `--threshold`
falls back to `stale_run_threshold`.

### Importing an existing schedule

`scheduler-manager:import` walks the schedule your application already has registered and creates a
scheduler row for each Artisan command entry, preserving its cron expression and timezone.

```bash
php artisan scheduler-manager:import --dry-run   # see what would be created, write nothing
php artisan scheduler-manager:import             # import, disabled
php artisan scheduler-manager:import --enabled   # import, already enabled
```

Imports land **disabled** unless you pass `--enabled`, so nothing starts firing twice while both the
old registration and the new row exist. Once a row is imported, remove the original entry from your
application schedule and then enable it.

Closure and job tasks are skipped and reported: they have no command string, so there is nothing a
scheduler row could store. This package's own `scheduler-manager:*` commands are skipped too — the
tick is required to be in your schedule, and a scheduler row that runs the tick would dispatch a job
that runs the tick again, amplifying every minute without bound.

Re-running the command is idempotent — an entry whose identifier *and* cron already match an existing
row is skipped rather than duplicated, so your edits are never clobbered.

### Reaping stale runs

A worker killed mid-job — OOM, deploy, container restart — never reaches its `finally` block, so its
`scheduler_runs` row would stay `running` forever and pollute every "is this failing?" query.
`scheduler-manager:reap` closes those out. By default `reap_on_tick` is `true`, so the tick does it
opportunistically and no extra cron entry is needed; set it to `false` if you would rather schedule
`scheduler-manager:reap` yourself.

## Run retention

`scheduler_runs` grows without bound. A single scheduler on `* * * * *` writes **1,440 rows a day**;
ten of them write over half a million rows a year. Schedule the pruner:

```php
Schedule::command('scheduler-manager:prune')->daily();
```

`retention_days` (default 30) sets the age cut-off, and `retention_keep_last` (default 10) is the
number of runs always kept per scheduler regardless of age, so a rarely-run task never ends up with an
empty history.

## Events

Every run dispatches a domain event carrying the `Scheduler` and the `SchedulerRun`, so you can wire up
your own alerting without touching the package:

| Event | When |
|---|---|
| `Events\SchedulerRunStarted` | A run row has been created and work is about to begin |
| `Events\SchedulerRunSucceeded` | The runner returned without error, exit code 0 |
| `Events\SchedulerRunFailed` | An exception was raised, or a non-zero exit code was returned |
| `Events\SchedulerRunSkipped` | Overlap protection was on and the lock was already held |

```php
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunFailed;
use Illuminate\Support\Facades\Event;

Event::listen(function (SchedulerRunFailed $event) {
    Notification::route('slack', config('services.slack.ops'))
        ->notify(new SchedulerFailed($event->scheduler, $event->run));
});
```

## Programmatic API

```php
use CleaniqueCoders\LaravelSchedulerManager\Facades\LaravelSchedulerManager;

LaravelSchedulerManager::schedulers();                 // Eloquent query builder
LaravelSchedulerManager::actions();                    // the configured whitelist
LaravelSchedulerManager::allowsAction('sync-inventory'); // bool
LaravelSchedulerManager::run($schedulerOrUuid);        // dispatch immediately
```

## Configuration

The published `config/scheduler-manager.php`:

| Key | Default | Purpose |
|---|---|---|
| `actions` | `[]` | Whitelist mapping action identifiers to class-strings or callables. An `action` scheduler can execute nothing else. |
| `route_prefix` | `'scheduler-manager'` | URL prefix for the UI. Use `''` to mount at the site root. |
| `middleware` | `['web', 'auth']` | Middleware applied to package routes. |
| `lock_ttl` | `3600` | Cache lock TTL, in seconds, used when `prevent_overlap` is set. Raise it above your longest task. |
| `allowed_commands` | `[]` | Artisan allow-list. Empty permits any registered command. |
| `retention_days` | `30` | Age cut-off for `scheduler-manager:prune`. |
| `retention_keep_last` | `10` | Runs always kept per scheduler regardless of age. |
| `stale_run_threshold` | `3600` | Seconds after which an unfinished run is treated as abandoned. |
| `reap_on_tick` | `true` | Have the tick reap stale runs, so no extra cron entry is needed. |
| `ui.enabled` | `true` | Register routes and Livewire components. `false` runs the engine alone. |
| `ui.route_name_prefix` | `'scheduler-manager.'` | Prefix for package route names, so they cannot collide with yours. |
| `ui.layout` | `'scheduler-manager::layouts.app'` | Blade layout the package views extend. |
| `ui.per_page` | `15` | Rows per page on the listing screens. |
| `gate` | `'manage-schedulers'` | Gate ability checked before any scheduler screen or action. Undefined means denied. |

## Testing

```bash
composer test          # Pest
composer test-coverage # Pest with coverage
composer analyse       # PHPStan level 5 with Larastan
composer format        # Pint, default Laravel preset
```

Run all three before opening a pull request. See [CONTRIBUTING](CONTRIBUTING.md).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Nasrul Hazim Bin Mohamad](https://github.com/nasrulhazim)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
