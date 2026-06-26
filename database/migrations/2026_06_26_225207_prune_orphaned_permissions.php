<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Remove permissions left over from a since-removed permission generator
     * (e.g. Filament Shield). Authorization here is role-based, so these rows
     * were never enforced. Idempotent — safe to run on any environment.
     */
    public function up(): void
    {
        Artisan::call('permissions:prune-orphaned');
    }

    /**
     * Irreversible: the orphaned permissions carried no behaviour, so there is
     * nothing meaningful to restore.
     */
    public function down(): void
    {
        // no-op
    }
};
