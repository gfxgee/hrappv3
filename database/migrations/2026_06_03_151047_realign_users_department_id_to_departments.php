<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Realign the legacy 0-based department index (Design=0 ... Office
        // Management=3) onto the departments table ids (Design=1 ... =4).
        DB::table('users')
            ->whereNotNull('department_id')
            ->whereBetween('department_id', [0, 3])
            ->update(['department_id' => DB::raw('department_id + 1')]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('department_id')->nullable()->change();
        });

        DB::table('users')
            ->whereNotNull('department_id')
            ->whereBetween('department_id', [1, 4])
            ->update(['department_id' => DB::raw('department_id - 1')]);
    }
};
