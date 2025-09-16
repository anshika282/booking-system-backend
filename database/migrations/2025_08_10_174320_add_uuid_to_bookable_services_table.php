<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\BookableService;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookable_services', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->nullable()->unique();
        });

        // Backfill UUIDs for any existing services
        DB::table('bookable_services')->whereNull('uuid')->cursor()->each(function ($service) {
            DB::table('bookable_services')
                ->where('id', $service->id)
                ->update(['uuid' => (string) Str::uuid()]);
        });
    }
    // ... down method
};