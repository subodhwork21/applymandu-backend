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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascade("onDelete");
            $table->foreignId('job_id')->constrained("am_jobs")->cascade('onDelete');
            $table->integer('year_of_experience')->nullable();
            $table->integer('expected_salary')->nullable();
            $table->integer('notice_period')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('cover_letter')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
