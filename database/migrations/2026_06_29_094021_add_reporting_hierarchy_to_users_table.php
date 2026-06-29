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
            // Who this employee reports to (org chart). The CEO has no manager.
            $table->foreignId('manager_id')->nullable()->after('department_id')
                ->constrained('users')->nullOnDelete();
            // Marks the top of the org chart (e.g. CEO), to distinguish the
            // root from employees who simply have no manager assigned yet.
            $table->boolean('is_org_head')->default(false)->after('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn('is_org_head');
        });
    }
};
