<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CleaniqueCoders\LaravelSchedulerManager\LaravelSchedulerManager
 */
class LaravelSchedulerManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CleaniqueCoders\LaravelSchedulerManager\LaravelSchedulerManager::class;
    }
}
