<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_requests', function (Blueprint $table) {
            $table->string('readiness_status', 20)->default('PENDING')->after('status');
            $table->text('readiness_reason')->nullable()->after('readiness_status');
            $table->foreignId('readiness_by')->nullable()->after('readiness_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('readiness_at')->nullable()->after('readiness_by');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('readiness_by');
            $table->dropColumn(['readiness_status', 'readiness_reason', 'readiness_at']);
        });
    }
};
