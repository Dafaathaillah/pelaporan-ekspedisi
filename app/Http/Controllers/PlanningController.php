<?php

namespace App\Http\Controllers;

use App\Events\PlanningUpdated;
use App\Models\PlanningItem;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addMonth();
        $sort = in_array($request->sort, ['vendor_name', 'type', 'status', 'submitted_at'], true) ? $request->sort : 'submitted_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $items = PlanningItem::with(['submission' => fn ($q) => $q->with('units:id,vendor_submission_id,subject_type,person_name,plate_number,unit_order')->withCount('units')])
            ->whereHas('submission', fn ($q) => $q->where('site', $request->user()->site)->where('status', '!=', 'REJECTED')
                ->when($request->search, fn ($x, $v) => $x->where(fn ($y) => $y->where('vendor_name', 'like', '%'.$v.'%')->orWhere('submission_number', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')))
                ->when($request->type, fn ($x, $v) => $x->where('type', $v))
                ->when($request->status, fn ($x, $v) => $x->where('status', $v)))
            ->where(fn ($q) => $q->whereNull('planned_date')->orWhereBetween('planned_date', [$start, $end]))
            ->join('vendor_submissions', 'vendor_submissions.id', '=', 'planning_items.vendor_submission_id')
            ->select('planning_items.*')->orderBy('vendor_submissions.'.$sort, $direction)->orderBy('planning_items.position')->get();

        return Inertia::render('Coordinator/Planning', ['items' => $items, 'dates' => collect(range(0, $start->diffInDays($end)))->map(fn ($day) => $start->copy()->addDays($day)->toDateString()), 'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()], 'filters' => $request->only(['search', 'type', 'status', 'sort', 'direction'])]);
    }

    public function move(Request $request, PlanningItem $planningItem)
    {
        abort_unless($planningItem->submission->site === $request->user()->site, 403);
        $data = $request->validate(['planned_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()], 'position' => ['nullable', 'integer', 'min:0', 'max:1000']]);
        $planningItem->update($data + ['updated_by' => $request->user()->id]);
        SafeBroadcast::dispatch(new PlanningUpdated($planningItem->fresh('submission')));

        return back()->with('success', $data['planned_date'] ? 'Planning berhasil dijadwalkan.' : 'Item dikembalikan ke daftar belum terjadwal.');
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
