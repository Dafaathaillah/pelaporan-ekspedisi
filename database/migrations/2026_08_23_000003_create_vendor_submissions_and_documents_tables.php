<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('submission_number')->unique();
            $table->string('type');
            $table->string('vendor_name');
            $table->string('applicant_name');
            $table->string('phone', 30);
            $table->text('description')->nullable();
            $table->string('status')->default('SUBMITTED');
            $table->text('coordinator_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('release_expired_at')->nullable();
            $table->unsignedInteger('revision_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->string('site');
            $table->timestamps();
            $table->index(['site', 'type', 'status']);
            $table->index(['site', 'submitted_at']);
            $table->index(['created_by', 'submitted_at']);
        });
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_submission_id')->constrained()->cascadeOnDelete();
            $table->string('document_code');
            $table->string('document_name');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_required');
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->unique(['vendor_submission_id', 'document_code']);
            $table->index('document_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendor_submissions');
    }
};
