<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Tests\Feature;

use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use CleaniqueCoders\LaravelSchedulerManager\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RunSchedulerJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations from package stubs
        foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__.'/../../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    public function test_artisan_command_runs_and_records_output()
    {
        // Use a simple artisan command that exists: 'list' is safe but outputs many lines.
        $scheduler = Scheduler::create([
            'name' => 'Test Artisan',
            'type' => 'artisan',
            'identifier' => 'cache:clear',
            'payload' => null,
            'cron' => '* * * * *',
            'timezone' => config('app.timezone'),
            'enabled' => true,
            'prevent_overlap' => false,
        ]);

        // Dispatch job synchronously
        (new RunSchedulerJob($scheduler))->handle();

        $run = SchedulerRun::where('scheduler_id', $scheduler->id)->first();

        $this->assertNotNull($run);
        $this->assertEquals('success', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertIsString($run->output);
    }

    public function test_prevent_overlap_records_failed_when_lock_unavailable()
    {
        $scheduler = Scheduler::create([
            'name' => 'Test Prevent Overlap',
            'type' => 'artisan',
            'identifier' => 'cache:clear',
            'payload' => null,
            'cron' => '* * * * *',
            'timezone' => config('app.timezone'),
            'enabled' => true,
            'prevent_overlap' => true,
        ]);

        // create a lock so the job cannot obtain it
        $lockKey = "scheduler_manager:{$scheduler->id}:lock";
        $lock = Cache::lock($lockKey, 300);
        $lock->get();

        (new RunSchedulerJob($scheduler))->handle();

        $run = SchedulerRun::where('scheduler_id', $scheduler->id)->first();

        $this->assertNotNull($run);
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('overlapping prevented', $run->exception);

        // release lock
        $lock->release();
    }
}
