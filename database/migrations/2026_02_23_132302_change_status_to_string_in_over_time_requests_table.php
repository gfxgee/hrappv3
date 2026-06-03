<?php

use App\Enum\AttendanceStatus;
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
        Schema::table('over_time_requests', function (Blueprint $table) {
            // Change the enum to a string
            $table->string('status')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('over_time_requests', function (Blueprint $table) {
            // Revert back to the specific enum if needed
            $table->enum('status', array_map(fn ($case) => $case->value, AttendanceStatus::cases()))
                  ->default(AttendanceStatus::FOR_APPROVAL->value)
                  ->change();
        });
    }
};
