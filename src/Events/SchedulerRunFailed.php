<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Events;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SchedulerRunFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Scheduler $scheduler,
        public SchedulerRun $run,
    ) {}
}
