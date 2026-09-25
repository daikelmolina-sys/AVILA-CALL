<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled automated purge: retain chat messages and moderation logs for 30 days
Schedule::command('avila:purge-chat --days=30')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/chat_purge.log'));

// Once a class has been ended for one hour, remove its chat and moderation audit data.
Schedule::command('avila:purge-ended-class-data --hours=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ended_class_purge.log'));
