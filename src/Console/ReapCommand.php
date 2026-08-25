<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Actions\ReapStaleRuns;
use Illuminate\Console\Command;

class ReapCommand extends Command
{
    protected $signature = 'scheduler-manager:reap
                            {--threshold= : Seconds after which an unfinished run is abandoned}';

    protected $description = 'Mark abandoned scheduler runs as failed.';

    public function handle(): int
    {
        $action = (new ReapStaleRuns(
            $this->option('threshold') !== null ? (int) $this->option('threshold') : null
        ))->execute();

        $this->info("Reaped {$action->reaped()} abandoned run(s).");

        return self::SUCCESS;
    }
}
