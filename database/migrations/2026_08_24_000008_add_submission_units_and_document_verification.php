<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_submission_units')) {
            Schema::create('vendor_submission_units', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('vendor_submission_id')->constrained()->cascadeOnDelete();
                $t->string('plate_number', 20);
                $t->unsignedSmallInteger('unit_order')->default(1);
                $t->timestamps();
                $t->unique(['vendor_submission_id', 'plate_number']);
            });
        }
        if (! Schema::hasColumn('vendor_documents', 'vendor_submission_unit_id')) {
            Schema::table('vendor_documents', fn (Blueprint $t) => $t->index('vendor_submission_id', 'vendor_documents_submission_idx'));
            Schema::table('vendor_documents', function (Blueprint $t) {
                $t->dropUnique(['vendor_submission_id', 'document_code']);
                $t->foreignUuid('vendor_submission_unit_id')->nullable()->after('vendor_submission_id')->constrained('vendor_submission_units')->cascadeOnDelete();
                $t->string('verification_status')->default('PENDING');
                $t->text('verification_note')->nullable();
                $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('verified_at')->nullable();
                $t->unique(['vendor_submission_unit_id', 'document_code'], 'unit_document_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $t) {
            $t->dropForeign(['vendor_submission_unit_id']);
            $t->dropForeign(['verified_by']);
            $t->dropColumn(['vendor_submission_unit_id', 'verification_status', 'verification_note', 'verified_by', 'verified_at']);
        });
        Schema::dropIfExists('vendor_submission_units');
    }
};
