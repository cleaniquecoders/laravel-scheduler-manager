<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Models;

use Carbon\CarbonInterface;
use CleaniqueCoders\LaravelSchedulerManager\Enums\SchedulerType;
use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property SchedulerType $type
 * @property string $identifier
 * @property array<string, mixed>|null $payload
 * @property string $cron
 * @property string|null $timezone
 * @property bool $enabled
 * @property bool $prevent_overlap
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SchedulerRun> $runs
 * @property-read SchedulerRun|null $latestRun
 */
class Scheduler extends Model
{
    use HasFactory, InteractsWithUuid;

    protected $table = 'schedulers';

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'identifier',
        'payload',
        'cron',
        'timezone',
        'enabled',
        'prevent_overlap',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'type' => SchedulerType::class,
        'payload' => 'array',
        'enabled' => 'boolean',
        'prevent_overlap' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * All recorded runs for this scheduler.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(SchedulerRun::class);
    }

    /**
     * The most recent run, for listing without an N+1.
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(SchedulerRun::class)->latestOfMany('started_at');
    }

    protected static function booted(): void
    {
        static::saving(function (Scheduler $scheduler) {
            if ($scheduler->isDirty(['cron', 'timezone']) || is_null($scheduler->next_run_at)) {
                $scheduler->next_run_at = $scheduler->calculateNextRunAt();
            }
        });
    }

    /**
     * When this scheduler is next due, or null if the cron cannot be parsed.
     *
     * Stored in the application timezone: Eloquent serialises a Carbon using
     * that object's own timezone, so a scheduler-timezone instance would be
     * written as local wall-clock and read back as application time.
     */
    public function calculateNextRunAt(?CarbonInterface $from = null): ?Carbon
    {
        $timezone = $this->resolveTimezone();

        try {
            $next = (new CronExpression($this->cron))
                ->getNextRunDate($from ?? Carbon::now($timezone));
        } catch (\Throwable) {
            return null;
        }

        return Carbon::instance($next)->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Whether the stored cron expression can be parsed.
     */
    public function isCronValid(): bool
    {
        return CronExpression::isValidExpression((string) $this->cron);
    }

    /**
     * Whether this scheduler is due at the given moment.
     */
    public function isDue(?CarbonInterface $at = null): bool
    {
        try {
            return (new CronExpression($this->cron))
                ->isDue($at ?? Carbon::now($this->resolveTimezone()));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The timezone this scheduler's cron should be evaluated in.
     */
    public function resolveTimezone(): string
    {
        return $this->timezone ?: config('app.timezone', 'UTC');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
