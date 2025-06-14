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
            $table->dropColumn("is_remote");
            $table->enum("location_type", ["on-site", "remote", "hybrid"])->default("on-site")->after("application_deadline");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('am_jobs', function (Blueprint $table) {
            $table->boolean("is_remote")->default(false)->after("status");
            $table->dropColumn("location_type");
        });
    }
};
