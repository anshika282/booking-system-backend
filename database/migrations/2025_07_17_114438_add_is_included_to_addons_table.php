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
        Schema::table('addons', function (Blueprint $table) {
            // This flag indicates if the add-on's cost is included in the ticket price.
            // We'll place it right after the name for logical grouping.
            $table->boolean('is_included_in_ticket')->after('name')->default(false);

            // An included add-on has no price, so the price column must now be nullable.
            $table->decimal('price', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('is_included_in_ticket');
            // Revert the price column back to its original state.
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });
    }
};
