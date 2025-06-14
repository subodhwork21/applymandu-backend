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
        Schema::table('am_jobs', function (Blueprint $table) {
            $table->enum('department', ["engineering", "product", "design", "marketing", "sales", "finance", "hr", "legal", "operations", "customer support", "content writing", "data entry", "other"])->nullable()->after("status");
            $table->date("application_deadline")->nullable()->after("department");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('am_jobs', function (Blueprint $table) {
            $table->dropColumn('department');
            $table->dropColumn('application_deadline');
        });
    }
};
