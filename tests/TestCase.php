<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Tests;

use CleaniqueCoders\LaravelSchedulerManager\LaravelSchedulerManagerServiceProvider;
use CleaniqueCoders\Traitify\TraitifyServiceProvider;
use Flux\FluxServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CleaniqueCoders\\LaravelSchedulerManager\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            TraitifyServiceProvider::class,
            LaravelSchedulerManagerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * The package ships its migrations as .stub files so they can be published
     * with a timestamp, which means the framework migrator cannot discover them.
     * Include and run them directly instead.
     */
    protected function defineDatabaseMigrations(): void
    {
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }
}
