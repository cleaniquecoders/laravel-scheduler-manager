<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Tests\Feature;

use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use CleaniqueCoders\LaravelSchedulerManager\Tests\TestCase;

class TickCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    public function test_tick_dispatches_job_for_due_scheduler()
    {
        $scheduler = Scheduler::create([
            'name' => 'Tick Test',
            'type' => 'artisan',
            'identifier' => 'cache:clear',
            'payload' => null,
            'cron' => '* * * * *',
            'timezone' => config('app.timezone'),
            'enabled' => true,
            'prevent_overlap' => false,
        ]);

        // Run the command synchronously
        $this->artisan('scheduler-manager:tick');

        $run = SchedulerRun::where('scheduler_id', $scheduler->id)->first();

        $this->assertNotNull($run);
    }
}
