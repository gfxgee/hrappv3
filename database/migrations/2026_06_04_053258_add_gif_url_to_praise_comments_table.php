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
        Schema::table('praise_comments', function (Blueprint $table) {
            $table->string('gif_url')->nullable()->after('comment');
            // A comment may now be GIF-only, so the text is no longer required.
            $table->text('comment')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('praise_comments', function (Blueprint $table) {
            $table->dropColumn('gif_url');
            $table->text('comment')->nullable(false)->change();
        });
    }
};
