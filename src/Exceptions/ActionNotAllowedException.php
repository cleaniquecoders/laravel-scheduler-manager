<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Exceptions;

use RuntimeException;

/**
 * Thrown when a scheduler names an action that is not present in the
 * scheduler-manager.actions whitelist.
 *
 * Resolving arbitrary identifiers out of the container would let anyone who
 * can create a scheduler instantiate any class in the application and have
 * the container invoke it with attacker-chosen arguments.
 */
class ActionNotAllowedException extends RuntimeException
{
    public static function forIdentifier(string $identifier): self
    {
        return new self(
            "Action [{$identifier}] is not registered in the scheduler-manager.actions whitelist. ".
            'Add it to config/scheduler-manager.php before scheduling it.'
        );
    }
}
