<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Add the foreign key to link a coupon directly to a service.
            // This column CANNOT be null in a service-level model.
            // We place it after tenant_id for logical grouping.
            $table->foreignId('bookable_service_id')
                  ->after('tenant_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            // The unique constraint on 'code' must now be per-service, not per-tenant.
            $table->unique(['bookable_service_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['bookable_service_id']);
            $table->dropUnique(['bookable_service_id', 'code']);
            $table->dropColumn('bookable_service_id');

            // Restore the old unique key
        });
    }
};