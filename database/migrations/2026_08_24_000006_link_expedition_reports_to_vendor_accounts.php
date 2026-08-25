<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->foreignId('vendor_user_id')->nullable()->after('sender_vendor')->constrained('users')->nullOnDelete();
            $table->index(['site', 'vendor_user_id', 'arrived_at'], 'exp_vendor_monitor_idx');
        });
        DB::table('vendor_submissions')->select('created_by', 'site', 'vendor_name')->distinct()->orderBy('created_by')->each(function ($vendor) {
            DB::table('expedition_reports')->where('site', $vendor->site)->where('sender_vendor', $vendor->vendor_name)->whereNull('vendor_user_id')->update(['vendor_user_id' => $vendor->created_by]);
        });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropIndex('exp_vendor_monitor_idx');
            $table->dropForeign(['vendor_user_id']);
            $table->dropColumn('vendor_user_id');
        });
    }
};
