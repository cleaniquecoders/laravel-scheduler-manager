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
        Scheduler::query()->enabled()->each(function (Scheduler $scheduler) {
            try {
                $cron = new CronExpression($scheduler->cron);
                $due = $cron->isDue(Carbon::now($scheduler->resolveTimezone()));
            } catch (\Throwable $e) {
                $this->error('Invalid cron for scheduler '.$scheduler->id.': '.$e->getMessage());

                return;
            }

            if ($due) {
                RunSchedulerJob::dispatch($scheduler);
            }
        });

        return self::SUCCESS;
    }
}
