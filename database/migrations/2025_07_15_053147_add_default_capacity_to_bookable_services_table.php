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
        Schema::table('bookable_services', function (Blueprint $table) {
            // Default capacity for ticketed events. Null for appointments (capacity is always 1).
        $table->unsignedInteger('default_capacity')->nullable()->after('duration_minutes');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookable_services', function (Blueprint $table) {
            //
        });
    }
};
