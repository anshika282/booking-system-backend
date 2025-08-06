<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // UUIDs are excellent public identifiers. They are unique and not sequential.
            // We'll place it after the 'id' and add a database index for fast lookups.
            $table->uuid('uuid')->after('id')->unique()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};