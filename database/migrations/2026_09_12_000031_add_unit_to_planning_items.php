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
        Schema::table('planning_items', function (Blueprint $table) {
            $table->foreignUuid('vendor_submission_unit_id')->nullable()->after('vendor_submission_id')->constrained('vendor_submission_units')->cascadeOnDelete();
            $table->unique(['vendor_submission_id', 'vendor_submission_unit_id'], 'planning_submission_unit_unique');
        });

        DB::table('vendor_submissions')->where('type', 'IJIN_LINTAS')->orderBy('id')->each(function ($submission) {
            $units = DB::table('vendor_submission_units')->where('vendor_submission_id', $submission->id)->orderBy('unit_order')->get();
            $items = DB::table('planning_items')->where('vendor_submission_id', $submission->id)->orderBy('position')->orderBy('created_at')->get();
            if ($units->isEmpty()) {
                return;
            }
            if ($items->isEmpty()) {
                foreach ($units as $position => $unit) {
                    DB::table('planning_items')->insert(['id' => (string) Str::uuid(), 'vendor_submission_id' => $submission->id, 'vendor_submission_unit_id' => $unit->id, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
                }

                return;
            }
            foreach ($units as $position => $unit) {
                if (isset($items[$position])) {
                    DB::table('planning_items')->where('id', $items[$position]->id)->update(['vendor_submission_unit_id' => $unit->id, 'position' => $position]);
                } else {
                    $source = (array) $items->first();
                    unset($source['id']);
                    $source['id'] = (string) Str::uuid();
                    $source['vendor_submission_unit_id'] = $unit->id;
                    $source['position'] = $position;
                    DB::table('planning_items')->insert($source);
                }
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
