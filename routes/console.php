<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// تشغيل النسخ الاحتياطي كل 3 أيام الساعة 2 صباحاً
Schedule::command('backup:smart-run --reason=scheduled')->cron('0 2 */3 * *');

// Publish scheduled articles check every minute
Schedule::command('articles:publish-scheduled')->everyMinute();
