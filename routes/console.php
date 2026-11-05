<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hosting:refresh-monitoring')
    ->everyThreeHours()
    ->withoutOverlapping();

Schedule::command('hosting:daily-report')
    ->dailyAt('20:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('hosting:retry-telegram')
    ->everyFiveMinutes()
    ->withoutOverlapping();
