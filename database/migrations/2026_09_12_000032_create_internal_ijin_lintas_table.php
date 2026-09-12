<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_ijin_lintas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->date('escort_date')->index();
            $table->unsignedSmallInteger('unit_count');
            $table->json('vehicle_plates');
            $table->string('site', 50)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_ijin_lintas');
    }
};
