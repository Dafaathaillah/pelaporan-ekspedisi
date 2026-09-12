<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->string('p2h_photo_path')->nullable()->after('blood_pressure_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropColumn('p2h_photo_path');
        });
    }
};
