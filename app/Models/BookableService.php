<?php

namespace App\Models;

use App\Models\AddOn;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\TicketTier;
use App\Models\PricingRule;
use Illuminate\Support\Str;
use App\Models\OperatingHour;
use App\Models\AvailabilitySlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookableService extends Model
{
    use SoftDeletes;
     protected $fillable = [
        'tenant_id', 'name', 'description', 'duration_minutes', 'slot_consumption_model',
        'slot_selection_mode', 'search_buffer_minutes', 'booking_window_min_days','default_capacity',
        'login_flow_preference',
        'booking_window_max_days', 'status',
    ];

    public function tenant(): BelongsTo { 
        return $this->belongsTo(Tenant::class); 
    }

     /**
     * Get the parent serviceable model (ServiceTicketedEvent or ServiceAppointment).
     *
     * IMPORTANT: We add withTrashed() here so that we can still find the
     * relationship even when the parent service has been soft-deleted.
     */
    public function serviceable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function ticketTiers(): HasMany {
         return $this->hasMany(TicketTier::class); 
    }
    
    public function addons(): HasMany { 
        return $this->hasMany(AddOn::class); 
    }

    public function pricingRules(): HasMany { 
        return $this->hasMany(PricingRule::class); 
    }

    public function operatingHours(): HasMany { 
        return $this->hasMany(OperatingHour::class); 
    }

    public function availabilitySlots(): HasMany { 
        return $this->hasMany(AvailabilitySlot::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
     /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // When a service is being soft-deleted, we must also soft-delete its child.
        static::deleting(function (BookableService $service) {
            // This check ensures we don't try to delete a non-existent child.
            // The isForceDeleting() check prevents this from running on a permanent delete.
            if ($service->isForceDeleting()) {
                // On permanent delete, permanently delete the child too.
                $service->serviceable()->forceDelete();
            } else {
                // On soft delete, soft delete the child.
                $service->serviceable()->delete();
            }
        });

        // When a service is being restored, we must also restore its child.
        static::restoring(function (BookableService $service) {
            // We need to query the child record including trashed ones to find it.
            $service->serviceable()->withTrashed()->restore();
        });

         static::creating(function (BookableService $service) {
                if (empty($service->uuid)) {
                    $service->uuid = (string) Str::uuid();
                }
            });
    }
}

