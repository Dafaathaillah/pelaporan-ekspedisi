<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departure_p2hs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_submission_unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('driver_name');
            $table->string('vehicle_plate', 20);
            $table->string('vehicle_type');
            $table->json('items');
            $table->json('tire_positions');
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamps();
            $table->unique(['vendor_submission_unit_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departure_p2hs');
    }
};
