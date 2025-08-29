<?php

namespace CleaniqueCoders\LaravelSchedulerManager;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use CleaniqueCoders\LaravelSchedulerManager\Commands\LaravelSchedulerManagerCommand;

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
            ->name('laravel-scheduler-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_scheduler_manager_table')
            ->hasCommand(LaravelSchedulerManagerCommand::class);
    }
}
