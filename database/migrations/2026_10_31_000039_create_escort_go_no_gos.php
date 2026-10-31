<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escort_go_no_gos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group_key', 64);
            $table->unsignedInteger('revision');
            $table->string('site');
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('escorter_name', 150);
            $table->string('nik', 50);
            $table->string('department')->default('SCM');
            $table->string('position')->default('Driver LV');
            $table->string('destination')->default('PPA-BIB');
            $table->json('units');
            $table->json('items');
            $table->string('driver_decision');
            $table->text('notes')->nullable();
            $table->string('p5m_photo_path');
            $table->string('status')->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name', 150)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->unique(['group_key', 'revision']);
            $table->index(['site', 'status', 'submitted_at']);
        });
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->foreignUuid('go_no_go_id')->nullable()->constrained('escort_go_no_gos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('go_no_go_id'));
        Schema::dropIfExists('escort_go_no_gos');
    }
};
