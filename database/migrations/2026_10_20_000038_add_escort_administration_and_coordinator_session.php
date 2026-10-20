<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->string('invoice_scan_path')->nullable();
            $table->string('spb_scan_path')->nullable();
            $table->timestamp('invoice_uploaded_at')->nullable();
            $table->timestamp('spb_uploaded_at')->nullable();
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('coordinator_session_hash', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('coordinator_session_hash'));
        Schema::table('expedition_reports', fn (Blueprint $table) => $table->dropColumn(['invoice_scan_path', 'spb_scan_path', 'invoice_uploaded_at', 'spb_uploaded_at']));
    }
};
