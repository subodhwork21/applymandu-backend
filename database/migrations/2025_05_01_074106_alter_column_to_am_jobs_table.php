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
        Schema::table('am_jobs', function (Blueprint $table) {
            $table->enum('employment_type', ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'])->change()->after('is_remote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('am_jobs', function (Blueprint $table) {
            $table->string("employment_type")->change();
        });
    }
};
