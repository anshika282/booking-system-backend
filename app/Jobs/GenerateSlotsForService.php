<?php

namespace App\Jobs;

use App\Models\BookableService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSlotsForService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $bookableServiceId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Use a try-catch block for resilience. If this job fails, it won't crash the whole queue worker.
        try {
            $service = BookableService::find($this->bookableServiceId);

            // Safety check: If service was deleted between dispatch and execution.
            if (!$service) {
                Log::warning("Slot Generation: Service with ID {$this->bookableServiceId} not found. Skipping.");
                return;
            }

            // --- 1. Determine the Date Range for Generation ---
            
            // Find the last slot that was already generated for this service.
            $lastSlot = DB::table('availability_slots')
                ->where('bookable_service_id', $service->id)
                ->max('start_time');

            // The generation starts from the day after the last slot, or from today if no slots exist.
            $startDate = $lastSlot ? Carbon::parse($lastSlot)->addDay()->startOfDay() : Carbon::today();
            
            // The generation ends at the service's booking horizon.
            $endDate = Carbon::today()->addDays($service->booking_window_max_days)->endOfDay();
            
            // If the start date is already past the end date, we are caught up. Nothing to do.
            if ($startDate->isAfter($endDate)) {
                Log::info("Slot Generation: Service {$service->name} (ID: {$service->id}) is already up to date. Skipping.");
                return;
            }

            // --- 2. Prepare for Generation ---
            $slotTemplate = $service->operatingHours()->get()->groupBy('day_of_week');
            if ($slotTemplate->isEmpty()) {
                Log::info("Slot Generation: Service {$service->name} (ID: {$service->id}) has no operating hours. Skipping.");
                return;
            }

            $slotsToInsert = [];
            $duration = $service->duration_minutes;
            $capacity = $service->default_capacity ?? 10;
            $currentDate = $startDate->copy();

            // --- 3. The Generation Loop ---
            while ($currentDate->lte($endDate)) {
                $dayOfWeek = $currentDate->dayOfWeek;

                if ($slotTemplate->has($dayOfWeek)) {
                    foreach ($slotTemplate->get($dayOfWeek) as $templateSlot) {
                        $startTime = Carbon::parse($currentDate->toDateString() . ' ' . $templateSlot->open_time);
                        
                        $slotsToInsert[] = [
                            'tenant_id' => $service->tenant_id,
                            'bookable_service_id' => $service->id,
                            'start_time' => $startTime->toDateTimeString(),
                            'end_time' => $startTime->copy()->addMinutes($duration)->toDateTimeString(),
                            'capacity' => $capacity,
                            'booked_count' => 0,
                            'status' => 'open',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                $currentDate->addDay();
            }

            // --- 4. Bulk Insert ---
            if (!empty($slotsToInsert)) {
                // Insert in chunks to avoid hitting query size limits on large generation ranges.
                foreach (array_chunk($slotsToInsert, 500) as $chunk) {
                    DB::table('availability_slots')->insert($chunk);
                }
                Log::info("Slot Generation: Successfully generated " . count($slotsToInsert) . " slots for service {$service->name} (ID: {$service->id}).");
            }

        } catch (\Exception $e) {
            Log::error("Slot Generation Failed for Service ID {$this->bookableServiceId}: " . $e->getMessage());
            // Optionally, re-throw the exception to make the job fail and be retried by the queue worker.
            // $this->fail($e);
        }
    }
}