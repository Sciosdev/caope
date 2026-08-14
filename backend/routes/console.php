<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --disable-notifications')
    ->name('backup-run')
    ->dailyAt('02:00')
    ->withoutOverlapping(120);

Schedule::command('backup:clean --disable-notifications')
    ->name('backup-clean')
    ->dailyAt('03:00')
    ->withoutOverlapping(120);

Schedule::command('backup:monitor')
    ->name('backup-monitor')
    ->dailyAt('04:00')
    ->withoutOverlapping(30);

Schedule::command('caope:deploy-pending')
    ->name('caope-deploy-pending')
    ->everyMinute()
    ->withoutOverlapping(30);

Schedule::call(function (): void {
    Cache::put('system.scheduler.last_run', now()->toIso8601String(), now()->addDay());
})
    ->name('system-scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();
