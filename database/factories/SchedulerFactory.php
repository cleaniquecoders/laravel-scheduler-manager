<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Database\Factories;

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scheduler>
 */
class SchedulerFactory extends Factory
{
    protected $model = Scheduler::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'type' => SchedulerType::Artisan,
            'identifier' => 'cache:clear',
            'payload' => null,
            'cron' => '* * * * *',
            'timezone' => config('app.timezone'),
            'enabled' => true,
            'prevent_overlap' => false,
            'last_run_at' => null,
            'next_run_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function action(string $identifier = 'send-report'): static
    {
        return $this->state(fn () => [
            'type' => SchedulerType::Action,
            'identifier' => $identifier,
        ]);
    }

    public function preventingOverlap(): static
    {
        return $this->state(fn () => ['prevent_overlap' => true]);
    }

    public function cron(string $expression): static
    {
        return $this->state(fn () => ['cron' => $expression]);
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function timezone(string $timezone): static
    {
        return $this->state(fn () => ['timezone' => $timezone]);
    }
}
