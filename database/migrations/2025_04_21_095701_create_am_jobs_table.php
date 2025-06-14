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
        Schema::create('am_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->boolean('is_remote')->default(false);
            $table->string('employment_type'); 
            $table->enum('experience_level', ['Entry Level', 'Mid Level', 'Senior Level']);
            $table->string("location");
            $table->decimal('salary_min', 10, 2);
            $table->decimal('salary_max', 10, 2);
            $table->json('requirements');
            $table->json('responsibilities');
            $table->json('benefits');
            $table->date('posted_date');
            $table->unsignedInteger('employer_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('am_jobs');
    }
};
