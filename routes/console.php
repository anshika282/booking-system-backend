<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; 

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:prune-intents')->everyFiveMinutes();

// This is the "Foreman" job. It runs daily at a low-traffic time (e.g., 1:00 AM).
// Its only job is to find work and dispatch the "Worker" jobs to the queue.
Schedule::command('app:queue-slot-generation')->dailyAt('01:00');

// This is the "Cleanup Crew" job. It runs after the generation.
// It keeps the database lean by removing old, useless data.
Schedule::command('app:prune-slots --days=7')->dailyAt('02:00');

