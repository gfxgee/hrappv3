<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the short-lived per-user sidebar preference (the sidebar is now
     * compact for everyone via CSS). Guarded so it's a no-op on databases
     * where the column was never created.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'sidebar_compact')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sidebar_compact');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'sidebar_compact')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('sidebar_compact')->default(false)->after('active');
            });
        }
    }
};
