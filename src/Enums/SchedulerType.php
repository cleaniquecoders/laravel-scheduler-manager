<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Enums;

use CleaniqueCoders\LaravelSchedulerManager\Contracts\Runner;
use CleaniqueCoders\LaravelSchedulerManager\Runners\ActionRunner;
use CleaniqueCoders\LaravelSchedulerManager\Runners\ArtisanRunner;
use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

enum SchedulerType: string implements Enum
{
    use InteractsWithEnum;

    case Artisan = 'artisan';
    case Action = 'action';

    public function label(): string
    {
        return match ($this) {
            self::Artisan => 'Artisan Command',
            self::Action => 'Action Class',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Artisan => 'Runs a registered Artisan command through Artisan::call().',
            self::Action => 'Resolves a whitelisted action from config and invokes it.',
        };
    }

    /**
     * The runner responsible for executing this type of scheduler.
     *
     * @return class-string<Runner>
     */
    public function runner(): string
    {
        return match ($this) {
            self::Artisan => ArtisanRunner::class,
            self::Action => ActionRunner::class,
        };
    }
}
