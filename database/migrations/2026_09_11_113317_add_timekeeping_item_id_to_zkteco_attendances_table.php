<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_attendances', function (Blueprint $table) {
            // The SharePoint Timekeeping list item this scan was mirrored into.
            // Set once the mirror succeeds, so the punch webhook can recognise
            // its own round-trip and skip re-logging the scan.
            $table->string('timekeeping_item_id')->nullable()->after('raw')->index();
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_attendances', function (Blueprint $table) {
            $table->dropIndex(['timekeeping_item_id']);
            $table->dropColumn('timekeeping_item_id');
        });
    }
};
