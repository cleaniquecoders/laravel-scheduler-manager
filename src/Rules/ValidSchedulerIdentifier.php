<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Rules;

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Artisan;

/**
 * Usability guard only. The security boundary is ActionRunner, which resolves
 * strictly from the whitelist regardless of what passed validation.
 */
class ValidSchedulerIdentifier implements ValidationRule
{
    public function __construct(protected SchedulerType|string|null $type = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute is required.');

            return;
        }

        $type = $this->type instanceof SchedulerType
            ? $this->type
            : SchedulerType::tryFrom((string) $this->type);

        match ($type) {
            SchedulerType::Action => $this->validateAction($value, $fail),
            SchedulerType::Artisan => $this->validateCommand($value, $fail),
            default => null,
        };
    }

    protected function validateAction(string $value, Closure $fail): void
    {
        $actions = config('scheduler-manager.actions', []);

        if (! is_array($actions) || ! array_key_exists($value, $actions)) {
            $fail('The :attribute must be one of the actions registered in config/scheduler-manager.php.');
        }
    }

    protected function validateCommand(string $value, Closure $fail): void
    {
        if (! array_key_exists($value, Artisan::all())) {
            $fail('The :attribute must be a registered Artisan command.');

            return;
        }

        $allowed = config('scheduler-manager.allowed_commands', []);

        if (is_array($allowed) && $allowed !== [] && ! in_array($value, $allowed, true)) {
            $fail('The :attribute is not permitted by scheduler-manager.allowed_commands.');
        }
    }
}
