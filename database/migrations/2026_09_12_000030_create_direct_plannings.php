<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_plannings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('vendor_company_id')->constrained()->restrictOnDelete();
            $table->string('site', 50)->index();
            $table->text('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('direct_planning_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('direct_planning_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_plate', 30);
            $table->string('vehicle_type', 50);
            $table->string('route_from', 200);
            $table->string('route_to', 200);
            $table->date('planned_date')->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['direct_planning_id', 'vehicle_plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_planning_units');
        Schema::dropIfExists('direct_plannings');
    }
};
