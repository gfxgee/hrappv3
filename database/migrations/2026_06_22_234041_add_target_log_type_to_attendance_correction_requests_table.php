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
        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            // Which punch to fix on approval (clockin/clockout). Derived from the
            // type for missing punches; chosen by the employee for "wrong time".
            $table->string('target_log_type')->nullable()->after('correction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropColumn('target_log_type');
        });
    }
};
