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
        // The ordered roster of developers in the on-call ("late dev") rotation.
        Schema::create('on_call_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // One row per week recording who is on-call. week_start is the Monday of
        // that week. is_override marks a manual HR assignment the auto-job leaves alone.
        Schema::create('on_call_assignments', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_override')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('on_call_assignments');
        Schema::dropIfExists('on_call_members');
    }
};
