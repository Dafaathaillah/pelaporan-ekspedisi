<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable()->after('destination');
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable()->after('blood_pressure_systolic');
            $table->string('arrival_photo_path')->nullable()->after('blood_pressure_diastolic');
            $table->string('blood_pressure_photo_path')->nullable()->after('arrival_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropColumn([
                'blood_pressure_systolic',
                'blood_pressure_diastolic',
                'arrival_photo_path',
                'blood_pressure_photo_path',
            ]);
        });
    }
};
