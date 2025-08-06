<?php

namespace App\Console\Commands;

use App\Models\BookingIntent;
use Illuminate\Console\Command;

class PruneExpiredIntents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * The signature defines the command name that you will use to run it
     * from the terminal (e.g., `php artisan app:prune-intents`).
     *
     * @var string
     */
    protected $signature = 'app:prune-intents';

    /**
     * The console command description.
     * This description will appear when you run `php artisan list`.
     *
     * @var string
     */
    protected $description = 'Finds active booking intents that have passed their expiration time and marks them as expired.';

    /**
     * Execute the console command.
     *
     * This is the main method that will be executed when the scheduler runs the command.
     * All of the business logic for this task lives here.
     */
    public function handle()
    {
        // Log a message to the console. This is a best practice for scheduled tasks,
        // as it allows you to see in your logs that the job started correctly.
        $this->info('Searching for expired booking intents to prune...');

        // --- Core Logic ---
        
        // We use the Eloquent query builder to construct our query. This is a clean
        // and database-agnostic way to write queries.
        $query = BookingIntent::query()
            // Condition 1: We only care about intents that are currently 'active'.
            // This prevents us from re-processing intents that are already 'completed' or 'expired'.
            // This is a crucial `WHERE` clause for performance on large tables.
            ->where('status', 'active')

            // Condition 2: The expiration timestamp is in the past.
            // `now()` gets the current time. This is the core of the "expiration" logic.
            ->where('expires_at', '<', now());
            
        // --- Execution and Feedback ---

        // Instead of fetching the models into memory (`->get()`) and then looping,
        // which would be very inefficient for thousands of records, we perform a
        // direct, single-database `update` query.
        // The `$count` variable will hold the number of rows that were affected.
        $count = $query->update(['status' => 'expired']);

        if ($count > 0) {
            // Provide clear feedback in the console/logs about what happened.
            $this->info("Successfully marked {$count} booking intent(s) as expired.");
        } else {
            // It's also good practice to log when the command ran but did nothing.
            $this.info('No expired intents found to prune.');
        }
        
        // Artisan commands should return 0 on success. This is a standard convention
        // for command-line applications.
        return self::SUCCESS;
    }
}