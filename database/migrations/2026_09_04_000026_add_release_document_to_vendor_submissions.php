<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->string('release_document_path')->nullable()->after('release_expired_at');
            $table->string('release_document_name')->nullable()->after('release_document_path');
            $table->unsignedBigInteger('release_document_size')->nullable()->after('release_document_name');
            $table->timestamp('vendor_release_opened_at')->nullable()->after('release_document_size');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropColumn(['release_document_path', 'release_document_name', 'release_document_size', 'vendor_release_opened_at']);
        });
    }
};
