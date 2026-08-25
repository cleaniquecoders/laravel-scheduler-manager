<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Command;

class ToggleCommand extends Command
{
    protected $signature = 'scheduler-manager:toggle
                            {uuid : The scheduler UUID}
                            {--enable : Force enable}
                            {--disable : Force disable}';

    protected $description = 'Enable or disable a scheduler.';

    public function handle(): int
    {
        $scheduler = Scheduler::query()->uuid($this->argument('uuid'))->first();

        if (! $scheduler instanceof Scheduler) {
            $this->error("No scheduler found with UUID [{$this->argument('uuid')}].");

            return self::FAILURE;
        }

        $enabled = match (true) {
            (bool) $this->option('enable') => true,
            (bool) $this->option('disable') => false,
            default => ! $scheduler->enabled,
        };

        $scheduler->update(['enabled' => $enabled]);

        $this->info("[{$scheduler->name}] is now ".($enabled ? 'enabled' : 'disabled').'.');

        return self::SUCCESS;
    }
}
