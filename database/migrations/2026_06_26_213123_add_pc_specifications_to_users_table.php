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
        Schema::table('users', function (Blueprint $table) {
            // A flexible list of {component, details} rows describing the
            // employee's workstation (Monitor, RAM, Mouse…). Stored as JSON so
            // any component type can be added without a schema change.
            $table->json('pc_specifications')->nullable()->after('government_documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pc_specifications');
        });
    }
};
