<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Livewire;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Concerns\AuthorizesSchedulers;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use AuthorizesSchedulers;

    public function mount(): void
    {
        $this->authorizeScheduler('viewAny');
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $since = now()->subDay();

        return [
            'total' => Scheduler::count(),
            'enabled' => Scheduler::query()->enabled()->count(),
            'disabled' => Scheduler::query()->where('enabled', false)->count(),
            'succeeded' => SchedulerRun::query()->status(RunStatus::Success)->where('started_at', '>=', $since)->count(),
            'failed' => SchedulerRun::query()->status(RunStatus::Failed)->where('started_at', '>=', $since)->count(),
            'skipped' => SchedulerRun::query()->status(RunStatus::Skipped)->where('started_at', '>=', $since)->count(),
            'running' => SchedulerRun::query()->status(RunStatus::Running)->count(),
        ];
    }

    public function render(): View
    {
        $failing = Scheduler::query()
            ->with('latestRun')
            ->get()
            ->filter(fn (Scheduler $s) => $s->latestRun?->status === RunStatus::Failed)
            ->values();

        // Enabled, past due, and nothing has run since — the tick is not
        // reaching them, which is the failure mode operators miss most.
        $overdue = Scheduler::query()
            ->enabled()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', now()->subMinutes(5))
            ->orderBy('next_run_at')
            ->limit(10)
            ->get();

        $upcoming = Scheduler::query()
            ->enabled()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '>=', now())
            ->orderBy('next_run_at')
            ->limit(10)
            ->get();

        return view('scheduler-manager::livewire.dashboard', [
            'stats' => $this->stats(),
            'failing' => $failing,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
        ])->layout($this->layout());
    }
}
