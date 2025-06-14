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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('type', ['interview', 'meeting', 'deadline', 'other'])->default('meeting');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->string('location')->nullable();
            $table->json('attendees')->nullable(); // Store email addresses as JSON array
            $table->foreignId('job_id')->nullable()->constrained('am_jobs')->onDelete('set null');
            $table->foreignId('application_id')->nullable()->constrained('applications')->onDelete('set null');
            $table->string('candidate_name')->nullable();
            $table->string('candidate_email')->nullable();
            $table->text('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone')->default('UTC');
            $table->json('reminder_settings')->nullable(); // Store reminder preferences
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['employer_id', 'start_time']);
            $table->index(['employer_id', 'type']);
            $table->index(['employer_id', 'status']);
            $table->index(['job_id']);
            $table->index(['application_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
