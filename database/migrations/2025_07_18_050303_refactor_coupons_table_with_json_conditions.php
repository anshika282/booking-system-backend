<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // First, ensure the columns we want to drop exist before trying to drop them.
            if (Schema::hasColumn('coupons', 'min_amount')) {
                $table->dropColumn('min_amount');
            }
            if (Schema::hasColumn('coupons', 'valid_from')) {
                $table->dropColumn('valid_from');
            }
            if (Schema::hasColumn('coupons', 'valid_to')) {
                $table->dropColumn('valid_to');
            }
            if (Schema::hasColumn('coupons', 'applicable_services')) {
                $table->dropColumn('applicable_services');
            }

            // Change discount_type from ENUM to a more flexible string.
            $table->string('discount_type')->default('fixed')->change();
            // Modify the column to allow NULL values.
            // A BOGO coupon, for example, has no single discount_value.
            $table->decimal('discount_value', 10, 2)->nullable()->change();

            // Add the new, structured JSON columns
            $table->json('conditions')->nullable()->after('discount_value');
            $table->json('effects')->nullable()->after('conditions');

        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'effects']);
            // Add old columns back for rollback
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('applicable_services')->nullable();
            // Revert the column back to NOT NULL.
            // Note: This might fail if there are existing rows with NULL in this column.
            $table->decimal('discount_value', 10, 2)->nullable(false)->change();

            $table->enum('discount_type', ['percentage', 'fixed'])->default('fixed')->change();
        });
    }
};