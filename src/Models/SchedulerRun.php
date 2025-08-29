<?php

namespace CleaniqueCoders\LaravelSchedulerManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SchedulerRun extends Model
{
    use HasFactory;

    protected $table = 'scheduler_runs';

    protected $fillable = [
        'scheduler_id',
        'started_at',
        'finished_at',
        'status',
        'exit_code',
        'output',
        'exception',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (SchedulerRun $run) {
            if (empty($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }
}
