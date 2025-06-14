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
        Schema::create('job_seeker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('district');
            $table->string('municipality');
            $table->string('city_tole');
            $table->date('date_of_birth');
            $table->string('mobile')->nullable();
            $table->string('industry');
            $table->string('preferred_job_type');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->boolean('has_driving_license')->default(false);
            $table->boolean('has_vehicle')->default(false);
            $table->text('career_objectives')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seeker_profiles');
    }
};
