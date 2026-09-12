<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->uuid('escort_batch_id')->nullable()->index()->after('escort_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropIndex(['escort_batch_id']);
            $table->dropColumn('escort_batch_id');
        });
    }
};
