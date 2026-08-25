<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->string('escort_status', 20)->default('WAITING')->after('arrived_at')->index();
            $table->foreignId('escort_driver_id')->nullable()->after('escort_status')->constrained('users')->nullOnDelete();
            $table->timestamp('escort_started_at')->nullable();
            $table->timestamp('escort_closed_at')->nullable();
            $table->string('escort_close_photo_path')->nullable();
            $table->decimal('escort_close_latitude', 10, 7)->nullable();
            $table->decimal('escort_close_longitude', 10, 7)->nullable();
            $table->decimal('escort_close_location_accuracy', 8, 2)->nullable();
            $table->text('escort_close_location_name')->nullable();
            $table->timestamp('escort_close_photo_captured_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escort_driver_id');
            $table->dropColumn(['escort_status', 'escort_started_at', 'escort_closed_at', 'escort_close_photo_path', 'escort_close_latitude', 'escort_close_longitude', 'escort_close_location_accuracy', 'escort_close_location_name', 'escort_close_photo_captured_at']);
        });
    }
};
