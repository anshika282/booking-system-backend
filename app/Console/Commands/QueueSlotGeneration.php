<?php

namespace App\Console\Commands;

use App\Jobs\GenerateSlotsForService;
use App\Models\BookableService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueSlotGeneration extends Command
{
    protected $signature = 'app:queue-slot-generation';
    protected $description = 'Finds services that need new availability slots and dispatches jobs to generate them.';

    public function handle()
    {
        $this->info('Checking for services that need slot generation...');

        // Find all active services whose booking window is not yet full.
        // This is an efficient query that finds the last slot date for each service    
        // and compares it to their required horizon.
        $servicesToUpdate = DB::table('bookable_services as bs')
            ->select('bs.id', 'bs.booking_window_max_days', DB::raw('MAX(avs.start_time) as last_slot_date'))
            ->leftJoin('availability_slots as avs', 'bs.id', '=', 'avs.bookable_service_id')
            ->where('bs.status', 'active')
            ->whereNull('bs.deleted_at')
            ->groupBy('bs.id', 'bs.booking_window_max_days')
            ->havingRaw('last_slot_date IS NULL OR DATE(last_slot_date) < ?', [Carbon::today()->addDays(DB::raw('bs.booking_window_max_days'))])
            ->get();
        
        if ($servicesToUpdate->isEmpty()) {
            $this->info('All services are up to date. No jobs dispatched.');
            return self::SUCCESS;
        }

        $this->info("Found {$servicesToUpdate->count()} service(s) to update. Dispatching jobs...");

        foreach ($servicesToUpdate as $service) {
            GenerateSlotsForService::dispatch($service->id);
        }
        
        $this->info('All generation jobs have been dispatched to the queue.');
        return self::SUCCESS;
    }
}