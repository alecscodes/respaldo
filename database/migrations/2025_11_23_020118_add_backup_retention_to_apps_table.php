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
            $table->unsignedInteger('retention_days')->nullable()->after('backup_days')->comment('Number of days to keep backups. If null, no age-based retention.');
            $table->unsignedInteger('retention_count')->nullable()->after('retention_days')->comment('Minimum number of backups to keep. If null, no count-based retention.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['retention_days', 'retention_count']);
        });
    }
};
