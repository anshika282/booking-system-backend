<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds 'category' and 'is_stackable' columns to the pricing_rules table
     * to support a more advanced, phased pricing engine.
     */
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            
            // The 'category' column classifies the rule's purpose. This allows the pricing engine
            // to process rules in a specific order (e.g., base adjustments first, then discounts).
            // It's a string to allow for future extensibility with new categories.
            // We are adding it after the 'priority' column for logical grouping.
            // An index is added because we will frequently query/filter by this column.
            $table->string('category')->after('priority')->default('discount')->index();

            // The 'is_stackable' flag determines if a rule can be combined with other rules
            // of the same category. This is primarily for the 'discount' category to prevent
            // accidental over-discounting. For example, a "50% Off Black Friday" deal would be
            // non-stackable, while a "5% Member Discount" could be stackable.
            $table->boolean('is_stackable')->after('category')->default(false);

        });
    }

    /**
     * Reverse the migrations.
     *
     * This 'down' method ensures the migration can be safely rolled back
     * by removing the columns it added.
     */
    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_stackable']);
        });
    }
};