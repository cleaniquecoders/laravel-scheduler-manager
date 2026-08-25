<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Models;

use CleaniqueCoders\LaravelSchedulerManager\Enums\RunStatus;
use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $scheduler_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property RunStatus $status
 * @property int|null $exit_code
 * @property string|null $output
 * @property string|null $exception
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scheduler $scheduler
 */
class SchedulerRun extends Model
{
    use HasFactory, InteractsWithUuid;

    protected $table = 'scheduler_runs';

    protected $fillable = [
        'uuid',
        'scheduler_id',
        'started_at',
        'finished_at',
        'status',
        'exit_code',
        'output',
        'exception',
    ];

    protected $casts = [
        'status' => RunStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(Scheduler::class);
    }

    /**
     * Run duration in seconds, or null while still in flight.
     */
    public function duration(): ?float
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return abs($this->started_at->diffInMilliseconds($this->finished_at)) / 1000;
    }

    public function scopeStatus($query, RunStatus $status)
    {
        return $query->where('status', $status);
    }
}
