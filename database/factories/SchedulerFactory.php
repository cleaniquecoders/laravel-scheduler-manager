<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Database\Factories;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchedulerFactory extends Factory
{
    protected $model = Scheduler::class;

    public function definition()
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => 'artisan',
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
}
