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
        Schema::table('job_seeker_certificates', function (Blueprint $table) {
            $table->string("issuer")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_seeker_certificates', function (Blueprint $table) {
            $table->dropColumn("issuer");
        });
    }
};
