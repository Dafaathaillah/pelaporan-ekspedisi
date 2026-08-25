<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_submissions', 'parent_submission_id')) {
            Schema::table('vendor_submissions', function (Blueprint $table) {
                $table->foreignUuid('parent_submission_id')->nullable()->after('id')->constrained('vendor_submissions')->nullOnDelete();
                $table->string('service_category')->nullable()->after('type');
                $table->unsignedTinyInteger('submission_sequence')->default(1)->after('service_category');
                $table->timestamp('coordinator_opened_at')->nullable()->after('reviewed_at');
                $table->timestamp('closed_at')->nullable()->after('released_at');
            });
        }
        $indexes = collect(Schema::getIndexes('vendor_submissions'));
        if (! $indexes->contains(fn ($index) => $index['columns'] === ['site', 'type', 'coordinator_opened_at'])) {
            Schema::table('vendor_submissions', fn (Blueprint $table) => $table->index(['site', 'type', 'coordinator_opened_at'], 'vs_unopened_idx'));
        }
        if (! $indexes->contains(fn ($index) => $index['columns'] === ['parent_submission_id', 'submission_sequence'])) {
            Schema::table('vendor_submissions', fn (Blueprint $table) => $table->index(['parent_submission_id', 'submission_sequence'], 'vs_sequence_idx'));
        }
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropForeign(['parent_submission_id']);
            $table->dropIndex('vs_unopened_idx');
            $table->dropIndex('vs_sequence_idx');
            $table->dropColumn(['parent_submission_id', 'service_category', 'submission_sequence', 'coordinator_opened_at', 'closed_at']);
        });
    }
};
