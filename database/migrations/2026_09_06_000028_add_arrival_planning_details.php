<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submission_units', function (Blueprint $table) {
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('vehicle_type', 50)->nullable();
        });
        Schema::table('vendor_submissions', fn (Blueprint $table) => $table->string('destination', 150)->nullable());
        Schema::table('planning_items', function (Blueprint $table) {
            $table->date('previous_planned_date')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->timestamp('rescheduled_at')->nullable();
        });
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->foreignUuid('planning_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('vendor_submission_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('p5m_photo_path')->nullable();
            $table->unique(['planning_item_id', 'vendor_submission_unit_id'], 'arrival_planning_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropUnique('arrival_planning_unit_unique');
            $table->dropConstrainedForeignId('planning_item_id');
            $table->dropConstrainedForeignId('vendor_submission_unit_id');
            $table->dropColumn('p5m_photo_path');
        });
        Schema::table('planning_items', fn (Blueprint $table) => $table->dropColumn(['previous_planned_date', 'reschedule_reason', 'rescheduled_at']));
        Schema::table('vendor_submissions', fn (Blueprint $table) => $table->dropColumn('destination'));
        Schema::table('vendor_submission_units', fn (Blueprint $table) => $table->dropColumn(['driver_name', 'driver_phone', 'vehicle_type']));
    }
};
