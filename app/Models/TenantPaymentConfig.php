<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPaymentConfig extends Model
{
    protected $fillable = ['tenant_id', 'gateway_type', 'is_default', 'credentials'];

    protected $casts = [
        'is_default' => 'boolean',
        'credentials' => 'array', // Will automatically serialize/deserialize the JSONB column
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}