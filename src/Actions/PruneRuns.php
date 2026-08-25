<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Actions;

use CleaniqueCoders\LaravelSchedulerManager\Models\SchedulerRun;
use CleaniqueCoders\Traitify\Contracts\Execute;
use Illuminate\Database\Eloquent\Builder;

/**
 * Delete run history beyond the retention window, while always keeping the
 * most recent N runs per scheduler so a rarely-run task never loses all
 * evidence that it ever ran.
 */
class PruneRuns implements Execute
{
    protected int $pruned = 0;

    public function __construct(
        protected ?int $days = null,
        protected ?int $keepLast = null,
        protected bool $dryRun = false,
    ) {}

    public function execute(): self
    {
        $days = $this->days ?? (int) config('scheduler-manager.retention_days', 30);
        $keepLast = $this->keepLast ?? (int) config('scheduler-manager.retention_keep_last', 10);

        $protected = $this->protectedRunIds($keepLast);

        $query = SchedulerRun::query()
            ->where('started_at', '<', now()->subDays($days))
            ->when($protected !== [], fn (Builder $q) => $q->whereNotIn('id', $protected));

        if ($this->dryRun) {
            $this->pruned = $query->count();

            return $this;
        }

        // Select-then-delete in batches: SQLite has no DELETE ... LIMIT, and
        // chunking keeps a large backlog from locking the table or exhausting
        // memory.
        $this->pruned = 0;

        do {
            $ids = $query->clone()->limit(1000)->pluck('id')->all();

            if ($ids === []) {
                break;
            }

            $this->pruned += SchedulerRun::query()->whereIn('id', $ids)->delete();
        } while (count($ids) === 1000);

        return $this;
    }

    /**
     * @return array<int, int>
     */
    protected function protectedRunIds(int $keepLast): array
    {
        if ($keepLast < 1) {
            return [];
        }

        return SchedulerRun::query()
            ->select('id', 'scheduler_id', 'started_at')
            ->get()
            ->groupBy('scheduler_id')
            ->flatMap(fn ($runs) => $runs->sortByDesc('started_at')->take($keepLast)->pluck('id'))
            ->all();
    }

    public function pruned(): int
    {
        return $this->pruned;
    }
}
