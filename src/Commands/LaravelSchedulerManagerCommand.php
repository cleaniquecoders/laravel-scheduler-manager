<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Commands;

use Illuminate\Console\Command;

class LaravelSchedulerManagerCommand extends Command
{
    public $signature = 'laravel-scheduler-manager';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
