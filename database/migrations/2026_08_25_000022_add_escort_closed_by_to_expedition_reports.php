<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->foreignId('escort_closed_by')->nullable()->after('escort_driver_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('escort_closed_by'));
    }
};
