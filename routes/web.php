<?php

use CleaniqueCoders\LaravelSchedulerManager\Livewire\Dashboard;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerForm;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerIndex;
use CleaniqueCoders\LaravelSchedulerManager\Livewire\SchedulerRuns;
use Illuminate\Support\Facades\Route;

$names = config('scheduler-manager.ui.route_name_prefix', 'scheduler-manager.');

Route::get('/', SchedulerIndex::class)->name($names.'index');
Route::get('/dashboard', Dashboard::class)->name($names.'dashboard');
Route::get('/create', SchedulerForm::class)->name($names.'create');
Route::get('/{scheduler}/edit', SchedulerForm::class)->name($names.'edit');
Route::get('/{scheduler}/runs', SchedulerRuns::class)->name($names.'runs');
