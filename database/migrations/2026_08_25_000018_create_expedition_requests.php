<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedition_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number')->unique();
            $table->foreignId('vendor_company_id')->constrained()->restrictOnDelete();
            $table->string('expedition_type', 50)->index();
            $table->string('route_from', 200);
            $table->string('route_to', 200);
            $table->text('description')->nullable();
            $table->string('evidence_path');
            $table->string('evidence_original_name');
            $table->string('evidence_mime_type', 100);
            $table->unsignedBigInteger('evidence_size');
            $table->string('status', 20)->default('OPEN')->index();
            $table->foreignId('created_by')->constrained('users');
            $table->string('site', 30)->index();
            $table->timestamps();
            $table->index(['vendor_company_id', 'site', 'status']);
        });

        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->foreignUuid('expedition_request_id')->nullable()->after('type')->constrained('expedition_requests')->nullOnDelete();
            $table->index(['site', 'expedition_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('expedition_request_id'));
        Schema::dropIfExists('expedition_requests');
    }
};
