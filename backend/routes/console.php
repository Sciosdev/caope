<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->dailyAt('02:00');

Schedule::call(function (): void {
    Cache::put('system.scheduler.last_run', now()->toIso8601String(), now()->addDay());
})
    ->name('system-scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
