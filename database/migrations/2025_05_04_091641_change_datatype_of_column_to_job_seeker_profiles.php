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
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn("industry");
            $table->enum('industry', ['IT', 'Finance', 'Marketing', 'Sales', 'Engineering', 'Education', 'Healthcare', 'Hospitality', 'Manufacturing', 'Construction', 'Transportation', 'Retail', 'Customer Service', 'Legal', 'Arts', 'Media', 'Non-Profit', 'Real Estate', 'Insurance', 'Consulting', 'Logistics', 'Wholesale', 'Energy', 'Agriculture', 'Mining', 'Defense', 'Government', 'Other'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn("industry");
        });
    }
};
