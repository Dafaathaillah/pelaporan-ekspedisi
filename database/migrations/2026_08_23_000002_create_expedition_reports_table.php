<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedition_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_number')->unique();
            $table->string('expedition_company')->index();
            $table->string('driver_name');
            $table->string('driver_phone', 30);
            $table->string('vehicle_plate', 20)->index();
            $table->string('vehicle_type');
            $table->string('sender_vendor')->index();
            $table->string('destination');
            $table->timestamp('arrived_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('site');
            $table->timestamps();
            $table->index(['site', 'arrived_at']);
            $table->index(['site', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedition_reports');
    }
};
