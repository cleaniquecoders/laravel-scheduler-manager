# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`cleaniquecoders/laravel-scheduler-manager` — a Laravel package that moves scheduled tasks out of the
host app's `routes/console.php` and into database rows, so they can be added, edited, enabled,
disabled and re-run from a CLI or a Livewire UI without a deploy.

The engine is complete: enums, models, runners, job, seven console commands, retention and reaping,
domain events, validation rules, an authorization policy, the Livewire components and their Blade
views. What remains on `feat/v0.3.0-management-ui` is provider wiring — see
[Current branch state](#current-branch-state).

## Commands

```bash
composer test                        # vendor/bin/pest
vendor/bin/pest --filter="tick"      # single test by name
vendor/bin/pest tests/Feature/RunSchedulerJobTest.php   # single file
composer test-coverage               # pest --coverage
composer analyse                     # phpstan level 5 over src/, config/, database/
composer format                      # pint (default Laravel preset, no pint.json)
```

`build/run_tick_smoke.php` and `build/run_job_smoke.php` are standalone Capsule-based smoke scripts
(`php build/run_tick_smoke.php`) that bootstrap Eloquent without Testbench — useful for debugging the
run loop outside the test harness, not part of CI.

## Architecture

Two stages, deliberately decoupled so a long task never blocks the minute tick.

**1. `TickCommand` (`scheduler-manager:tick`)** — the host app registers this itself:
`Schedule::command('scheduler-manager:tick')->everyMinute()` in `routes/console.php`. Without that
registration the package does nothing at all. The command optionally reaps stale runs
(`reap_on_tick`, default true), then walks `Scheduler::enabled()`, skipping rows whose cron will not
parse (reported to stderr, never fatal) and dispatching `RunSchedulerJob` for rows where `isDue()`.

**2. `RunSchedulerJob` (`ShouldQueue`)** — refreshes the model, creates a `SchedulerRun` with
`RunStatus::Running`, dispatches `SchedulerRunStarted`, and acquires
`Cache::lock("scheduler_manager:{id}:lock", lock_ttl)` when `prevent_overlap` is set. A lock it cannot
get produces a **skipped** run, not a silent no-op. It then resolves the runner from
`SchedulerType::runner()` and delegates:

- `ArtisanRunner` — checks `allowed_commands`, then `Artisan::call($identifier, $payload)`; exit code
  drives success/failure.
- `ActionRunner` — resolves `$identifier` **strictly** from `config('scheduler-manager.actions')`,
  then invokes `handle()`, `__invoke()` or the callable via `App::call()`.

Every path ends in `finish()`: the run row is closed out with status, exit code, output and exception;
on a non-skipped run the parent's `last_run_at` and `next_run_at` are recomputed; and one of
`SchedulerRunSucceeded` / `SchedulerRunFailed` / `SchedulerRunSkipped` is dispatched.

Adding a scheduler type means adding a `Runners\*Runner` and a branch in `SchedulerType::runner()`.
`RunSchedulerJob` should never grow a `match` on type.

### Layout

| Concern | Path |
|---|---|
| Provider | `src/LaravelSchedulerManagerServiceProvider.php` |
| Models | `src/Models/{Scheduler,SchedulerRun}.php` |
| Enums | `src/Enums/{SchedulerType,RunStatus}.php` |
| Console | `src/Console/{Tick,Run,List,Toggle,Prune,Reap,Import}Command.php` |
| Job / runners | `src/Jobs/RunSchedulerJob.php`, `src/Runners/`, `src/Contracts/Runner.php`, `src/Data/RunResult.php` |
| Maintenance | `src/Actions/{PruneRuns,ReapStaleRuns}.php` |
| Livewire | `src/Livewire/` (+ `src/Livewire/Concerns/AuthorizesSchedulers.php`) |
| Views | `resources/views/` — namespace `scheduler-manager::` |
| Policy / rules / events / exceptions | `src/Policies/`, `src/Rules/`, `src/Events/`, `src/Exceptions/` |
| Routes | `routes/web.php` |

## Non-obvious gotchas

**Spatie package name vs Composer name.** The Composer package is
`cleaniquecoders/laravel-scheduler-manager`, but the provider calls `$package->name('scheduler-manager')`.
That short name drives the config key `config('scheduler-manager.*')`, the view namespace
`scheduler-manager::`, and the publish tags `scheduler-manager-config`, `scheduler-manager-migrations`,
`scheduler-manager-views`. `laravel-scheduler-manager-*` tags do not exist and every command using one
fails.

**One migration stub, two tables.** The provider registers a single migration,
`create_schedulers_table`, but that stub creates **both** `schedulers` and `scheduler_runs`. Do not go
looking for a second migration.

**No `RefreshDatabase`.** `tests/TestCase.php::defineDatabaseMigrations()` includes the `.stub`
migration files and calls `->up()` on each returned anonymous class. The migrations ship as `.stub` so
they can be published with a timestamp, which means the framework migrator cannot discover them. Every
test already gets a fresh in-memory SQLite database — adding `RefreshDatabase` is wrong, not merely
redundant.

**Never a bare `Event::fake()`.** It swallows Eloquent model events too, which is exactly what
Traitify's `InteractsWithUuid` hooks into. Models then insert with a null `uuid` and hit the NOT NULL
constraint. Always fake specific events:
`Event::fake([SchedulerRunFailed::class, SchedulerRunSucceeded::class])`.

**`next_run_at` is stored in the application timezone.** Cron *evaluation* uses the scheduler's own
timezone (`resolveTimezone()`, falling back to `config('app.timezone')`), but
`calculateNextRunAt()` converts the result to `config('app.timezone')` before returning. Eloquent
serialises a `Carbon` using that instance's own timezone, so persisting a scheduler-timezone instance
would write local wall-clock and read it back as application time. Do not "simplify" that conversion
away.

**`next_run_at` is written from two places.** `Scheduler::booted()` recomputes it on `saving()` when
`cron` or `timezone` is dirty or it is null; `RunSchedulerJob::finish()` recomputes it after every
non-skipped run via `forceFill()->save()`.

**The action whitelist is the security boundary.** `ActionRunner` throws `ActionNotAllowedException`
for any identifier that is not a key of `config('scheduler-manager.actions')`, and there is
**deliberately no fallback** to treating the identifier as a class name. `identifier` is an
operator-supplied column, so that fallback would let anyone who can create a scheduler instantiate any
class in the host app. `Rules\ValidSchedulerIdentifier` is a usability guard on top, never a
substitute. This fallback existed once and was removed as a security fix; do not reintroduce it.

**Authorization denies by default.** `SchedulerPolicy` defers every ability to
`config('scheduler-manager.gate')` (default `manage-schedulers`) and returns `false` when
`Gate::has()` is false. The host app must define the gate. `run` and `toggle` are separate abilities
from `update` on purpose. Components authorize via `AuthorizesSchedulers::authorizeScheduler()` in
addition to route middleware.

**Flux free tier only.** The UI uses the free tier of `livewire/flux`. Both it and
`livewire/livewire` are hard `require` dependencies: the UI is the product, so a consumer must not
have to discover a missing package to get a working install. Setting `ui.enabled` to `false` still
registers no routes and no Livewire components, but the dependencies are installed either way.
No Flux Pro component may be used — `livewire/flux-pro` is not on Packagist at all (it is served from
the private `composer.fluxui.dev` repository against per-licence credentials), so a Pro component
would force every consumer to buy a licence and CI to hold licence secrets.

**Pin `livewire/livewire` to `^3.7`, never `^4.0`.** Flux `^2.17` permits `^3.7.4|^4.0`, but Livewire
4 breaks this package in two ways: `Livewire\Finder` treats everything before `::` as a namespace and
ignores the explicitly registered component map, so every screen dies with `Unable to find component:
[scheduler-manager::dashboard]`; and `wire:key` on any `<flux:*>` tag makes Blade emit an unbalanced
`endif`, because Livewire 4's `SupportCompiledWireKeys` precompiler injects `<?php ?>` into the tag
before the component compiler sees it.

**`phpstan-baseline.neon` must stay empty.** Level 5 with `checkModelProperties: true` and
`checkOctaneCompatibility: true` over `src`, `config`, `database`. Fix findings; never baseline them.
`checkModelProperties` is why the `@property` docblocks on both models must stay accurate.

**`ImportCommand` must never import `scheduler-manager:*`.** The host application is required to
schedule `scheduler-manager:tick` every minute, so it is always present in the schedule being
imported. Stored as a scheduler row it would dispatch a job that runs the tick, which dispatches the
tick again — unbounded amplification every minute. `ImportCommand::OWN_COMMAND_PREFIX` skips the whole
namespace; do not narrow it to just the tick.

**Eager-load `latestRun`.** `Scheduler::latestRun()` is a `hasOne(...)->latestOfMany('started_at')`.
Any listing that renders last-run status must `->with('latestRun')` or it is an N+1 across the page.

**`workbench/`.** `composer.json` `autoload-dev` maps `Workbench\App\` to `workbench/app/`, but no
`workbench/` directory exists.

## Conventions

- **Traitify concerns over hand-rolled hooks.** Models use
  `CleaniqueCoders\Traitify\Concerns\InteractsWithUuid`, which generates the `uuid`, gives a `uuid()`
  scope, and makes `getRouteKeyName()` return `'uuid'` — so routes and every CLI command take the UUID,
  never the numeric id. Reach for an `InteractsWith*` before writing new boot logic.
- **Enums, never magic strings.** Enums implement `CleaniqueCoders\Traitify\Contracts\Enum` and use
  `InteractsWithEnum`, which supplies `::options()` returning `[{value, label, description}]` for
  form selects. Compare cases (`$run->status === RunStatus::Failed`), never `=== 'failed'`. Both
  models cast their enum columns.
- **PHP 8.4+, Laravel 12 or 13, Livewire 3, Pest 4, Testbench 10 or 11.** Both Laravel cells are
  verified in CI; write nothing that only works on one of them.
- **Pint, default Laravel preset.** Run `composer format` on anything you touch.
- Cron work always goes through `dragonmantank/cron-expression` (a declared dependency). Never roll a
  parser.

## Testing conventions

- Pest closures (`it(...)`, `test(...)`) throughout — there are no PHPUnit-style test classes left.
  `tests/Pest.php` applies `Tests\TestCase` (Orchestra Testbench) to `tests/Feature` and
  `tests/Unit`. `tests/Boot/*` gets its own base case per file, because provider wiring that is
  settled before the application boots cannot be exercised by flipping config inside a test:
  `UiDisabledTestCase` (`ui.enabled` false) and `RootPrefixTestCase` (empty `route_prefix`).
- Factories resolve via `Factory::guessFactoryNamesUsing()` to
  `CleaniqueCoders\LaravelSchedulerManager\Database\Factories\{Model}Factory`. `SchedulerFactory`
  states: `disabled`, `action`, `preventingOverlap`, `cron`, `hourly`, `daily`, `timezone`.
  `SchedulerRunFactory` states: `running`, `successful`, `failed`, `skipped`, `stale`.
- Use `Carbon::setTestNow()` for anything touching `isDue()` or `calculateNextRunAt()`.
- `tests/ArchTest.php` forbids `dd`, `dump` and `ray` anywhere in the codebase.

## Configuration

`config/scheduler-manager.php`: `actions` (the whitelist), `route_prefix`, `middleware`, `lock_ttl`,
`allowed_commands` (empty permits any registered command), `retention_days`, `retention_keep_last`,
`stale_run_threshold`, `reap_on_tick`, `ui` (`enabled`, `route_name_prefix`, `layout`, `per_page`),
and `gate`.

`scheduler_runs` grows without bound — a `* * * * *` scheduler writes 1,440 rows a day — so the host
app should also schedule `scheduler-manager:prune`.

## Current branch state

`feat/v0.3.0-management-ui` is the UI branch, and it is complete: the four Livewire components, the
`AuthorizesSchedulers` concern, `SchedulerPolicy`, `routes/web.php`, the `ui`/`gate` config keys, the
Blade views (`resources/views/layouts/app.blade.php`, `resources/views/livewire/*`,
`resources/views/components/stats.blade.php`), `ImportCommand`, and the provider wiring that ties them
together.

`packageBooted()` registers the policy always, then — only when `ui.enabled` — asserts Flux is
installed, registers the four Livewire aliases (`scheduler-manager::scheduler-index`, `-form`,
`-runs`, `dashboard`) and loads `routes/web.php` under `route_prefix`/`middleware`.
`tests/Feature/ServiceProviderTest.php` and `tests/Boot/` hold that contract; re-run them rather than
trusting this section, which goes stale fastest.

Also note `.gitignore` line 35 is `*instructions.md`, which excludes `.github/copilot-instructions.md`
from version control. Edits to it are local-only until that pattern is narrowed.

## CI

`run-tests.yml` runs a 2x2 matrix, prefer-stable, Linux only: **PHP 8.4 and 8.5 x Laravel 12
(Testbench 10) and Laravel 13 (Testbench 11)**. `composer.json` and the matrix agree exactly —
`illuminate/contracts ^12.0||^13.0` and testbench `^10.0||^11.0`. Laravel 11 support was dropped
rather than left as an untested claim (issue #38). Keep the two in sync: do not widen a constraint
without adding the matching matrix cell.

`pint.yml` runs `vendor/bin/pint --test`, so a style violation fails the build rather than being
auto-fixed. `phpstan.yml` runs level 5 against an empty `phpstan-baseline.neon`; keep it empty rather
than adding ignores.
