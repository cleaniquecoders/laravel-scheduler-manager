<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Contracts;

use CleaniqueCoders\LaravelSchedulerManager\Data\RunResult;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\Traitify\Contracts\Execute;

interface Runner extends Execute
{
    /**
     * Run the bound scheduler.
     *
     * Narrows Execute::execute(): self so the fluent ->execute()->result()
     * chain resolves to the runner rather than the base contract.
     */
    public function execute(): static;

    /**
     * Bind the scheduler this runner should execute.
     */
    public function for(Scheduler $scheduler): static;

    /**
     * The outcome of the last execute() call.
     */
    public function result(): RunResult;
}
