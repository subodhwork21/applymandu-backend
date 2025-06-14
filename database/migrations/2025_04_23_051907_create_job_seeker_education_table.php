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
        Schema::create('job_seeker_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained("users")->onDelete('cascade');
            $table->string('degree');
            $table->string('subject_major');
            $table->string('institution');
            $table->string('university_board');
            $table->string('grading_type')->nullable();
            $table->date('joined_year');
            $table->date('passed_year')->nullable();
            $table->boolean('currently_studying')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seeker_education');
    }
};
