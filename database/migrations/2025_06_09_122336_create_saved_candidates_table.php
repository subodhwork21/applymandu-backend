<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSavedCandidatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('saved_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employer_id');
            $table->unsignedBigInteger('jobseeker_id');
            $table->text('notes')->nullable();
            $table->timestamp('saved_at');
            $table->timestamps();
            
            $table->foreign('employer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('jobseeker_id')->references('id')->on('users')->onDelete('cascade');
            
            // Ensure an employer can save a candidate only once
            $table->unique(['employer_id', 'jobseeker_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('saved_candidates');
    }
}
