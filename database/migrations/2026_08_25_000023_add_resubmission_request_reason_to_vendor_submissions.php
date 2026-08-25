<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->text('resubmission_request_reason')->nullable()->after('resubmission_subject_ids');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', fn (Blueprint $table) => $table->dropColumn('resubmission_request_reason'));
    }
};
