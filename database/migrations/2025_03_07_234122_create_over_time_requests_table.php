<?php

use App\Enum\AttendanceStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('over_time_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reason');
            $table->dateTime('request_date');
            $table->float('hours');
            // $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('status', array_map(fn ($case) => $case->value, AttendanceStatus::cases()))
                  ->default(AttendanceStatus::FOR_APPROVAL->value);
            $table->dateTime('approved_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('over_time_requests');
    }
};
