<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use Carbon\Carbon;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Cron\CronExpression;
use Illuminate\Console\Command;

class TickCommand extends Command
{
    protected $signature = 'scheduler-manager:tick';

    protected $description = 'Check schedulers and dispatch due ones.';

    public function handle(): int
    {
        $now = Carbon::now();

        $schedulers = Scheduler::where('enabled', true)->get();

        foreach ($schedulers as $scheduler) {
            try {
                $cron = CronExpression::factory($scheduler->cron);
                $due = $cron->isDue($now->toDateTimeString());
            } catch (\Throwable $e) {
                $this->error('Invalid cron for scheduler '.$scheduler->id.': '.$e->getMessage());

                continue;
            }

            if ($due) {
                RunSchedulerJob::dispatch($scheduler);
            }
        }

        return 0;
    }
}
