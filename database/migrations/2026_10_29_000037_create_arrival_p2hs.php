<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrival_p2hs', function (Blueprint $table) {
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
            $table->string('status')->default('PENDING')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['vendor_submission_unit_id', 'revision']);
        });
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->foreignUuid('arrival_p2h_id')->nullable()->constrained('arrival_p2hs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arrival_p2h_id');
        });
        Schema::dropIfExists('arrival_p2hs');
    }
};
