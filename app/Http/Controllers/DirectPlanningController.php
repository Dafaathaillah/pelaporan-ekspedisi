<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDirectPlanningRequest;
use App\Models\DirectPlanning;
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
        $this->authorizeSite($request, $directPlanning);
        abort_if($directPlanning->is_locked, 422, 'Planning langsung terkunci. Buka kunci sebelum mengubah data.');

        DB::transaction(function () use ($request, $directPlanning) {
            $directPlanning->update([
                'vendor_company_id' => $request->validated('vendor_company_id'),
                'notes' => $request->validated('notes'),
                'updated_by' => $request->user()->id,
            ]);
            $this->syncUnits($directPlanning, $request->validated('units'), true);
        });

        return back()->with('success', 'Planning langsung berhasil diperbarui.');
    }

    public function lock(Request $request, DirectPlanning $directPlanning)
    {
        $this->authorizeSite($request, $directPlanning);
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $directPlanning->update([
            'is_locked' => $data['locked'],
            'locked_at' => $data['locked'] ? now() : null,
            'locked_by' => $data['locked'] ? $request->user()->id : null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $data['locked'] ? 'Planning langsung berhasil dikunci.' : 'Kunci planning langsung berhasil dibuka.');
    }

    public function destroy(Request $request, DirectPlanning $directPlanning)
    {
        $this->authorizeSite($request, $directPlanning);
        abort_if($directPlanning->is_locked, 422, 'Planning langsung terkunci. Buka kunci sebelum menghapus data.');
        DB::transaction(fn () => $directPlanning->delete());

        return back()->with('success', 'Planning langsung berhasil dihapus.');
    }

    private function authorizeSite(Request $request, DirectPlanning $planning): void
    {
        abort_unless($planning->site === $request->user()->site, 403);
    }

    private function syncUnits(DirectPlanning $planning, array $units, bool $updating = false): void
    {
        $retained = [];
        foreach (array_values($units) as $position => $unit) {
            $attributes = collect($unit)->except('id')->all() + ['position' => $position];
            if ($updating && isset($unit['id'])) {
                $model = $planning->units()->whereKey($unit['id'])->firstOrFail();
                $model->update($attributes);
                $retained[] = $model->id;
            } else {
                $retained[] = $planning->units()->create($attributes)->id;
            }
        }
        if ($updating) {
            $planning->units()->whereNotIn('id', $retained)->delete();
        }
    }
}
