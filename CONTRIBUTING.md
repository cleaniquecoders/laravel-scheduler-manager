# Contributing

Thanks for considering a contribution to `cleaniquecoders/laravel-scheduler-manager`. This document
covers how to get set up, the conventions the codebase follows, and what a pull request needs to
carry before it can be merged.

Please note that this project is released with a Contributor Code of Conduct. By participating you
agree to abide by its terms.

## Getting set up

```bash
git clone git@github.com:<your-username>/laravel-scheduler-manager.git
cd laravel-scheduler-manager
composer install
composer test
```

The suite runs against Orchestra Testbench with an in-memory SQLite database; there is nothing else to
configure.

Requirements: PHP 8.4+, Composer. CI verifies **PHP 8.4 + Laravel 12 + Testbench 10**.

## Workflow

1. Fork the repository and create a branch off `main`.
2. Name the branch for what it does: `feat/scheduler-import`, `fix/next-run-timezone`,
   `docs/readme-rewrite`, `chore/ci-matrix`.
3. Make the change, with tests.
4. Run the full check set (below) — all three must pass.
5. Open a pull request against `main`.

Keep pull requests small and focused. One behavioural change per PR is much easier to review than a
branch that also reformats half the package.

If your change closes an issue, reference it in the PR body (`Closes #42`).

## Before you push

All three of these must pass. CI runs them, so running them locally saves a round trip:

```bash
composer test     # Pest
composer analyse  # PHPStan level 5 with Larastan
composer format   # Pint, default Laravel preset
```

`composer format` rewrites files in place — run it last and commit the result.

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<optional scope>): <short imperative summary>
```

Types in use here: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `chore`, `ci`, `build`.

```
feat(console): add scheduler-manager:import
fix(models): honour the per-scheduler timezone when computing next_run_at
docs(readme): correct the vendor:publish tags
test(livewire): cover the run-now authorization path
```

Mark a breaking change with `!` after the type (`feat!: ...`) and explain it in the commit body.

## Code conventions

- **PHP 8.4, Laravel 12+, Livewire 3.** Use constructor property promotion, enums, readonly where it
  fits, and typed properties throughout.
- **Pint, default Laravel preset**, no `pint.json`. Do not hand-format; run `composer format`.
- **PHPStan level 5** with Larastan, `checkModelProperties: true`. `phpstan-baseline.neon` **must stay
  empty** — fix the finding rather than baselining it. If a rule genuinely cannot be satisfied, say so
  in the PR and we will discuss it, but the default answer is to fix the code.
- **Traitify over hand-rolled hooks.** Models use `CleaniqueCoders\Traitify\Concerns\InteractsWith*`
  (`InteractsWithUuid` and friends) rather than a bespoke `booted()` creating hook.
- **Enums, never magic strings.** New enums implement
  `CleaniqueCoders\Traitify\Contracts\Enum` and use `InteractsWithEnum`, which gives you
  `::options()` for form selects. Add `label()` and `description()`.
- **New scheduler types go behind a runner.** Add a `Runners\*Runner` implementing
  `Contracts\Runner`, and map it from `SchedulerType::runner()`. `RunSchedulerJob` should not need
  editing to support a new type.
- **Never widen the action whitelist.** `ActionRunner` resolves only from
  `config('scheduler-manager.actions')`, and there must be no fallback that treats a scheduler's
  `identifier` as a class name. `identifier` is operator-supplied input; a fallback would let anyone
  who can write a scheduler row instantiate any class in the host application. This is a hard
  constraint, not a preference.
- **UI is Flux free tier only.** Views may use free-tier Flux components. Flux Pro components are
  never permitted: `livewire/flux-pro` is not on Packagist, so using one would force every consumer
  to buy a licence and configure a private repository. Verify a component exists by listing
  `vendor/livewire/flux/stubs/resources/views/flux/` rather than trusting the docs site, which does
  not publish the tier split.
- **Do not widen `livewire/livewire` past `^3.7` without running the suite on Livewire 4 with a
  cleared Blade cache.** Compiled views are keyed on the Blade source, not the Livewire version, so
  switching majors without deleting
  `vendor/orchestra/testbench-core/laravel/storage/framework/views/*.php` reports a false green.
  See issue #48.

- **No debugging leftovers.** `tests/ArchTest.php` fails the build on `dd`, `dump` or `ray` anywhere
  in the codebase.

## Tests

- Written in **Pest closure style**:

  ```php
  it('dispatches a job for a due scheduler', function () {
      $scheduler = Scheduler::factory()->create(['cron' => '* * * * *']);

      Queue::fake();

      $this->artisan('scheduler-manager:tick')->assertSuccessful();

      Queue::assertPushed(RunSchedulerJob::class);
  });
  ```

- **Do not add `RefreshDatabase`.** `tests/TestCase.php` builds the schema in
  `defineDatabaseMigrations()` by including the `.stub` migrations directly — the package ships them
  as stubs so they can be published with a timestamp, which means the framework migrator cannot
  discover them. Each test already gets a fresh in-memory database.

- **Never call a bare `Event::fake()`.** It swallows Eloquent model events too, which is what Traitify
  `InteractsWithUuid` hooks into, so models insert with a null `uuid` and hit the NOT NULL constraint.
  Always fake specific events:

  ```php
  Event::fake([SchedulerRunFailed::class, SchedulerRunSucceeded::class]);
  ```

- Use `Carbon::setTestNow()` for anything that evaluates cron or computes `next_run_at`, so the test
  does not depend on the wall clock.

- Cover the new behaviour and its failure path. A `feat` PR without tests will be asked for tests.

## Documentation

If your change alters public behaviour, update the docs in the same PR:

- `README.md` — anything a consumer of the package sees: commands, config keys, security rules.
- `CLAUDE.md` and `.github/copilot-instructions.md` — anything a contributor or AI assistant needs to
  know: file locations, conventions, gotchas.

Every path referenced in the contributor docs must exist on disk, and every command in the README must
be copy-pasteable against a clean install.

## Reporting bugs

Open an issue using the bug template. Include the package version, PHP and Laravel versions, and a
minimal reproduction. For security vulnerabilities, do **not** open a public issue — follow the
[security policy](../../security/policy).

## Pull request expectations

A PR is ready to review when:

- [ ] `composer test`, `composer analyse` and `composer format` all pass
- [ ] `phpstan-baseline.neon` is still empty
- [ ] New behaviour has Pest tests, including its failure path
- [ ] Commits follow Conventional Commits
- [ ] Docs are updated if public behaviour changed
- [ ] The diff contains only what the PR is about

Maintainers may push small fixups to your branch rather than blocking on a round trip. If you would
rather they did not, say so in the PR description.
