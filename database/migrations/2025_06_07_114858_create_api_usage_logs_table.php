<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->string('endpoint');
            $table->string('method');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->integer('response_status');
            $table->integer('response_time_ms');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('created_at');

            $table->index(['api_key_id', 'created_at']);
            $table->index(['employer_id', 'created_at']);
            $table->index(['endpoint', 'method']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
