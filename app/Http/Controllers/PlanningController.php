<?php

namespace App\Http\Controllers;

use App\Events\PlanningUpdated;
use App\Models\DirectPlanning;
use App\Models\InternalIjinLintas;
use App\Models\PlanningItem;
use App\Models\VendorCompany;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $start = isset($validated['start_date'])
            ? now()->parse($validated['start_date'])->startOfDay()
            : now()->startOfDay();
        $end = isset($validated['end_date'])
            ? now()->parse($validated['end_date'])->startOfDay()
            : $start->copy()->addMonth();
        $sort = in_array($request->sort, ['vendor_name', 'type', 'status', 'submitted_at'], true) ? $request->sort : 'submitted_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $items = PlanningItem::with(['locker:id,name', 'unit:id,vendor_submission_id,subject_type,person_name,plate_number,vehicle_type,unit_order', 'submission' => fn ($q) => $q->with('units:id,vendor_submission_id,subject_type,person_name,plate_number,vehicle_type,unit_order')->withCount('units')])
            ->whereHas('submission', fn ($q) => $q->where('site', $request->user()->site)->where('status', '!=', 'REJECTED')
                ->when($request->user()->role->value === 'purchasing_admin', fn ($query) => $query->where('type', 'IJIN_LINTAS'))
                ->when($request->search, fn ($x, $v) => $x->where(fn ($y) => $y->where('vendor_name', 'like', '%'.$v.'%')->orWhere('submission_number', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')))
                ->when($request->type, fn ($x, $v) => $x->where('type', $v))
                ->when($request->status, fn ($x, $v) => $x->where('status', $v)))
            ->where(fn ($q) => $q->whereNull('planned_date')->orWhereBetween('planned_date', [$start->toDateString(), $end->toDateString()]))
            ->join('vendor_submissions', 'vendor_submissions.id', '=', 'planning_items.vendor_submission_id')
            ->select('planning_items.*')->orderBy('vendor_submissions.'.$sort, $direction)->orderBy('planning_items.position')->get();

        $directPlannings = DirectPlanning::with(['vendorCompany:id,name,abbreviation', 'locker:id,name', 'units'])
            ->where('site', $request->user()->site)
            ->whereHas('units', fn ($query) => $query->whereBetween('planned_date', [$start->toDateString(), $end->toDateString()]))
            ->latest()->get();
        $directItems = $directPlannings->flatMap(fn ($planning) => $planning->units
            ->filter(fn ($unit) => $unit->planned_date->betweenIncluded($start, $end))
            ->map(fn ($unit) => [
                'id' => $unit->id,
                'source' => 'direct',
                'direct_planning_id' => $planning->id,
                'planned_date' => $unit->planned_date->toDateString(),
                'vehicle_plate' => $unit->vehicle_plate,
                'vehicle_type' => $unit->vehicle_type,
                'route_from' => $unit->route_from,
                'route_to' => $unit->route_to,
                'vendor_name' => $planning->vendorCompany->name,
                'notes' => $planning->notes,
                'is_locked' => $planning->is_locked,
                'locked_by' => $planning->locker,
            ]))->values();

        $isPurchasing = $request->user()->role->value === 'purchasing_admin';
        $internalItems = InternalIjinLintas::where('site', $request->user()->site)
            ->whereBetween('escort_date', [$start->toDateString(), $end->toDateString()])->latest()->get();

        return Inertia::render('Coordinator/Planning', [
            'items' => $items,
            'directItems' => $directItems,
            'directPlannings' => $directPlannings,
            'internalItems' => $internalItems,
            'vendors' => VendorCompany::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'vehicleTypes' => ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya'],
            'dates' => collect(range(0, $start->diffInDays($end)))->map(fn ($day) => $start->copy()->addDays($day)->toDateString()),
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'filters' => $request->only(['search', 'type', 'status', 'sort', 'direction', 'start_date', 'end_date']),
            'planningBase' => $isPurchasing ? '/purchasing/planning' : '/coordinator/planning',
            'directPlanningBase' => $isPurchasing ? '/purchasing/direct-plannings' : '/coordinator/direct-plannings',
            'pageRole' => $request->user()->role->value,
        ]);
    }

    public function move(Request $request, PlanningItem $planningItem)
    {
        $this->authorizePlanning($request, $planningItem);
        abort_if($planningItem->is_locked, 422, 'Planning terkunci. Buka kunci sebelum memindahkan tanggal.');
        $data = $request->validate(['planned_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()], 'position' => ['nullable', 'integer', 'min:0', 'max:1000']]);
        $planningItem->update($data + ['updated_by' => $request->user()->id]);
        SafeBroadcast::dispatch(new PlanningUpdated($planningItem->fresh('submission')));

        return back()->with('success', $data['planned_date'] ? 'Planning berhasil dijadwalkan.' : 'Item dikembalikan ke daftar belum terjadwal.');
    }

    public function lock(Request $request, PlanningItem $planningItem)
    {
        $this->authorizePlanning($request, $planningItem);
        abort_unless($planningItem->planned_date, 422, 'Planning tanpa tanggal belum dapat dikunci.');
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $planningItem->update([
            'is_locked' => $data['locked'],
            'locked_at' => $data['locked'] ? now() : null,
            'locked_by' => $data['locked'] ? $request->user()->id : null,
            'updated_by' => $request->user()->id,
        ]);
        SafeBroadcast::dispatch(new PlanningUpdated($planningItem->fresh('submission')));

        return back()->with('success', $data['locked'] ? 'Tanggal planning dikunci.' : 'Kunci tanggal planning dibuka.');
    }

    private function authorizePlanning(Request $request, PlanningItem $planningItem): void
    {
        abort_unless($planningItem->submission->site === $request->user()->site, 403);
        if ($request->user()->role->value === 'purchasing_admin') {
            abort_unless($planningItem->submission->type->value === 'IJIN_LINTAS', 403);
        }
    }

    public function evidence(Request $request, PlanningItem $planningItem)
    {
        abort_unless($planningItem->submission->site === $request->user()->site, 403);
        $data = $request->validate(['evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        if ($planningItem->evidence_path) {
            Storage::disk('local')->delete($planningItem->evidence_path);
        }
        $path = $data['evidence']->store('private/planning-evidence/'.$planningItem->id, 'local');
        $planningItem->update(['evidence_path' => $path, 'evidence_name' => $data['evidence']->getClientOriginalName(), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Evidence WhatsApp berhasil dilampirkan.');
    }

    public function showEvidence(Request $request, PlanningItem $planningItem)
    {
        abort_unless($planningItem->submission->site === $request->user()->site && $planningItem->evidence_path && Storage::disk('local')->exists($planningItem->evidence_path), 404);

        return Storage::disk('local')->response($planningItem->evidence_path, $planningItem->evidence_name);
    }
}
