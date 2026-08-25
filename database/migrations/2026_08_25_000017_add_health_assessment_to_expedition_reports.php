<?php

use App\Support\BloodPressureAssessment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->string('health_status', 30)->nullable()->after('blood_pressure_diastolic')->index();
            $table->string('health_status_label', 100)->nullable()->after('health_status');
            $table->text('health_status_reason')->nullable()->after('health_status_label');
        });

        DB::table('expedition_reports')
            ->whereNotNull('blood_pressure_systolic')
            ->whereNotNull('blood_pressure_diastolic')
            ->orderBy('id')
            ->each(function ($report) {
                $assessment = BloodPressureAssessment::assess((int) $report->blood_pressure_systolic, (int) $report->blood_pressure_diastolic);
                DB::table('expedition_reports')->where('id', $report->id)->update([
                    'health_status' => $assessment['status'],
                    'health_status_label' => $assessment['label'],
                    'health_status_reason' => $assessment['reason'],
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('expedition_reports', function (Blueprint $table) {
            $table->dropColumn(['health_status', 'health_status_label', 'health_status_reason']);
        });
    }
};
