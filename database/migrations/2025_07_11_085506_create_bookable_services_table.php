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
        Schema::create('bookable_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->enum('slot_consumption_model', ['per_ticket', 'per_booking'])->default('per_ticket');
            $table->enum('slot_selection_mode', ['list_all', 'find_next'])->default('list_all');
            $table->unsignedInteger('search_buffer_minutes')->default(0);
            $table->unsignedInteger('booking_window_min_days')->default(0);
            $table->unsignedInteger('booking_window_max_days')->default(90);
            $table->morphs('serviceable'); // Creates serviceable_id and serviceable_type
            $table->enum('status', ['active', 'draft', 'archived'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookable_services');
    }
};
