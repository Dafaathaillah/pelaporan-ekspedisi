<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDirectPlanningRequest;
use App\Models\DirectPlanning;
use App\Models\DirectPlanningUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectPlanningController extends Controller
{
    public function store(SaveDirectPlanningRequest $request)
    {
        DB::transaction(function () use ($request) {
            $planning = DirectPlanning::create([
                'vendor_company_id' => $request->validated('vendor_company_id'),
                'site' => $request->user()->site,
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->syncUnits($planning, $request->validated('units'));
        });

        return back()->with('success', 'Planning langsung berhasil dibuat.');
    }

    public function update(SaveDirectPlanningRequest $request, DirectPlanning $directPlanning)
    {
        DB::transaction(function () use ($request, $directPlanning) {
            $planning = DirectPlanning::lockForUpdate()->findOrFail($directPlanning->id);
            $this->authorizeSite($request, $planning);
            abort_if($planning->is_locked, 422, 'Planning langsung terkunci. Buka kunci sebelum mengubah data.');
            $planning->update([
                'vendor_company_id' => $request->validated('vendor_company_id'),
                'notes' => $request->validated('notes'),
                'updated_by' => $request->user()->id,
            ]);
            $this->syncUnits($planning, $request->validated('units'), true);
        });

        return back()->with('success', 'Planning langsung berhasil diperbarui.');
    }

    public function lock(Request $request, DirectPlanning $directPlanning)
    {
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $directPlanning, $data) {
            $planning = DirectPlanning::lockForUpdate()->findOrFail($directPlanning->id);
            $this->authorizeSite($request, $planning);
            $planning->update([
                'is_locked' => $data['locked'],
                'locked_at' => $data['locked'] ? now() : null,
                'locked_by' => $data['locked'] ? $request->user()->id : null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', $data['locked'] ? 'Planning langsung berhasil dikunci.' : 'Kunci planning langsung berhasil dibuka.');
    }

    public function destroy(Request $request, DirectPlanning $directPlanning)
    {
        DB::transaction(function () use ($request, $directPlanning) {
            $planning = DirectPlanning::lockForUpdate()->findOrFail($directPlanning->id);
            $this->authorizeSite($request, $planning);
            abort_if($planning->is_locked, 422, 'Planning langsung terkunci. Buka kunci sebelum menghapus data.');
            $planning->delete();
        });

        return back()->with('success', 'Planning langsung berhasil dihapus.');
    }

    public function moveUnit(Request $request, DirectPlanningUnit $directPlanningUnit)
    {
        $this->authorizeSite($request, $directPlanningUnit->planning);
        $data = $request->validate(['planned_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()]]);
        DB::transaction(function () use ($request, $directPlanningUnit, $data) {
            $planning = DirectPlanning::lockForUpdate()->findOrFail($directPlanningUnit->direct_planning_id);
            $this->authorizeSite($request, $planning);
            abort_if($planning->is_locked, 422, 'Planning langsung terkunci. Buka kunci sebelum memindahkan tanggal.');
            DirectPlanningUnit::whereKey($directPlanningUnit->id)->update(['planned_date' => $data['planned_date']]);
            $planning->update(['updated_by' => $request->user()->id]);
        });

        return back()->with('success', 'Tanggal unit planning langsung berhasil dipindahkan.');
    }

    private function authorizeSite(Request $request, DirectPlanning $planning): void
    {
        abort_unless($planning->site === $request->user()->site, 403);
    }

    private function syncUnits(DirectPlanning $planning, array $units, bool $updating = false): void
    {
        if ($updating) {
            $retainedIds = collect($units)->pluck('id')->filter()->all();
            $planning->units()->whereNotIn('id', $retainedIds)->delete();
            $planning->units()->whereIn('id', $retainedIds)->get()->each(
                fn ($unit, $index) => $unit->update(['vehicle_plate' => '~TMP-'.$index.'-'.substr($unit->id, 0, 18)]),
            );
        }

        foreach (array_values($units) as $position => $unit) {
            $attributes = collect($unit)->except('id')->all() + ['position' => $position];
            if ($updating && isset($unit['id'])) {
                $model = $planning->units()->whereKey($unit['id'])->firstOrFail();
                $model->update($attributes);
            } else {
                $planning->units()->create($attributes);
            }
        }
    }
}
