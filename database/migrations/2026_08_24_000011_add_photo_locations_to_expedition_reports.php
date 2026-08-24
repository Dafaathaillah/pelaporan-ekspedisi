<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            foreach (['arrival', 'blood_pressure'] as $prefix) {
                $table->decimal($prefix.'_latitude', 10, 7)->nullable();
                $table->decimal($prefix.'_longitude', 10, 7)->nullable();
                $table->unsignedInteger($prefix.'_location_accuracy')->nullable();
                $table->string($prefix.'_location_name', 500)->nullable();
                $table->timestamp($prefix.'_photo_captured_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            foreach (['arrival', 'blood_pressure'] as $prefix) {
                $table->dropColumn([$prefix.'_latitude', $prefix.'_longitude', $prefix.'_location_accuracy', $prefix.'_location_name', $prefix.'_photo_captured_at']);
            }
        });
    }
};
