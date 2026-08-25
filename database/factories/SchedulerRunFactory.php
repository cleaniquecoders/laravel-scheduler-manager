<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Database\Factories;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchedulerRun>
 */
class SchedulerRunFactory extends Factory
{
    protected $model = SchedulerRun::class;

    public function definition(): array
    {
        return [
            'scheduler_id' => Scheduler::factory(),
            'started_at' => now(),
            'finished_at' => null,
            'status' => RunStatus::Running,
            'exit_code' => null,
            'output' => null,
            'exception' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Running,
            'finished_at' => null,
        ]);
    }

    public function successful(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Success,
            'finished_at' => now(),
            'exit_code' => 0,
            'output' => 'Done.',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Failed,
            'finished_at' => now(),
            'exit_code' => 1,
            'exception' => 'RuntimeException: something went wrong',
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Skipped,
            'finished_at' => now(),
            'exception' => 'Could not obtain lock: overlapping prevented',
        ]);
    }

    public function stale(int $hours = 6): static
    {
        return $this->state(fn () => [
            'status' => RunStatus::Running,
            'started_at' => now()->subHours($hours),
            'finished_at' => null,
        ]);
    }
}
