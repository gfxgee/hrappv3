<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_devices', function (Blueprint $table) {
            $table->id();
            $table->string('sn')->unique();
            $table->string('name')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });

        Schema::create('zkteco_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('sn');
            // The device's enrolled user id, matched to users.bio_metric_id.
            $table->integer('bio_metric_id');
            $table->dateTime('scanned_at');
            $table->unsignedTinyInteger('status1')->nullable();
            $table->unsignedTinyInteger('status2')->nullable();
            $table->unsignedTinyInteger('status3')->nullable();
            $table->unsignedTinyInteger('status4')->nullable();
            $table->unsignedTinyInteger('status5')->nullable();
            $table->text('raw')->nullable();
            $table->timestamps();

            $table->index(['sn', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_attendances');
        Schema::dropIfExists('zkteco_devices');
    }
};
