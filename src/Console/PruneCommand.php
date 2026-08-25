<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Actions\PruneRuns;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'scheduler-manager:prune
                            {--days= : Delete runs older than this many days}
                            {--keep-last= : Runs to always keep per scheduler}
                            {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete scheduler run history beyond the retention window.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $action = (new PruneRuns(
            days: $this->option('days') !== null ? (int) $this->option('days') : null,
            keepLast: $this->option('keep-last') !== null ? (int) $this->option('keep-last') : null,
            dryRun: $dryRun,
        ))->execute();

        $this->info($dryRun
            ? "{$action->pruned()} run(s) would be deleted."
            : "Pruned {$action->pruned()} run(s).");

        return self::SUCCESS;
    }
}
