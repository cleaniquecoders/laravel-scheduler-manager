<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Livewire;

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Concerns\AuthorizesSchedulers;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidCronExpression;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidSchedulerIdentifier;
use CleaniqueCoders\LaravelSchedulerManager\Rules\ValidTimezone;
use Cron\CronExpression;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SchedulerForm extends Component
{
    use AuthorizesSchedulers;

    public ?Scheduler $scheduler = null;

    public string $name = '';

    public string $type = 'artisan';

    public string $identifier = '';

    public string $payload = '';

    public string $cron = '* * * * *';

    public ?string $timezone = null;

    public bool $enabled = true;

    public bool $prevent_overlap = false;

    /**
     * Shortcuts offered alongside raw cron entry.
     *
     * @var array<string, string>
     */
    public array $presets = [
        'Every minute' => '* * * * *',
        'Every five minutes' => '*/5 * * * *',
        'Hourly' => '0 * * * *',
        'Daily at midnight' => '0 0 * * *',
        'Weekly on Monday' => '0 0 * * 1',
        'Monthly' => '0 0 1 * *',
    ];

    public function mount(?Scheduler $scheduler = null): void
    {
        if ($scheduler?->exists) {
            $this->authorizeScheduler('update', $scheduler);

            $this->scheduler = $scheduler;
            $this->name = $scheduler->name;
            $this->type = $scheduler->type->value;
            $this->identifier = $scheduler->identifier;
            $this->payload = $scheduler->payload ? (json_encode($scheduler->payload, JSON_PRETTY_PRINT) ?: '') : '';
            $this->cron = $scheduler->cron;
            $this->timezone = $scheduler->timezone;
            $this->enabled = $scheduler->enabled;
            $this->prevent_overlap = $scheduler->prevent_overlap;

            return;
        }

        $this->authorizeScheduler('create');

        $this->timezone = config('app.timezone');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', SchedulerType::values())],
            'identifier' => ['required', 'string', 'max:255', new ValidSchedulerIdentifier($this->type)],
            'payload' => ['nullable', 'string', 'json'],
            'cron' => ['required', 'string', new ValidCronExpression],
            'timezone' => ['nullable', 'string', new ValidTimezone],
            'enabled' => ['boolean'],
            'prevent_overlap' => ['boolean'],
        ];
    }

    public function applyPreset(string $expression): void
    {
        $this->cron = $expression;
    }

    /**
     * The next few fire times, so an operator can sanity-check an expression
     * before saving it rather than discovering the mistake a day later.
     *
     * The preview is computed on every keystroke, from values the operator is
     * still typing, so it must never raise: an unparseable expression or an
     * unknown timezone yields no preview and is reported by validation on save.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function upcomingRuns(): array
    {
        if (! CronExpression::isValidExpression($this->cron)) {
            return [];
        }

        $timezone = $this->timezone ?: config('app.timezone', 'UTC');

        try {
            $cron = new CronExpression($this->cron);
            $from = Carbon::now($timezone);

            return collect(range(0, 4))
                ->map(function (int $index) use ($cron, $from, $timezone) {
                    $next = Carbon::instance($cron->getNextRunDate($from, $index));

                    return $next->format('Y-m-d H:i').' '.$timezone;
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function save(): void
    {
        $this->authorizeScheduler(
            $this->scheduler?->exists ? 'update' : 'create',
            $this->scheduler
        );

        $data = $this->validate();

        $data['payload'] = $data['payload'] ? json_decode($data['payload'], true) : null;

        if ($this->scheduler?->exists) {
            $this->scheduler->update($data);
        } else {
            $this->scheduler = Scheduler::create($data);
        }

        $this->dispatch('scheduler-saved', name: $this->scheduler->name);

        $this->redirectRoute(
            config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.').'index',
            navigate: true
        );
    }

    public function delete(): void
    {
        if (! $this->scheduler?->exists) {
            return;
        }

        $this->authorizeScheduler('delete', $this->scheduler);

        $this->scheduler->delete();

        $this->redirectRoute(
            config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.').'index',
            navigate: true
        );
    }

    public function render(): View
    {
        return view('scheduler-manager::livewire.scheduler-form', [
            'types' => SchedulerType::options(),
            'actions' => array_keys(config('scheduler-manager.actions', [])),
            'timezones' => timezone_identifiers_list(),
            'upcoming' => $this->upcomingRuns(),
        ])->layout($this->layout());
    }
}
