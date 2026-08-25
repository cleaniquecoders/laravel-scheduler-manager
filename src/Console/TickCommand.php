<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Actions\ReapStaleRuns;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Command;

class TickCommand extends Command
{
    protected $signature = 'scheduler-manager:tick';

    protected $description = 'Check schedulers and dispatch due ones.';

    public function handle(): int
    {
        if (config('scheduler-manager.reap_on_tick', true)) {
            (new ReapStaleRuns)->execute();
        }

        $dispatched = 0;

        Scheduler::query()->enabled()->each(function (Scheduler $scheduler) use (&$dispatched) {
            if (! $scheduler->isCronValid()) {
                $this->error("Invalid cron for scheduler {$scheduler->id}: {$scheduler->cron}");

                return;
            }

            if ($scheduler->isDue()) {
                RunSchedulerJob::dispatch($scheduler);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} scheduler(s).");

        return self::SUCCESS;
    }
}
