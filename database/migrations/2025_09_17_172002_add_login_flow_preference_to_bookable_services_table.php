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
            // The ENUM type ensures data integrity by only allowing these three specific values.
            // It is placed after the 'status' column for logical grouping of service settings.
            // The default value is set to 'guest_only' as requested for the MVP.
            $table->enum('login_flow_preference', ['login_first', 'login_at_checkout', 'guest_only'])
                  ->after('status')
                  ->default('guest_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookable_services', function (Blueprint $table) {
            $table->dropColumn('login_flow_preference');
        });
    }
};
