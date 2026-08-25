<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Runners;

use CleaniqueCoders\LaravelSchedulerManager\Contracts\Runner;
use CleaniqueCoders\LaravelSchedulerManager\Data\RunResult;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use RuntimeException;

abstract class AbstractRunner implements Runner
{
    protected ?Scheduler $scheduler = null;

    protected ?RunResult $result = null;

    public function for(Scheduler $scheduler): static
    {
        $this->scheduler = $scheduler;

        return $this;
    }

    public function result(): RunResult
    {
        if (! $this->result instanceof RunResult) {
            throw new RuntimeException('Runner has not been executed yet.');
        }

        return $this->result;
    }

    protected function scheduler(): Scheduler
    {
        if (! $this->scheduler instanceof Scheduler) {
            throw new RuntimeException('No scheduler bound to this runner. Call for() first.');
        }

        return $this->scheduler;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return (array) ($this->scheduler()->payload ?? []);
    }
}
