<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Livewire;

use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\LaravelSchedulerManager\Jobs\RunSchedulerJob;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\Concerns\AuthorizesSchedulers;
use CleaniqueCoders\LaravelSchedulerManager\Models\Scheduler;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SchedulerIndex extends Component
{
    use AuthorizesSchedulers, WithPagination;

    /**
     * Columns the table may be ordered by.
     *
     * @var list<string>
     */
    protected const SORTABLE = ['name', 'last_run_at', 'next_run_at'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public function mount(): void
    {
        $this->authorizeScheduler('viewAny');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, static::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    /**
     * Both properties are client-writable — they are bound to the query string —
     * so the column reaches the query builder from user input. Resolve it
     * against the whitelist here as well as in sortBy(), which a request that
     * sets the property directly never goes through.
     */
    protected function sortColumn(): string
    {
        return in_array($this->sort, static::SORTABLE, true) ? $this->sort : 'name';
    }

    public function toggle(string $uuid): void
    {
        $scheduler = Scheduler::query()->uuid($uuid)->firstOrFail();

        $this->authorizeScheduler('toggle', $scheduler);

        $scheduler->update(['enabled' => ! $scheduler->enabled]);

        $this->dispatch('scheduler-toggled', name: $scheduler->name, enabled: $scheduler->enabled);
    }

    public function runNow(string $uuid): void
    {
        $scheduler = Scheduler::query()->uuid($uuid)->firstOrFail();

        $this->authorizeScheduler('run', $scheduler);

        RunSchedulerJob::dispatch($scheduler);

        $this->dispatch('scheduler-dispatched', name: $scheduler->name);
    }

    public function delete(string $uuid): void
    {
        $scheduler = Scheduler::query()->uuid($uuid)->firstOrFail();

        $this->authorizeScheduler('delete', $scheduler);

        $scheduler->delete();

        $this->dispatch('scheduler-deleted', name: $scheduler->name);
    }

    public function render(): View
    {
        $schedulers = Scheduler::query()
            // Eager loaded: without it the last-run column is an N+1 across the page.
            ->with('latestRun')
            ->when($this->search !== '', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('identifier', 'like', "%{$this->search}%")
            ))
            ->when($this->type !== '', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->state !== '', fn (Builder $q) => $q->where('enabled', $this->state === 'enabled'))
            ->orderBy($this->sortColumn(), $this->direction === 'desc' ? 'desc' : 'asc')
            ->paginate($this->perPage());

        return view('scheduler-manager::livewire.scheduler-index', [
            'schedulers' => $schedulers,
            'types' => SchedulerType::options(),
        ])->layout($this->layout());
    }
}
