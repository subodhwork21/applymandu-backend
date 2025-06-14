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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->text('content');
            $table->enum('type', ['text', 'image', 'file'])->default('text');
            $table->string('file_path')->nullable(); 
            $table->string('file_name')->nullable(); 
            $table->unsignedBigInteger('chat_id')->nullable(); 
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            
            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
