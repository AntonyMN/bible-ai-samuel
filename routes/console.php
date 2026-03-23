<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Morning Reflection: 6:00 AM - 8:30 AM (150m jitter)
Schedule::command('samuel:generate-blog --jitter=150')
    ->dailyAt('06:00')
    ->timezone('Africa/Nairobi');

// Evening Reflection: 8:30 PM - 10:00 PM (90m jitter)
Schedule::command('samuel:generate-blog --jitter=90 --evening')
    ->dailyAt('20:30')
    ->timezone('Africa/Nairobi');

Schedule::command('samuel:cleanup-audio')->daily();

Schedule::command('samuel:check-facebook-token')->daily();
