<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->string('p2h_status')->default('PENDING')->after('p2h_photo_path');
            $table->text('p2h_rejection_reason')->nullable()->after('p2h_status');
            $table->timestamp('p2h_reviewed_at')->nullable()->after('p2h_rejection_reason');
            $table->foreignId('p2h_reviewed_by')->nullable()->after('p2h_reviewed_at')->constrained('users')->nullOnDelete();
            $table->json('p2h_review_history')->nullable()->after('p2h_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('p2h_reviewed_by');
            $table->dropColumn(['p2h_status', 'p2h_rejection_reason', 'p2h_reviewed_at', 'p2h_review_history']);
        });
    }
};
