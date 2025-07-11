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
        Schema::create('booking_intents', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('bookable_service_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('intent_data')->nullable();
            $table->decimal('subtotal_amount', 10, 2)->nullable();
            $table->decimal('discounts_amount', 10, 2)->nullable();
            $table->decimal('surcharges_amount', 10, 2)->nullable();
            $table->decimal('addons_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('last_step_completed')->nullable();
            $table->enum('status', ['active', 'expired', 'completed'])->default('active');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_intents');
    }
};
