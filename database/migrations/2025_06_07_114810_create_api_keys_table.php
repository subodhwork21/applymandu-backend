<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('key_hash'); // Store hashed version
            $table->string('key_prefix', 10); // Store first 10 chars for display
            $table->json('permissions')->nullable(); // Remove default value
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('monthly_limit')->default(10000);
            $table->date('current_month')->nullable(); // Remove default value
            $table->integer('current_month_usage')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['employer_id', 'status']);
            $table->index(['key_hash']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
