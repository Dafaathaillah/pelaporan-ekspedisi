<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_requests', function (Blueprint $table) {
            $table->string('evidence_path')->nullable()->change();
            $table->string('evidence_original_name')->nullable()->change();
            $table->string('evidence_mime_type', 100)->nullable()->change();
            $table->unsignedBigInteger('evidence_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        // File lama tetap dipertahankan. Kolom tidak dikembalikan menjadi wajib
        // agar rollback tidak merusak request baru yang dibuat tanpa evidence.
    }
};
