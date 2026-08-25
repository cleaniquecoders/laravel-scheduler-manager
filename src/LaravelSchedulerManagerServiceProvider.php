<?php

namespace CleaniqueCoders\LaravelSchedulerManager;

use CleaniqueCoders\LaravelSchedulerManager\Console\ListCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\PruneCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\ReapCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\RunCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\TickCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\ToggleCommand;
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
            ->hasCommands([
                TickCommand::class,
                RunCommand::class,
                ListCommand::class,
                ToggleCommand::class,
                PruneCommand::class,
                ReapCommand::class,
            ]);
    }
}
