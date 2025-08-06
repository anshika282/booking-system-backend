<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // The unique code the user will enter. Must be unique per tenant.
            $table->string('code');
            
            $table->enum('discount_type', ['percentage', 'fixed'])->default('fixed');
            $table->decimal('discount_value', 10, 2);

            // Optional: The minimum booking subtotal required to use this coupon.
            $table->decimal('min_amount', 10, 2)->nullable();
            
            // Optional: The total number of times this coupon can be used across all customers.
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            // Optional: The date range when this coupon is valid.
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();

            // Optional: A JSON column to store an array of service IDs this coupon applies to.
            // If null, it applies to all services for the tenant.
            $table->json('applicable_services')->nullable();
            
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};