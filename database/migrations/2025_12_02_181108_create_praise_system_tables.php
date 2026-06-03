<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Praise Sessions (The "Cycle" - HR Controlled)
        Schema::create('praise_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "January 2026 Awards"
            $table->boolean('is_active')->default(false); // Only one should be active at a time
            $table->timestamps();
        });

        // 2. Badges (The "Awards" - HR Controlled)
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('label'); // e.g., "Team Player"
            $table->string('icon')->nullable(); // e.g., "heroicon-o-star" or "🏆"
            $table->string('color')->default('primary'); // success, warning, danger, etc.
            $table->timestamps();
        });

        // 3. Praises (The Nomination Card)
        Schema::create('praises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The Sender
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete(); // The Receiver (Single Person)
            $table->foreignId('praise_session_id')->constrained()->cascadeOnDelete(); // Belongs to specific cycle
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete(); // The Award type
            $table->text('message'); // The "Why"
            $table->timestamps();
        });

        // 4. Praise Reactions (The "Heart" Votes)
        Schema::create('praise_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete(); // Links to the Card
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The Voter
            $table->timestamps();

            // Prevent a user from hearting the same card twice
            $table->unique(['praise_id', 'user_id']);
        });

        // 5. Praise Comments (The Hype)
        Schema::create('praise_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('praise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The Commenter
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praise_comments');
        Schema::dropIfExists('praise_reactions');
        Schema::dropIfExists('praises');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('praise_sessions');
    }
};