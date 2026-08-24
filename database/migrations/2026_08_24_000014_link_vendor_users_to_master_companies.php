<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('vendor_company_id')->nullable()->after('role')->constrained('vendor_companies')->nullOnDelete();
        });
        foreach (['vendor_dma' => 'DMA', 'vendor_bumdes' => 'BUMDES', 'vendor_sinar' => 'SJPL'] as $username => $abbreviation) {
            $company = DB::table('vendor_companies')->where('abbreviation', $abbreviation)->first();
            if ($company) {
                DB::table('users')->where('username', $username)->update(['vendor_company_id' => $company->id, 'name' => $company->name]);
                $user = DB::table('users')->where('username', $username)->first();
                if ($user) {
                    DB::table('vendor_submissions')->where('created_by', $user->id)->update(['vendor_name' => $company->name]);
                    DB::table('expedition_reports')->where('vendor_user_id', $user->id)->update(['sender_vendor' => $company->name]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_company_id');
        });
    }
};
