<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Jobs;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunSchedulerJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public Scheduler $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function handle(): void
    {
        $scheduler = $this->scheduler->fresh();

        $payload = (array) ($scheduler->payload ?? []);

        // create initial run record
        $run = SchedulerRun::create([
            'scheduler_id' => $scheduler->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $lockKey = "scheduler_manager:{$scheduler->id}:lock";
        $lockTtl = config('scheduler-manager.lock_ttl', 3600);

        $lock = null;
        if ($scheduler->prevent_overlap) {
            $lock = Cache::lock($lockKey, $lockTtl);
            if (! $lock->get()) {
                $run->update([
                    'finished_at' => now(),
                    'status' => 'failed',
                    'exit_code' => null,
                    'output' => null,
                    'exception' => 'Could not obtain lock: overlapping prevented',
                ]);

                return;
            }
        }

        try {
            if ($scheduler->type === 'artisan') {
                // Run artisan command
                $exit = Artisan::call($scheduler->identifier, $payload);
                $output = Artisan::output();

                $run->update([
                    'finished_at' => now(),
                    'status' => $exit === 0 ? 'success' : 'failed',
                    'exit_code' => $exit,
                    'output' => $output,
                ]);
            } else {
                // Resolve action class or callable
                $actions = config('scheduler-manager.actions', []);
                $actionClass = $actions[$scheduler->identifier] ?? $scheduler->identifier;

                $action = App::make($actionClass);

                // Try handle or __invoke
                $result = null;
                if (is_callable([$action, 'handle'])) {
                    $result = App::call([$action, 'handle'], $payload);
                } elseif (is_callable($action)) {
                    $result = $action(...array_values($payload));
                } elseif (is_callable([$action, '__invoke'])) {
                    $result = App::call([$action, '__invoke'], $payload);
                } else {
                    throw new \RuntimeException('Action is not invokable: '.get_class($action));
                }

                $run->update([
                    'finished_at' => now(),
                    'status' => 'success',
                    'exit_code' => 0,
                    'output' => is_string($result) ? $result : json_encode($result),
                ]);
            }

            // update scheduler last_run_at
            $scheduler->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('RunSchedulerJob exception: '.$e->getMessage(), ['exception' => $e]);

            $run->update([
                'finished_at' => now(),
                'status' => 'failed',
                'exit_code' => null,
                'output' => null,
                'exception' => (string) $e,
            ]);
        } finally {
            if ($lock instanceof \Illuminate\Contracts\Cache\Lock) {
                $lock->release();
            }
        }
    }
}
