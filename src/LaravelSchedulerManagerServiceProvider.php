<?php

namespace CleaniqueCoders\LaravelSchedulerManager;

use CleaniqueCoders\LaravelSchedulerManager\Commands\LaravelSchedulerManagerCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSchedulerManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('scheduler-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_schedulers_table')
            ->hasCommand(LaravelSchedulerManagerCommand::class)
            ->hasCommand(\CleaniqueCoders\LaravelSchedulerManager\Console\TickCommand::class);
    }
}
