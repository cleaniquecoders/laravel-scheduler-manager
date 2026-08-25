<?php

namespace CleaniqueCoders\LaravelSchedulerManager;

use CleaniqueCoders\LaravelSchedulerManager\Console\ImportCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\ListCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\PruneCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\ReapCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\RunCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\TickCommand;
use CleaniqueCoders\LaravelSchedulerManager\Console\ToggleCommand;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Dashboard;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerForm;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerIndex;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerRuns;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Policies\SchedulerPolicy;
use Flux\FluxServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSchedulerManagerServiceProvider extends PackageServiceProvider
{
    /**
     * Livewire components backing the management UI, keyed by the alias the
     * package views and routes refer to them by.
     *
     * @var array<string, class-string<Component>>
     */
    protected const COMPONENTS = [
        'scheduler-manager::scheduler-index' => SchedulerIndex::class,
        'scheduler-manager::scheduler-form' => SchedulerForm::class,
        'scheduler-manager::scheduler-runs' => SchedulerRuns::class,
        'scheduler-manager::dashboard' => Dashboard::class,
    ];

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
                ImportCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        Gate::policy(Scheduler::class, SchedulerPolicy::class);

        if (! config('scheduler-manager.ui.enabled', true)) {
            return;
        }

        $this->guardAgainstMissingFlux();

        $this->registerLivewireComponents();
        $this->registerRoutes();
    }

    /**
     * The UI is rendered with Flux, which is intentionally not a hard dependency
     * of this MIT-licensed package. Fail loudly at boot rather than serving a
     * page of unrendered <flux:*> tags.
     */
    protected function guardAgainstMissingFlux(): void
    {
        if (class_exists(FluxServiceProvider::class)) {
            return;
        }

        throw new RuntimeException(
            'The scheduler manager UI requires Flux, which is not installed. '
            .'Run "composer require livewire/flux" (the free tier is sufficient), '
            .'or set scheduler-manager.ui.enabled to false to install the scheduling engine without any HTTP surface.'
        );
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        // Livewire resolves a namespaced alias such as "scheduler-manager::foo"
        // exclusively through its registered class namespaces:
        // Finder::resolveClassComponentClassName() returns null once it sees a
        // "::" without ever consulting the explicit component map. Registering
        // the namespace is therefore required, not merely an optimisation.
        Livewire::addNamespace(
            'scheduler-manager',
            classNamespace: __NAMESPACE__.'\\Livewire',
        );

        foreach (static::COMPONENTS as $alias => $class) {
            Livewire::component($alias, $class);
        }
    }

    protected function registerRoutes(): void
    {
        $attributes = [
            'middleware' => config('scheduler-manager.middleware', ['web']),
        ];

        // An empty prefix mounts the UI at the site root, as the config promises.
        $prefix = trim((string) config('scheduler-manager.route_prefix', ''), '/');

        if ($prefix !== '') {
            $attributes['prefix'] = $prefix;
        }

        Route::group($attributes, function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}
