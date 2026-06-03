<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rebuild the (empty) praise tables with an improved schema:
     * - Praise no longer requires an award cycle (session optional) → kudos wall.
     * - Sender / badge / session preserve history on delete (nullOnDelete).
     * - Reactions support multiple emoji types per user.
     * - Badges carry points (leaderboards) and an active flag.
     */
    public function up(): void
    {
        Schema::dropIfExists('praise_comments');
        Schema::dropIfExists('praise_reactions');
        Schema::dropIfExists('praises');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('praise_sessions');

        Schema::create('praise_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('color')->default('primary');
            $table->unsignedInteger('points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('praises', function (Blueprint $table) {
            $table->id();
            // Sender — kept on delete so recognition history survives a departure.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Recipient — the praise is about them, so it goes if they're removed.
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            // Optional award cycle and badge.
            $table->foreignId('praise_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('badge_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->timestamps();

            $table->index('recipient_id');
        });

        Schema::create('praise_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('❤️');
            $table->timestamps();

            // One reaction of each type per user per praise (toggle behaviour).
            $table->unique(['praise_id', 'user_id', 'type']);
        });

        Schema::create('praise_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });
    }

    /**
     * Restore the original schema.
     */
    public function down(): void
    {
        Schema::dropIfExists('praise_comments');
        Schema::dropIfExists('praise_reactions');
        Schema::dropIfExists('praises');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('praise_sessions');

        Schema::create('praise_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('color')->default('primary');
            $table->timestamps();
        });

        Schema::create('praises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('praise_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('praise_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['praise_id', 'user_id']);
        });

        Schema::create('praise_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });
    }
};
