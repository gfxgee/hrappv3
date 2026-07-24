<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the columns required to import the legacy SharePoint "Timeoff" list
     * into the existing leave_requests table. Email columns from the list
     * (Email, Manager, ApprovedBy) are resolved to users.id foreign keys during
     * import; the remaining columns preserve SharePoint provenance.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // SharePoint list item ID — kept for idempotent re-imports.
            $table->unsignedBigInteger('sharepoint_id')->nullable()->unique()->after('id');

            // Manager (SharePoint "Manager" email) resolved to a user.
            $table->foreignId('manager_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();

            // Final approver (SharePoint "ApprovedBy" email) resolved to a user.
            $table->foreignId('approved_by')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();

            // Manager-stage decision (SharePoint "Status"); the existing `status`
            // column holds the final decision (SharePoint "OverallStatus").
            $table->string('manager_status')->nullable()->after('status');

            // SharePoint "HR" approval flag.
            $table->boolean('hr_approved')->nullable()->after('manager_status');

            // Original SharePoint creation time (the list has no "Modified").
            $table->dateTime('sharepoint_created_at')->nullable()->after('remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('approved_by');

            $table->dropColumn([
                'sharepoint_id',
                'manager_status',
                'hr_approved',
                'sharepoint_created_at',
            ]);
        });
    }
};
