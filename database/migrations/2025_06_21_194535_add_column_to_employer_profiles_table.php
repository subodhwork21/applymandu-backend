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
        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->boolean('receive_analytics_reports')->default(true);
            $table->enum('report_frequency', ['weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->json('report_preferences')->nullable(); // Store custom report preferences
            $table->timestamp('last_report_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'receive_analytics_reports',
                'report_frequency',
                'report_preferences',
                'last_report_sent_at'
            ]);
        });
    }
};
