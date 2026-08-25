<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

class ImportCommand extends Command
{
    /**
     * Console namespace owned by this package.
     *
     * The host application is told to schedule `scheduler-manager:tick` every
     * minute, so it is always present in the schedule being imported. Importing
     * it would create a scheduler whose job runs the tick, which dispatches the
     * tick again, amplifying every minute without bound. The maintenance
     * commands are equally pointless as scheduler rows.
     */
    protected const OWN_COMMAND_PREFIX = 'scheduler-manager:';

    protected $signature = 'scheduler-manager:import
                            {--dry-run : Report what would be imported without writing}
                            {--enabled : Import as enabled instead of disabled}';

    protected $description = 'Import the application\'s registered schedule into the scheduler manager.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $enabled = (bool) $this->option('enabled');

        /** @var Schedule $schedule */
        $schedule = $this->laravel->make(Schedule::class);

        $imported = [];
        $skippedExisting = [];
        $skippedOwn = [];
        $unsupported = [];

        foreach ($schedule->events() as $event) {
            $identifier = $this->resolveIdentifier($event);

            if ($identifier === null) {
                $unsupported[] = [$this->describe($event), $event->expression];

                continue;
            }

            if (str_starts_with($identifier, static::OWN_COMMAND_PREFIX)) {
                $skippedOwn[] = [$identifier, $event->expression];

                continue;
            }

            $attributes = [
                'name' => $this->resolveName($event, $identifier),
                'type' => SchedulerType::Artisan,
                'identifier' => $identifier,
                'cron' => $event->expression,
                'timezone' => $this->resolveTimezone($event),
                'enabled' => $enabled,
            ];

            if ($this->alreadyImported($identifier, $event->expression)) {
                $skippedExisting[] = [$attributes['name'], $identifier, $attributes['cron']];

                continue;
            }

            if (! $dryRun) {
                Scheduler::query()->create($attributes);
            }

            $imported[] = [
                $attributes['name'],
                $identifier,
                $attributes['cron'],
                $attributes['timezone'] ?? '-',
                $enabled ? 'yes' : 'no',
            ];
        }

        $this->report($imported, $skippedExisting, $skippedOwn, $unsupported, $dryRun);

        return self::SUCCESS;
    }

    /**
     * The artisan command name behind a scheduled event, or null when the
     * event cannot be represented as a scheduler row.
     *
     * Closure and job tasks are CallbackEvent instances with no command
     * string, and nothing sensible can be stored for them.
     */
    protected function resolveIdentifier(Event $event): ?string
    {
        if ($event instanceof CallbackEvent) {
            return null;
        }

        $command = $event->command;

        if (! is_string($command) || trim($command) === '') {
            return null;
        }

        // Laravel prepends the PHP binary and the artisan path to every
        // command event, e.g. "'/usr/bin/php' 'artisan' inspire".
        $command = str_replace([
            ConsoleApplication::phpBinary(),
            ConsoleApplication::artisanBinary(),
        ], '', $command);

        $command = trim(preg_replace('#^\s*(php\s+)?(artisan\s+)?#i', '', trim($command)) ?? '');

        return $command === '' ? null : $command;
    }

    /**
     * The human-readable name, falling back to the command being run.
     */
    protected function resolveName(Event $event, string $identifier): string
    {
        return is_string($event->description) && $event->description !== ''
            ? $event->description
            : $identifier;
    }

    protected function resolveTimezone(Event $event): ?string
    {
        $timezone = $event->timezone;

        if ($timezone instanceof \DateTimeZone) {
            return $timezone->getName();
        }

        // The framework declares this property as non-nullable but leaves it
        // null whenever the schedule carries no timezone of its own.
        return blank($timezone) ? null : (string) $timezone;
    }

    /**
     * An entry is considered already imported when both the command it runs
     * and the frequency it runs at match, which keeps re-runs idempotent
     * without clobbering an operator's edits.
     */
    protected function alreadyImported(string $identifier, string $expression): bool
    {
        return Scheduler::query()
            ->where('identifier', $identifier)
            ->where('cron', $expression)
            ->exists();
    }

    protected function describe(Event $event): string
    {
        $summary = $event->getSummaryForDisplay();

        return blank($summary) ? 'Closure' : (string) $summary;
    }

    /**
     * @param  array<int, array<int, string|null>>  $imported
     * @param  array<int, array<int, string|null>>  $skippedExisting
     * @param  array<int, array<int, string|null>>  $skippedOwn
     * @param  array<int, array<int, string|null>>  $unsupported
     */
    protected function report(array $imported, array $skippedExisting, array $skippedOwn, array $unsupported, bool $dryRun): void
    {
        if ($imported !== []) {
            $this->info($dryRun
                ? 'The following entries would be imported:'
                : 'Imported the following entries:');

            $this->table(['Name', 'Identifier', 'Cron', 'Timezone', 'Enabled'], $imported);
        }

        if ($skippedExisting !== []) {
            $this->comment('Skipped, already imported:');
            $this->table(['Name', 'Identifier', 'Cron'], $skippedExisting);
        }

        if ($skippedOwn !== []) {
            $this->comment('Skipped, this package\'s own commands (importing the tick would make it dispatch itself):');
            $this->table(['Identifier', 'Cron'], $skippedOwn);
        }

        if ($unsupported !== []) {
            $this->warn('Skipped, unsupported (closure or job tasks cannot be stored as schedulers):');
            $this->table(['Task', 'Cron'], $unsupported);
        }

        $this->table(
            ['Result', 'Count'],
            [
                [$dryRun ? 'Would import' : 'Imported', (string) count($imported)],
                ['Skipped (existing)', (string) count($skippedExisting)],
                ['Skipped (own commands)', (string) count($skippedOwn)],
                ['Skipped (unsupported)', (string) count($unsupported)],
            ]
        );

        if ($dryRun) {
            $this->comment('Dry run: nothing was written.');

            return;
        }

        if ($imported !== []) {
            $this->comment($this->option('enabled')
                ? 'Imported schedulers are enabled. Remove the entries from your application schedule to avoid double firing.'
                : 'Imported schedulers are disabled. Enable them once the entries are removed from your application schedule.');
        }
    }
}
