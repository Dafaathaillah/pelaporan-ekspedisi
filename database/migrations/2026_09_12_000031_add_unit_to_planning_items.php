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
        if (! Schema::hasColumn('planning_items', 'vendor_submission_unit_id')) {
            Schema::table('planning_items', function (Blueprint $table) {
                $table->foreignUuid('vendor_submission_unit_id')->nullable()->after('vendor_submission_id')->constrained('vendor_submission_units')->cascadeOnDelete();
            });
        }
        if (! Schema::hasIndex('planning_items', 'planning_submission_unit_unique')) {
            Schema::table('planning_items', fn (Blueprint $table) => $table->unique(['vendor_submission_id', 'vendor_submission_unit_id'], 'planning_submission_unit_unique'));
        }

        DB::table('vendor_submissions')->where('type', 'IJIN_LINTAS')->orderBy('id')->each(function ($submission) {
            $units = DB::table('vendor_submission_units')->where('vendor_submission_id', $submission->id)->orderBy('unit_order')->get();
            $items = DB::table('planning_items')->where('vendor_submission_id', $submission->id)->orderBy('position')->orderBy('created_at')->get();
            if ($units->isEmpty()) {
                return;
            }
            DB::table('planning_items')->where('vendor_submission_id', $submission->id)->update(['vendor_submission_unit_id' => null]);
            $used = [];
            $targets = [];
            foreach ($units as $position => $unit) {
                $item = $items->first(fn ($candidate) => ! isset($used[$candidate->id]) && DB::table('expedition_reports')->where('planning_item_id', $candidate->id)->where('vendor_submission_unit_id', $unit->id)->exists())
                    ?? $items->first(fn ($candidate) => ! isset($used[$candidate->id]));
                if (! $item) {
                    $source = $items->isNotEmpty() ? (array) $items->first() : ['vendor_submission_id' => $submission->id, 'position' => $position, 'created_at' => now(), 'updated_at' => now()];
                    unset($source['id']);
                    $source['id'] = (string) Str::uuid();
                    DB::table('planning_items')->insert($source);
                    $item = (object) $source;
                }
                $used[$item->id] = true;
                $targets[$unit->id] = $item->id;
                DB::table('planning_items')->where('id', $item->id)->update(['vendor_submission_unit_id' => $unit->id, 'position' => $position]);
            }
            foreach ($items->whereNotIn('id', array_keys($used)) as $surplus) {
                DB::table('expedition_reports')->where('planning_item_id', $surplus->id)->get()->each(function ($report) use ($targets) {
                    if (isset($targets[$report->vendor_submission_unit_id])) {
                        DB::table('expedition_reports')->where('id', $report->id)->update(['planning_item_id' => $targets[$report->vendor_submission_unit_id]]);
                    }
                });
                DB::table('planning_items')->where('id', $surplus->id)->delete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('planning_items', function (Blueprint $table) {
            $table->dropUnique('planning_submission_unit_unique');
            $table->dropConstrainedForeignId('vendor_submission_unit_id');
        });
    }
};
