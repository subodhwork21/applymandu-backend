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
        Schema::create('schedule_application_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained("applications")->onDelete('cascade');
            $table->foreignId('interview_type_id')->constrained("application_interview_types")->onDelete('cascade');
            $table->date('date');
            $table->time('time');
            $table->enum('mode', ['in-person', 'video-call', 'phone-call']);
            $table->foreignId('interviewer_id')->constrained("application_interviewers")->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_application_interviews');
    }
};
