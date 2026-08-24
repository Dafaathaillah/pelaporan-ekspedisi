<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->timestamp('resubmission_requested_at')->nullable()->after('closed_at');
            $table->foreignId('resubmission_requested_by')->nullable()->after('resubmission_requested_at')->constrained('users')->nullOnDelete();
        });
        DB::table('vendor_submissions')->whereNotNull('released_at')->whereNull('release_expired_at')->select('id', 'released_at')->get()->each(fn ($row) => DB::table('vendor_submissions')->where('id', $row->id)->update(['release_expired_at' => Carbon::parse($row->released_at)->addDays(7)]));
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropForeign(['resubmission_requested_by']);$table->dropColumn(['resubmission_requested_at', 'resubmission_requested_by']);
        });
    }
};
