<?php

use App\Models\PageView;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Return ticket holds from checkouts that were never paid.
Schedule::command('orders:release-expired')->everyMinute()->withoutOverlapping();

// Drop raw page views older than PageView::KEEP_DAYS.
Schedule::command('model:prune', ['--model' => [PageView::class]])->daily();
