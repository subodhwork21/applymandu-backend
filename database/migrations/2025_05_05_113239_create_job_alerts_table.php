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
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id");
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('alert_title');
            $table->enum('job_category', ['IT', 'Finance', 'Marketing', 'Sales', 'Engineering', 'Design', 'Human Resources', 'Customer Service', 'Operations', 'Legal', 'Research', 'Education', 'Healthcare', 'Consulting', 'Real Estate', 'Hospitality', 'Transportation', 'Media', 'Non-Profit']);
            $table->enum('experience_level', ['Entry Level', 'Mid Level', 'Senior Level', 'lead', 'executive']);
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('location')->nullable();
            $table->text('keywords')->nullable(); 
            $table->enum('alert_frequency', ['daily', 'weekly', 'monthly']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_alerts');
    }
};
