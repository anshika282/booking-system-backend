<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
             // This flag identifies the special "Guest" placeholder record.
            $table->boolean('is_placeholder')->after('id')->default(false)->index();
        });
         // Seed the single, system-wide placeholder customer record.
        // This ensures it exists for all guest checkouts.
        DB::table('customers')->insert([
            'is_placeholder' => true,
            'name' => 'Guest Customer',
            'email' => 'guest@placeholder.system',
            'phone_number' => 'GUEST-ACCOUNT', // A unique, non-functional identifier
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
             $table->dropColumn('is_placeholder');
        });

        // You can optionally remove the guest user here if needed
        DB::table('customers')->where('phone_number', 'GUEST-ACCOUNT')->delete();
    }
};
