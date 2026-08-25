<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Exceptions;

use RuntimeException;

/**
 * Thrown when an artisan scheduler names a command outside the configured
 * allow-list, where one is in force.
 */
class CommandNotAllowedException extends RuntimeException
{
    public static function forCommand(string $command): self
    {
        return new self(
            "Artisan command [{$command}] is not permitted by scheduler-manager.allowed_commands."
        );
    }
}
