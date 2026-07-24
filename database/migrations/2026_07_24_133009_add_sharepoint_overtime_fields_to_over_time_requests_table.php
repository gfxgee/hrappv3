<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the columns required to import the legacy SharePoint "Overtime"
     * list into the existing over_time_requests table. Email columns from the
     * list (Title, Manager, ApprovedBy) are resolved to users.id foreign keys
     * during import; the remaining columns preserve SharePoint provenance.
     */
    public function up(): void
    {
        Schema::table('over_time_requests', function (Blueprint $table) {
            // SharePoint list item ID — kept for idempotent re-imports.
            $table->unsignedBigInteger('sharepoint_id')->nullable()->unique()->after('id');

            // Manager (SharePoint "Manager" email) resolved to a user.
            $table->foreignId('manager_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();

            // Final approver (SharePoint "ApprovedBy" email) resolved to a user.
            $table->foreignId('approved_by')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();

            // Manager-stage decision (SharePoint "Status"); the existing `status`
            // column holds the final decision (SharePoint "OverallStatus").
            $table->string('manager_status')->nullable()->after('status');

            // SharePoint "Remarks" free text.
            $table->text('remarks')->nullable()->after('manager_status');

            // SharePoint "Attachments" count.
            $table->unsignedSmallInteger('attachments_count')->default(0)->after('remarks');

            // Original SharePoint audit timestamps (kept separate from Laravel's).
            $table->dateTime('sharepoint_created_at')->nullable()->after('attachments_count');
            $table->dateTime('sharepoint_modified_at')->nullable()->after('sharepoint_created_at');

            // SharePoint reasons exceed 255 chars; widen to hold the full text.
            $table->text('reason')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('over_time_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('approved_by');

            $table->dropColumn([
                'sharepoint_id',
                'manager_status',
                'remarks',
                'attachments_count',
                'sharepoint_created_at',
                'sharepoint_modified_at',
            ]);

            $table->string('reason')->change();
        });
    }
};
