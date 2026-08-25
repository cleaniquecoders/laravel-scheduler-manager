<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Actions;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use CleaniqueCoders\Traitify\Contracts\Execute;

/**
 * Reclassify runs left in the Running state by a worker that died before it
 * could record a result.
 */
class ReapStaleRuns implements Execute
{
    protected int $reaped = 0;

    public function __construct(protected ?int $thresholdSeconds = null) {}

    public function execute(): self
    {
        $threshold = $this->thresholdSeconds
            ?? (int) config('scheduler-manager.stale_run_threshold', 3600);

        $this->reaped = SchedulerRun::query()
            ->where('status', RunStatus::Running)
            ->whereNull('finished_at')
            ->where('started_at', '<', now()->subSeconds($threshold))
            ->update([
                'status' => RunStatus::Failed,
                'finished_at' => now(),
                'exception' => 'Run abandoned: no result recorded within '
                    ."{$threshold}s. The worker most likely terminated mid-run.",
            ]);

        return $this;
    }

    public function reaped(): int
    {
        return $this->reaped;
    }
}
