<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Console;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'scheduler-manager:list
                            {--enabled : Only enabled schedulers}
                            {--failing : Only schedulers whose latest run failed}';

    protected $description = 'List the registered schedulers.';

    public function handle(): int
    {
        $schedulers = Scheduler::query()
            ->with('latestRun')
            ->when($this->option('enabled'), fn ($q) => $q->enabled())
            ->orderBy('name')
            ->get()
            ->when($this->option('failing'), fn ($rows) => $rows->filter(
                fn (Scheduler $s) => $s->latestRun?->status === RunStatus::Failed
            ));

        if ($schedulers->isEmpty()) {
            $this->comment('No schedulers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['UUID', 'Name', 'Type', 'Identifier', 'Cron', 'Timezone', 'Enabled', 'Last Run', 'Next Run'],
            $schedulers->map(fn (Scheduler $s) => [
                $s->uuid,
                $s->name,
                $s->type->value,
                $s->identifier,
                $s->cron,
                $s->resolveTimezone(),
                $s->enabled ? 'yes' : 'no',
                $s->latestRun?->status->value ?? '-',
                $s->next_run_at?->toDateTimeString() ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
