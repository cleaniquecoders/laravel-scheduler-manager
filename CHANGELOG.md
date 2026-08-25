# Changelog

All notable changes to `laravel-scheduler-manager` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0 - 2026-08-25

**Breaking release.** This package now targets **Livewire 4 exclusively**. Applications still on Livewire 3 should stay on `1.0.x`.

### Breaking

- `livewire/livewire` moves from `^3.7` to `^4.0`.

### Fixed

Livewire 4 previously did not work at all. Two separate problems, both now resolved.

**Component resolution.** Livewire resolves a namespaced alias such as `scheduler-manager::dashboard` *solely* through registered class namespaces — `Finder::resolveClassComponentClassName()` returns `null` the moment it sees `::`, without ever consulting the explicit component map that `Livewire::component()` fills. Every screen was unresolvable. The service provider now calls `Livewire::addNamespace()`, unconditionally rather than behind the `method_exists` guard that 1.0.1 needed for Livewire 3 compatibility.

**Invalid Blade on Flux tags.** `SupportCompiledWireKeys` precompiles `wire:key` by injecting a `<?php ?>` block immediately before the attribute — that is, *inside* the tag. On a Blade component tag such as `<flux:table.row wire:key="...">` the component compiler then emitted invalid PHP and the view died with `syntax error, unexpected token "endif"`, taking 66 of 233 tests with it.

`wire:key` is removed from every `<flux:*>` tag. Livewire 4 derives loop keys itself through `livewire.smart_wire_keys`, so the attribute was redundant. This is the change that **required** dropping Livewire 3: Livewire 3 has no smart-key fallback, so a keyless loop there risks DOM-diffing bugs. `wire:key` on plain HTML elements is untouched.

All three table views now carry a Blade comment recording why the attribute must not come back.

### Note for contributors

Compiled Blade views are cached keyed on the Blade **source**, not on the Livewire version. A test run after changing Livewire or the views will happily reuse views compiled by the previous configuration and report a false green — this once turned a real 66-failed into a reported 233-passed. Delete `vendor/orchestra/testbench-core/laravel/storage/framework/views/*.php` before trusting such a run. Documented in `CLAUDE.md` and `CONTRIBUTING.md`.

### Unchanged

PHP 8.4/8.5, Laravel 12/13, Flux free tier, and the whole scheduling engine, commands, events and security model are as in 1.0.1.

Verified from a cold Blade cache on Livewire v4.4.2: 233 tests, 557 assertions, PHPStan level 5 clean with an empty baseline, Pint clean, and all four CI cells green.

**Full Changelog**: https://github.com/cleaniquecoders/laravel-scheduler-manager/compare/1.0.1...2.0.0

## 1.0.1 - 2026-08-25

Patch release. No behaviour change on Livewire 3; groundwork and documentation corrections toward Livewire 4.

### Fixed

- The service provider now registers the package's Livewire class namespace with `addNamespace()` in addition to `Livewire::component()`. Livewire 4 resolves a namespaced alias such as `scheduler-manager::dashboard` exclusively through registered class namespaces and never consults the explicit component map, so without this every screen is unresolvable there. The call goes through the facade root behind a `method_exists` check, since the method does not exist on Livewire 3.

### Documentation

- The 1.0.0 notes said Livewire 4 was unsupported without stating a verified reason. Both stated reasons turned out to be real, and one of them is now fixed. The remaining blocker is documented precisely: `SupportCompiledWireKeys` injects a `<?php ?>` block *inside* the tag carrying `wire:key`, which produces invalid Blade on a component tag such as `<flux:table.row wire:key="...">`, and the view dies with `syntax error, unexpected token "endif"`. Tracked in #48.
- Added a warning that compiled Blade views are cached keyed on the Blade **source**, not the Livewire version. Running the suite after switching Livewire majors silently reuses views compiled by the other one and reports a false green — clearing the cache turned a reported 233-passed into 66-failed. `CLAUDE.md` and `CONTRIBUTING.md` both call this out.

### Still true

`livewire/livewire` remains pinned to `^3.7`. Livewire 4 support is tracked in #48 and is not complete.

**Full Changelog**: https://github.com/cleaniquecoders/laravel-scheduler-manager/compare/1.0.0...1.0.1

## 1.0.0 - 2026-08-25

First public release. Manage Laravel scheduled tasks as database records, with a Livewire UI, instead of hard-coding them in `routes/console.php`.

