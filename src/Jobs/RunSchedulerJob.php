<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Jobs;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
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

    public function __construct(public Scheduler $scheduler) {}

    public function handle(): void
    {
        $scheduler = $this->scheduler->fresh();

        $payload = (array) ($scheduler->payload ?? []);

        $run = SchedulerRun::create([
            'scheduler_id' => $scheduler->id,
            'started_at' => now(),
            'status' => RunStatus::Running,
        ]);

        $lockKey = "scheduler_manager:{$scheduler->id}:lock";
        $lockTtl = config('scheduler-manager.lock_ttl', 3600);

        $lock = null;
        if ($scheduler->prevent_overlap) {
            $lock = Cache::lock($lockKey, $lockTtl);

            if (! $lock->get()) {
                // Nothing failed here: an earlier run is still holding the lock,
                // so this tick is deliberately suppressed.
                $run->update([
                    'finished_at' => now(),
                    'status' => RunStatus::Skipped,
                    'exception' => 'Could not obtain lock: overlapping prevented',
                ]);

                return;
            }
        }

        try {
            if ($scheduler->type === SchedulerType::Artisan) {
                $exit = Artisan::call($scheduler->identifier, $payload);

                $run->update([
                    'finished_at' => now(),
                    'status' => $exit === 0 ? RunStatus::Success : RunStatus::Failed,
                    'exit_code' => $exit,
                    'output' => Artisan::output(),
                ]);
            } else {
                $result = $this->runAction($scheduler->identifier, $payload);

                $run->update([
                    'finished_at' => now(),
                    'status' => RunStatus::Success,
                    'exit_code' => 0,
                    'output' => is_string($result) ? $result : json_encode($result),
                ]);
            }

            $scheduler->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('RunSchedulerJob exception: '.$e->getMessage(), ['exception' => $e]);

            $run->update([
                'finished_at' => now(),
                'status' => RunStatus::Failed,
                'exit_code' => null,
                'output' => null,
                'exception' => (string) $e,
            ]);
        } finally {
            if ($lock instanceof Lock) {
                $lock->release();
            }
        }
    }

    /**
     * Resolve and invoke a configured action.
     */
    protected function runAction(string $identifier, array $payload): mixed
    {
        $actions = config('scheduler-manager.actions', []);
        $action = $actions[$identifier] ?? $identifier;

        // The whitelist may map straight to a closure or callable array.
        if (! is_string($action)) {
            if (! is_callable($action)) {
                throw new \RuntimeException('Configured action is not callable: '.$identifier);
            }

            return App::call($action, $payload);
        }

        $instance = App::make($action);

        // Both branches go through App::call so the container can inject
        // dependencies alongside the payload arguments.
        if (method_exists($instance, 'handle')) {
            return App::call([$instance, 'handle'], $payload);
        }

        if (method_exists($instance, '__invoke')) {
            return App::call([$instance, '__invoke'], $payload);
        }

        throw new \RuntimeException('Action is not invokable: '.get_class($instance));
    }
}
