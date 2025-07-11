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
        Schema::create('booking_addon', function (Blueprint $table) {
            // We DO NOT use $table->id() for a pivot table with a composite key.
            
            // Define the foreign keys first.
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('addon_id')->constrained()->onDelete('cascade');

            // Define the additional columns for the pivot table.
            $table->unsignedInteger('quantity');
            $table->decimal('price_at_booking', 10, 2);

            // Define the composite primary key.
            // This ensures that the same addon cannot be added to the same booking more than once.
            $table->primary(['booking_id', 'addon_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_addon');
    }
};
