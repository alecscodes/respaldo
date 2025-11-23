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
        Schema::table('apps', function (Blueprint $table) {
            $table->string('backup_period')->nullable()->after('storage_size');
            $table->json('backup_days')->nullable()->after('backup_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['backup_period', 'backup_days']);
        });
    }
};
