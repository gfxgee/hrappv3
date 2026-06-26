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
            // A flexible list of {label, url} pairs so HR/employees can link any
            // number of digital ID copies (SSS, PhilHealth, Pag-IBIG, TIN, NBI…)
            // without a schema change per document type.
            $table->json('government_documents')->nullable()->after('hdmf_tin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('government_documents');
        });
    }
};
