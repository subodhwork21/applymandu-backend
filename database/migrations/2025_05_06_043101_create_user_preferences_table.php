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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Privacy Preferences
            $table->boolean('visible_to_employers')->default(false);
            $table->boolean('appear_in_search_results')->default(false);
            $table->boolean('show_contact_info')->default(false);
            $table->boolean('show_online_status')->default(false);
            $table->boolean('allow_personalized_recommendations')->default(false);
            
            // Notification Preferences
            $table->boolean('email_job_matches')->default(false);
            $table->boolean('sms_application_updates')->default(false);
            $table->boolean('subscribe_to_newsletter')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
