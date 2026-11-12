<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Payment Expiration
|--------------------------------------------------------------------------
|
| Automatically expire pending payments that have exceeded
| the configured payment timeout and release reserved inventory.
|
*/

Schedule::command('payments:expire --minutes=30')
    ->everyMinute()
    ->withoutOverlapping();

// Only process research explicitly requested by a customer.
// Do not search the entire catalog. No daily research quota.
Schedule::command('assistant:research-pending')
    ->everyMinute()
    ->withoutOverlapping();