<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Enums;

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
}
