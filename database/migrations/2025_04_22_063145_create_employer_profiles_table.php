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
        Schema::create('employer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); 
            $table->string("address")->nullable();
            $table->string("website")->nullable();
            $table->string("logo")->nullable();
            $table->text("description")->nullable();
            $table->string("industry")->nullable();
            $table->string("size")->nullable();
            $table->string("founded_year")->nullable();
            $table->boolean("two_fa")->default(0);
            $table->boolean("notification")->default(0);
            $table->foreign('user_Id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employer_profiles');
    }
};
