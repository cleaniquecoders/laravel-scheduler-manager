<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Database\Eloquent\Builder schedulers()
 * @method static array actions()
 * @method static bool allowsAction(string $identifier)
 * @method static void run(\CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler|string $scheduler)
 *
 * @see \CleaniqueCoders\LaravelSchedulerManager\LaravelSchedulerManager
 */
class LaravelSchedulerManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CleaniqueCoders\LaravelSchedulerManager\LaravelSchedulerManager::class;
    }
}
