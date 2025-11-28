<?php

namespace App\Models;

use App\Models\User;
use App\Models\Location;
use Illuminate\Support\Str;
use App\Models\BookableService;
use App\Models\TenantPaymentConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'domain', 'status'];

    // Relationship: One tenant can have many users
    public function users()
    {
        return $this->hasMany(User::class);
    }
    // Relationship: One tenant can have many services
    public function services(): HasMany { 
        return $this->hasMany(BookableService::class); 
    }
    // Relationship: One tenant can have many locations
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Relationship: One tenant can have many payment configurations.
     * This is the relationship linking the tenant to their payment options.
     */
    public function paymentConfigs(): HasMany
    {
        // Eloquent correctly assumes the foreign key is 'tenant_id' on the related model.
        return $this->hasMany(TenantPaymentConfig::class);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // This 'creating' event listener runs just before a new Tenant record is saved.
        static::creating(function (Tenant $tenant) {
            // If a UUID hasn't already been set, generate a new one.
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }
}
