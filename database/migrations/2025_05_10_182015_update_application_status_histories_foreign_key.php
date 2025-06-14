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
        Schema::table('application_status_histories', function (Blueprint $table) {
            // Drop the existing foreign key
            $table->dropForeign(['application_id']);
            
            // Add the foreign key with cascade delete
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_status_histories', function (Blueprint $table) {
            // Drop the cascading foreign key
            $table->dropForeign(['application_id']);
            
            // Add back the original foreign key without cascade
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications');
        });
    }
};
