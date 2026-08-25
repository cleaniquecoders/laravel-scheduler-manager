<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Runners;

use CleaniqueCoders\LaravelSchedulerManager\Data\RunResult;
use CleaniqueCoders\LaravelSchedulerManager\Exceptions\CommandNotAllowedException;
use Illuminate\Support\Facades\Artisan;

class ArtisanRunner extends AbstractRunner
{
    public function execute(): static
    {
        $command = $this->scheduler()->identifier;

        $this->guardAgainstDisallowedCommand($command);

        $exit = Artisan::call($command, $this->payload());
        $output = Artisan::output();

        $this->result = $exit === 0
            ? RunResult::success($exit, $output)
            : RunResult::failed("Command exited with code {$exit}.", $exit, $output);

        return $this;
    }

    /**
     * An empty allow-list means every registered command is permitted, which
     * preserves the historical behaviour. Populating it opts an application
     * into a stricter policy.
     */
    protected function guardAgainstDisallowedCommand(string $command): void
    {
        $allowed = config('scheduler-manager.allowed_commands', []);

        if ($allowed === [] || $allowed === null) {
            return;
        }

        if (! in_array($command, (array) $allowed, true)) {
            throw CommandNotAllowedException::forCommand($command);
        }
    }
}
