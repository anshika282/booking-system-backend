<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Unique Index to ensure a tenant only has ONE config per type (e.g. one PhonePe config)
            $table->string('gateway_type')->default('phonepe');
            $table->unique(['tenant_id', 'gateway_type']);
            
            // Flag for whether this is the tenant's primary/default gateway
            $table->boolean('is_default')->default(false)->index(); 
            
            // The JSONB column to store all provider-specific credentials/settings securely
            $table->json('credentials');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_configs');
    }
};