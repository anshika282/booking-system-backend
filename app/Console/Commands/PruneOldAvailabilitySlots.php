<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PruneOldAvailabilitySlots extends Command
{
    protected $signature = 'app:prune-slots {--days=7 : Delete unbooked slots older than this many days.}';
    protected $description = 'Prune old, unbooked availability slots from the database.';

    public function handle()
    {
        $this->info('Starting to prune old, unbooked availability slots...');

        $days = (int) $this->option('days');
        $cutoffDate = Carbon::today()->subDays($days)->startOfDay();
        
        $this->info("Pruning slots with a start time before: " . $cutoffDate->toDateTimeString());

        // Use a single, efficient bulk DELETE query.
        $deletedCount = DB::table('availability_slots')
            ->where('start_time', '<', $cutoffDate)
            ->where('booked_count', '=', 0) // IMPORTANT: Only delete unbooked slots
            ->delete();
        
        $this->info("Successfully pruned {$deletedCount} old slots.");
        
        return self::SUCCESS;
    }
}