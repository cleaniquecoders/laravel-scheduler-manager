<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Scheduler extends Model
{
    use HasFactory;

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
        'payload' => 'array',
        'enabled' => 'boolean',
        'prevent_overlap' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Scheduler $scheduler) {
            if (empty($scheduler->uuid)) {
                $scheduler->uuid = (string) Str::orderedUuid();
            }
        });
    }
}
