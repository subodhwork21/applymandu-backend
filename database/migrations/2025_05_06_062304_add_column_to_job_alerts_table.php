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
        Schema::table('job_alerts', function (Blueprint $table) {
            $table->enum('status', ['paused', 'active', 'expired'])->default('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_alerts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
