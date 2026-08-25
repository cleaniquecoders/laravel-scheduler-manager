<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Livewire;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Concerns\AuthorizesSchedulers;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SchedulerRuns extends Component
{
    use AuthorizesSchedulers, WithPagination;

    public ?Scheduler $scheduler = null;

    #[Url(except: '')]
    public string $status = '';

    public ?int $expanded = null;

    public function mount(?Scheduler $scheduler = null): void
    {
        if ($scheduler?->exists) {
            $this->authorizeScheduler('view', $scheduler);
            $this->scheduler = $scheduler;

            return;
        }

        $this->authorizeScheduler('viewAny');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function expand(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render(): View
    {
        $runs = SchedulerRun::query()
            ->with('scheduler')
            ->when($this->scheduler, fn (Builder $q) => $q->where('scheduler_id', $this->scheduler->id))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->latest('started_at')
            ->paginate($this->perPage());

        return view('scheduler-manager::livewire.scheduler-runs', [
            'runs' => $runs,
            'statuses' => RunStatus::options(),
        ])->layout($this->layout());
    }
}
