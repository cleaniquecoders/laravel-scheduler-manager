<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Command;

class RunCommand extends Command
{
    protected $signature = 'scheduler-manager:run
                            {uuid : The scheduler UUID}
                            {--sync : Run inline instead of dispatching to the queue}';

    protected $description = 'Run a scheduler immediately, off-schedule.';

    public function handle(): int
    {
        $scheduler = Scheduler::query()->uuid($this->argument('uuid'))->first();

        if (! $scheduler instanceof Scheduler) {
            $this->error("No scheduler found with UUID [{$this->argument('uuid')}].");

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            (new RunSchedulerJob($scheduler))->handle();
            $this->info("Ran [{$scheduler->name}] synchronously.");

            return self::SUCCESS;
        }

        RunSchedulerJob::dispatch($scheduler);
        $this->info("Dispatched [{$scheduler->name}].");

        return self::SUCCESS;
    }
}
