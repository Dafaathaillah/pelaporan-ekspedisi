<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->uuid('internal_ijin_lintas_id')->nullable()->after('id')->index();
            $table->foreign('internal_ijin_lintas_id')->references('id')->on('internal_ijin_lintas')->cascadeOnDelete();
        });

        DB::table('internal_ijin_lintas')->orderBy('id')->get()->each(function ($permit) {
            foreach (json_decode($permit->vehicle_plates, true) ?: [] as $index => $plate) {
                DB::table('expedition_reports')->insert([
                    'id' => (string) Str::uuid(),
                    'internal_ijin_lintas_id' => $permit->id,
                    'report_number' => 'INT-MIG-'.Str::upper(Str::random(10)).'-'.($index + 1),
                    'expedition_company' => 'Internal', 'driver_name' => 'Driver Internal', 'driver_phone' => '-',
                    'vehicle_plate' => $plate, 'vehicle_type' => 'Light Vehicle', 'sender_vendor' => 'Internal',
                    'destination' => $permit->title, 'arrived_at' => $permit->created_at, 'created_by' => $permit->created_by,
                    'site' => $permit->site, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('expedition_reports')->whereNotNull('internal_ijin_lintas_id')->delete();
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropForeign(['internal_ijin_lintas_id']);
            $table->dropColumn('internal_ijin_lintas_id');
        });
    }
};
