<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_companies', function (Blueprint $table) {
            $table->enum('planning_frequency', ['DAILY', 'WEEKLY'])->nullable()->after('category');
        });
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->enum('planning_frequency', ['DAILY', 'WEEKLY', 'NON_REGULAR'])->nullable()->after('service_category');
        });
        Schema::create('planning_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_submission_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date')->nullable()->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['planned_date', 'position']);
        });
        DB::table('vendor_companies')->update(['remarks' => null]);
        DB::table('vendor_submissions')->orderBy('id')->each(function ($submission) {
            DB::table('planning_items')->insert(['id' => (string) Str::uuid(), 'vendor_submission_id' => $submission->id, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_items');
        Schema::table('vendor_submissions', fn (Blueprint $table) => $table->dropColumn('planning_frequency'));
        Schema::table('vendor_companies', fn (Blueprint $table) => $table->dropColumn('planning_frequency'));
    }
};
