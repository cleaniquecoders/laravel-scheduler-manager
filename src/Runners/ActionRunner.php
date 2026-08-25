<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Runners;

use CleaniqueCoders\LaravelSchedulerManager\Data\RunResult;
use CleaniqueCoders\LaravelSchedulerManager\Exceptions\ActionNotAllowedException;
use Illuminate\Support\Facades\App;
use RuntimeException;

class ActionRunner extends AbstractRunner
{
    public function execute(): static
    {
        $identifier = $this->scheduler()->identifier;

        $action = $this->resolveAllowedAction($identifier);

        $result = $this->invoke($action);

        $this->result = RunResult::success(0, $this->stringify($result));

        return $this;
    }

    /**
     * Resolve the action strictly from the configured whitelist.
     *
     * There is deliberately no fallback to treating the identifier itself as a
     * class name: identifier is an operator-supplied string column, and such a
     * fallback would allow instantiating any class in the application.
     */
    protected function resolveAllowedAction(string $identifier): mixed
    {
        $actions = config('scheduler-manager.actions', []);

        if (! is_array($actions) || ! array_key_exists($identifier, $actions)) {
            throw ActionNotAllowedException::forIdentifier($identifier);
        }

        $action = $actions[$identifier];

        return is_string($action) ? App::make($action) : $action;
    }

    protected function invoke(mixed $action): mixed
    {
        $payload = $this->payload();

        // Closures and callable arrays configured directly in the whitelist.
        if (! is_object($action) || $action instanceof \Closure) {
            if (! is_callable($action)) {
                throw new RuntimeException('Configured action is not callable.');
            }

            return App::call($action, $payload);
        }

        // App::call on both branches so the container can inject dependencies
        // alongside the payload arguments.
        if (method_exists($action, 'handle')) {
            return App::call([$action, 'handle'], $payload);
        }

        if (method_exists($action, '__invoke')) {
            return App::call([$action, '__invoke'], $payload);
        }

        throw new RuntimeException('Action is not invokable: '.get_class($action));
    }

    protected function stringify(mixed $result): ?string
    {
        if ($result === null || is_string($result)) {
            return $result;
        }

        return json_encode($result) ?: null;
    }
}