### Added

#### Scheduling engine

- `scheduler-manager:tick` evaluates every enabled scheduler against its own cron expression and timezone, and dispatches due work to the queue. The tick only decides *what* is due, so a task that runs for twenty minutes never blocks the next minute.
- `RunSchedulerJob` is orchestration only — run bookkeeping, overlap locking, event dispatch. The work belongs to a runner resolved from `SchedulerType`, so adding a task type never means editing the job.
- Two runners: `ArtisanRunner` (`Artisan::call`) and `ActionRunner` (container-resolved, whitelisted).
- Every execution is recorded on `scheduler_runs` with status, exit code, captured output, duration and exception.
- `prevent_overlap` takes a cache lock per scheduler. A suppressed run is recorded as **Skipped**, not Failed, so failure counts and alerting stay meaningful.

#### Management UI

- Livewire screens for listing (search, type/state filter, sortable columns, inline enable/disable), create/edit with cron presets and a live next-five-runs preview, run history with an output viewer, and a dashboard of failing, overdue and upcoming tasks.
- Built on the **free tier** of [Flux](https://fluxui.dev). No Flux Pro component is used anywhere.
- Mounted at `scheduler-manager.route_prefix` with `scheduler-manager.middleware`. Set `ui.enabled` to `false` to run the engine with no HTTP surface at all.

#### Commands

`tick`, `run` (`--sync`), `list` (`--enabled`, `--failing`), `toggle`, `prune` (`--days`, `--keep-last`, `--dry-run`), `reap` (`--threshold`), `import` (`--dry-run`, `--enabled`).

#### Operations

- Run history is unbounded by nature — one `* * * * *` scheduler writes 1,440 rows a day — so `scheduler-manager:prune` enforces a retention window while always keeping the last N runs per scheduler.
- `scheduler-manager:reap` reclassifies runs abandoned by a worker that died before recording a result. The tick reaps opportunistically, so no extra cron entry is needed.
- `SchedulerRunStarted`, `SchedulerRunSucceeded`, `SchedulerRunFailed` and `SchedulerRunSkipped` events, so applications can wire their own alerting without this package depending on any of it.

#### Foundation

- UUID handling via [`cleaniquecoders/traitify`](https://github.com/cleaniquecoders/traitify) `InteractsWithUuid`, which also makes `uuid` the route key so the UI never exposes auto-increment ids.
- `SchedulerType` and `RunStatus` implement Traitify's `Contracts\Enum`, so `::options()` feeds the UI selects directly.

### Security

**Actions resolve strictly from the `scheduler-manager.actions` whitelist.** There is deliberately no fallback that treats a scheduler's `identifier` as a class name. `identifier` is operator-supplied input, so such a fallback would let anyone who can create a scheduler have the container instantiate any class in the application and invoke it with arguments of their choosing.

Artisan schedulers accept an optional `allowed_commands` list, empty by default.

**Authorization denies by default.** This UI executes Artisan commands on the host every minute, so "any authenticated user" is not a safe default — the host application must define the gate named by `scheduler-manager.gate`. `run` and `toggle` are separate abilities from `update`, so an operator can trigger a task without being able to change what it runs. Every component authorizes internally, not only through route middleware.

Run output and exception traces are rendered escaped, with a test asserting both the absence of raw markup and the presence of the escaped entities.

### Requirements

- PHP 8.4 or 8.5
- Laravel 12 or 13 — both verified in CI
- Livewire 3. **Livewire 4 is not supported**: it changes component resolution so explicitly registered namespaced components cannot be found, and its `wire:key` precompiler emits invalid Blade for `<flux:*>` tags. `livewire/livewire` is pinned to `^3.7`.

### Setup

Registering the tick is required — without it the package does nothing at all:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('scheduler-manager:tick')->everyMinute();
Schedule::command('scheduler-manager:prune')->daily();



```
Publish tags are `scheduler-manager-config`, `scheduler-manager-migrations` and `scheduler-manager-views`.

### Notes on this tag

A `1.0.0` tag previously existed on a pre-release commit while this repository was private. Its notes described a CI standardisation change as feature work, and it never reached Packagist. It has been replaced by this tag.

Verified at 233 tests / 557 assertions, PHPStan level 5 with an empty baseline, and Pint, across all four CI cells.

## [Unreleased]
