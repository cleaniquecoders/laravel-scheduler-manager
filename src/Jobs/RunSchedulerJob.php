<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Jobs;

use CleaniqueCoders\LaravelSchedulerManager\Contracts\Runner;
use CleaniqueCoders\LaravelSchedulerManager\Data\RunResult;
use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunFailed;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunSkipped;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunStarted;
use CleaniqueCoders\LaravelSchedulerManager\Events\SchedulerRunSucceeded;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one scheduler execution: run bookkeeping, overlap locking and
 * event dispatch. The work itself belongs to the runner for the scheduler's
 * type, so adding a new type never means editing this class.
 */
class RunSchedulerJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public Scheduler $scheduler) {}

    public function handle(): void
    {
        $scheduler = $this->scheduler->fresh();

        if (! $scheduler instanceof Scheduler) {
            return;
        }

        $run = SchedulerRun::create([
            'scheduler_id' => $scheduler->id,
            'started_at' => now(),
            'status' => RunStatus::Running,
        ]);

        SchedulerRunStarted::dispatch($scheduler, $run);

        $lock = $this->acquireLock($scheduler);

        if ($lock === false) {
            $this->finish($scheduler, $run, RunResult::skipped(
                'Could not obtain lock: overlapping prevented'
            ));

            return;
        }

        try {
            $result = $this->runnerFor($scheduler)->execute()->result();
        } catch (\Throwable $e) {
            Log::error('RunSchedulerJob exception: '.$e->getMessage(), ['exception' => $e]);

            $result = RunResult::failed((string) $e);
        } finally {
            if ($lock instanceof Lock) {
                $lock->release();
            }
        }

        $this->finish($scheduler, $run, $result);
    }

    protected function runnerFor(Scheduler $scheduler): Runner
    {
        /** @var Runner $runner */
        $runner = App::make($scheduler->type->runner());

        return $runner->for($scheduler);
    }

    /**
     * Returns the acquired lock, false when overlapping is prevented and the
     * lock is already held, or null when overlap protection is off.
     */
    protected function acquireLock(Scheduler $scheduler): Lock|false|null
    {
        if (! $scheduler->prevent_overlap) {
            return null;
        }

        $lock = Cache::lock(
            "scheduler_manager:{$scheduler->id}:lock",
            config('scheduler-manager.lock_ttl', 3600)
        );

        return $lock->get() ? $lock : false;
    }

    protected function finish(Scheduler $scheduler, SchedulerRun $run, RunResult $result): void
    {
        $run->update([
            'status' => $result->status,
            'exit_code' => $result->exitCode,
            'output' => $result->output,
            'exception' => $result->exception,
            'finished_at' => now(),
        ]);

        if ($result->status !== RunStatus::Skipped) {
            $scheduler->forceFill([
                'last_run_at' => now(),
                'next_run_at' => $scheduler->calculateNextRunAt(),
            ])->save();
        }

        match ($result->status) {
            RunStatus::Success => SchedulerRunSucceeded::dispatch($scheduler, $run),
            RunStatus::Skipped => SchedulerRunSkipped::dispatch($scheduler, $run),
            default => SchedulerRunFailed::dispatch($scheduler, $run),
        };
    }
}
