# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`cleaniquecoders/laravel-scheduler-manager` — a Laravel package that moves scheduled tasks out of the host app's `routes/console.php` and into database rows, so they can eventually be managed from a UI. The package is **early / partially built**: the core tick → dispatch → run → record loop works, but the UI (Livewire/Volt), routes, and "run now" API described in `todo.md` and `.github/copilot-instructions.md` do not exist yet. `src/LaravelSchedulerManager.php` and `src/Commands/LaravelSchedulerManagerCommand.php` are still empty Spatie skeleton stubs.

## Commands

```bash
composer test                   # vendor/bin/pest
vendor/bin/pest --filter="tick" # single test by name
vendor/bin/pest tests/Feature/RunSchedulerJobTest.php   # single file
composer test-coverage          # pest --coverage
composer analyse                # phpstan level 5 over src/, config/, database/
composer format                 # pint (default Laravel preset, no pint.json)
```

`build/run_tick_smoke.php` and `build/run_job_smoke.php` are standalone Capsule-based smoke scripts (`php build/run_tick_smoke.php`) that bootstrap Eloquent without Testbench — useful for debugging the run loop outside the test harness, not part of CI.

## Architecture

The runtime is a two-stage loop, deliberately decoupled so long-running tasks never block the minute tick:

1. **`TickCommand`** (`scheduler-manager:tick`) — the host app registers this with `$schedule->command('scheduler-manager:tick')->everyMinute()`. It loads all `enabled` schedulers, evaluates each `cron` string with `Cron\CronExpression` (from `dragonmantank/cron-expression`, pulled in transitively via `laravel/framework` — it is **not** a declared dependency in `composer.json`), and dispatches `RunSchedulerJob` for due rows. An invalid cron string is reported to stderr and skipped, never fatal.
2. **`RunSchedulerJob`** (`ShouldQueue`) — creates a `SchedulerRun` row with `status: running`, optionally acquires `Cache::lock("scheduler_manager:{id}:lock", config('scheduler-manager.lock_ttl'))` when `prevent_overlap` is set, then branches on `Scheduler::$type`:
   - `artisan` → `Artisan::call($identifier, $payload)`, exit code drives success/failed.
   - `action` → looks `$identifier` up in `config('scheduler-manager.actions')` (falling back to treating the identifier itself as a class name), resolves via the container, and invokes `handle()` / `__invoke()` / callable, in that order.

   Every path terminates by updating the `SchedulerRun` with `finished_at`, `status`, `exit_code`, `output`, `exception`. Failure to acquire the lock is recorded as a **failed run**, not a silent skip. On success the parent `Scheduler.last_run_at` is bumped.

`Scheduler` and `SchedulerRun` both auto-populate a `uuid` in a `booted()` creating hook because the migration declares those columns non-null.

### Known gaps to be aware of when editing

- `TickCommand` uses `Carbon::now()` (app timezone) and ignores the per-scheduler `timezone` column. `next_run_at` is never computed or written by any code path.
- `SchedulerRun::$fillable` does not include `uuid`; it only gets set through the model hook, so `SchedulerRun::create()` cannot set it explicitly.
- `config/scheduler-manager.php` declares `route_prefix` and `middleware`, but no routes are registered anywhere yet.

## Package wiring gotchas

- The Spatie package name is **`scheduler-manager`**, not `laravel-scheduler-manager`. Publish tags are therefore `scheduler-manager-config`, `scheduler-manager-migrations`, `scheduler-manager-views` — the README still shows the wrong `laravel-scheduler-manager-*` tags.
- The service provider registers a single migration, `create_schedulers_table`, but that one stub creates **both** the `schedulers` and `scheduler_runs` tables.
- `.github/copilot-instructions.md` describes an intended design (Livewire components, `src/SchedulerManagerServiceProvider.php`, Livewire feature tests) that diverges from what is on disk — the actual provider is `src/LaravelSchedulerManagerServiceProvider.php`. Treat that file as a spec for future work, not a description of current state.
- `composer.json` `autoload-dev` maps `Workbench\App\` to `workbench/app/`, but no `workbench/` directory exists.

## Testing conventions

- Pest, with `tests/Pest.php` applying `Tests\TestCase` (Orchestra Testbench) to everything in `tests/`. Factories are resolved to `CleaniqueCoders\LaravelSchedulerManager\Database\Factories\{Model}Factory`.
- There is **no `RefreshDatabase`**. Feature tests build their schema by looping `File::allFiles(database/migrations)` and calling `->up()` on each returned anonymous class in `setUp()` — mirror that pattern in new feature tests. The database is Testbench's in-memory `testing` connection.
- `tests/Feature/*` are currently written as PHPUnit-style classes with `test_*` methods rather than Pest closures; both styles run. New tests should follow the repo's stated preference for Pest closures where practical.
- `tests/ArchTest.php` forbids `dd`, `dump`, `ray` anywhere in the codebase.

## CI

`run-tests.yml` pins a single combination: **PHP 8.4 + Laravel 12 + Testbench 10, prefer-stable**. `composer.json` advertises broader support (`illuminate/contracts ^11||^12||^13`, testbench `^9||^10||^11`) but only L12 is verified — recent commits deliberately narrowed the matrix until Laravel 13 lands on Packagist. `phpstan.yml` runs level 5 with an empty `phpstan-baseline.neon`; keep it empty rather than adding ignores.
