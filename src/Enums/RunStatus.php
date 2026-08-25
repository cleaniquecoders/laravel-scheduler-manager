<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Enums;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum;

enum RunStatus: string implements Enum
{
    use InteractsWithEnum;

    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Running => 'The run has started and has not reported a result yet.',
            self::Success => 'The run completed without error.',
            self::Failed => 'The run raised an exception or returned a non-zero exit code.',
            self::Skipped => 'The run was suppressed because an overlapping run held the lock.',
        };
    }

    /**
     * Statuses that represent a finished run.
     */
    public static function terminal(): array
    {
        return [self::Success, self::Failed, self::Skipped];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }
}
