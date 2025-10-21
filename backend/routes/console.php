<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('queue:dev-listen', function (): int {
    $defaultQueue = config('queue.default');
    $disabledDrivers = ['sync', 'null'];

    if (in_array($defaultQueue, $disabledDrivers, true)) {
        $this->warn(sprintf(
            'queue:listen skipped — the "%s" driver runs jobs synchronously.',
            $defaultQueue
        ));

        while (true) {
            sleep(60);
        }
    }

    return $this->call('queue:listen', ['--tries' => 1]);
})->purpose('Run the queue listener in development when the queue driver supports it.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->dailyAt('02:00');
