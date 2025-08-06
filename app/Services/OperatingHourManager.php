<?php

namespace App\Services;

use App\Models\BookableService;
use App\Models\OperatingHour;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperatingHourManager
{
    /**
     * Replaces the weekly schedule and optionally generates availability slots for a given period.
     *
     * @param BookableService $service The service to update.
     * @param array $scheduleData The validated array of weekly schedule data.
     * @param int|null $generationDays The number of days from today to generate slots for.
     * @return array An associative array with the new hours and the count of generated slots.
     */
    public function replaceScheduleAndGenerateSlots(BookableService $service, array $scheduleData, ?int $generationDays): array
    {
        $newHours = DB::transaction(function () use ($service, $scheduleData) {
            // Step 1: Replace the operating hours template.
            $service->operatingHours()->delete();
            $createdHours = new Collection();

            foreach ($scheduleData as $dayData) {
                if ($dayData['is_enabled']) {
                    $createdHours->push(OperatingHour::create([
                        'bookable_service_id' => $service->id,
                        'tenant_id' => $service->tenant_id,
                        'day_of_week' => $dayData['day_of_week'],
                        'open_time' => $dayData['open_time'],
                        'close_time' => $dayData['close_time'],
                    ]));
                }
            }
            return $createdHours;
        });

        $generatedSlotsCount = 0;
        if ($generationDays > 0) {
            // Step 2: If requested, generate the concrete availability slots.
            $generatedSlotsCount = $this->generateSlotsForService($service, $newHours, $generationDays);
        }

        return [
            'hours' => $newHours,
            'slots_generated' => $generatedSlotsCount,
        ];
    }

    /**
     * Generates and bulk-inserts availability slots for a service.
     *
     * @param BookableService $service
     * @param Collection $operatingHours
     * @param int $daysToGenerate
     * @return int The number of slots created.
     */
    private function generateSlotsForService(BookableService $service, Collection $operatingHours, int $daysToGenerate): int
    {
        if ($operatingHours->isEmpty()) {
            return 0; // No schedule, no slots to generate.
        }

        $slotsToInsert = [];
        $today = Carbon::today();
        
        // Use the duration from the parent service.
        $duration = $service->duration_minutes;
        
        // Get buffer time if the service type is 'appointment'. Default to 0 otherwise.
        $buffer = $service->serviceable_type === 'App\\Models\\ServiceAppointment'
                  ? $service->serviceable->buffer_time_minutes ?? 0
                  : 0;

        // Assumption: Define a default capacity. This could be made configurable later.
        $capacity = $service->serviceable_type === 'App\\Models\\ServiceAppointment' 
              ? 1 
              : ($service->default_capacity ?? 100); // Use a sane default of 10 if not set.
        
        for ($i = 0; $i < $daysToGenerate; $i++) {
            $currentDate = $today->copy()->addDays($i);
            $dayOfWeek = $currentDate->dayOfWeek; // Carbon: 0=Sun, 1=Mon, ..., 6=Sat

            // Find the schedule for the current day of the week.
            $schedule = $operatingHours->firstWhere('day_of_week', $dayOfWeek);

            if ($schedule) {
                $openingTime = Carbon::parse($currentDate->toDateString() . ' ' . $schedule->open_time);
                $closingTime = Carbon::parse($currentDate->toDateString() . ' ' . $schedule->close_time);
                
                $slotTime = $openingTime->copy();

                while ($slotTime->copy()->addMinutes($duration) <= $closingTime) {
                    $slotsToInsert[] = [
                        'tenant_id' => $service->tenant_id,
                        'bookable_service_id' => $service->id,
                        'start_time' => $slotTime->toDateTimeString(),
                        'end_time' => $slotTime->copy()->addMinutes($duration)->toDateTimeString(),
                        'capacity' => $capacity,
                        'booked_count' => 0,
                        'status' => 'open',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Move to the start of the next slot
                    $slotTime->addMinutes($duration + $buffer);
                }
            }
        }

        if (!empty($slotsToInsert)) {
            // First, delete any potentially overlapping future slots to prevent duplicates.
            DB::table('availability_slots')
                ->where('bookable_service_id', $service->id)
                ->where('start_time', '>=', Carbon::today()->startOfDay())
                ->delete();

            // Use a single, highly performant bulk insert for all new slots.
            DB::table('availability_slots')->insert($slotsToInsert);
        }

        return count($slotsToInsert);
    }
}