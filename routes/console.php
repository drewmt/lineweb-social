<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('message-reports:prune')->daily()->withoutOverlapping();
Schedule::command('notifications:dispatch-digests')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();
