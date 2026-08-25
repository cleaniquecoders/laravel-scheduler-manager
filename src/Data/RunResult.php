<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Data;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;

/**
 * The outcome of a single scheduler execution.
 */
class RunResult
{
    public function __construct(
        public readonly RunStatus $status,
        public readonly ?int $exitCode = null,
        public readonly ?string $output = null,
        public readonly ?string $exception = null,
    ) {}

    public static function success(?int $exitCode = 0, ?string $output = null): self
    {
        return new self(RunStatus::Success, $exitCode, $output);
    }

    public static function failed(?string $exception = null, ?int $exitCode = null, ?string $output = null): self
    {
        return new self(RunStatus::Failed, $exitCode, $output, $exception);
    }

    public static function skipped(string $reason): self
    {
        return new self(RunStatus::Skipped, null, null, $reason);
    }

    public function succeeded(): bool
    {
        return $this->status === RunStatus::Success;
    }
}
