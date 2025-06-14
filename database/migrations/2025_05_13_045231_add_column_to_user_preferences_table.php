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
        Schema::table('user_preferences', function (Blueprint $table) {
           $table->boolean("immediate_availability")->nullable()->default(false)->after("subscribe_to_newsletter");
           $table->date("availability_date")->nullable()->after("immediate_availability");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn("immediate_availability");
            $table->dropColumn("availability_date");
        });
    }
};
