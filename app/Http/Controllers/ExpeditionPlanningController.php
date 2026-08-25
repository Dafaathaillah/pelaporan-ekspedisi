<?php

namespace App\Http\Controllers;

use App\Events\PlanningUpdated;
use App\Models\VendorSubmission;
use App\Support\SafeBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpeditionPlanningController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $perPage = in_array((int) $request->per_page, [10, 25, 50], true) ? (int) $request->per_page : 10;
        $items = VendorSubmission::query()
            ->with([
                'units:id,vendor_submission_id,plate_number,unit_order',
                'expeditionRequest:id,request_number,pr_number,po_number,route_from,route_to',
                'planningItems' => fn ($query) => $query->orderBy('planned_date')->orderBy('position'),
            ])
            ->where('site', $user->site)
            ->where('type', 'IJIN_LINTAS')
            ->where('status', 'RELEASED')
            ->whereDate('release_expired_at', '>=', today())
            ->when($request->search, fn ($query, $value) => $query->where(fn ($search) => $search
                ->where('submission_number', 'like', '%'.$value.'%')
                ->orWhere('vendor_name', 'like', '%'.$value.'%')
                ->orWhereHas('expeditionRequest', fn ($requestQuery) => $requestQuery->where('request_number', 'like', '%'.$value.'%'))))
            ->latest('released_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Expedition/Planning', [
            'items' => $items,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function update(Request $request, VendorSubmission $submission): RedirectResponse
    {
        abort_unless($submission->site === $request->user()->site && $submission->type->value === 'IJIN_LINTAS', 403);
        if ($submission->status->value !== 'RELEASED') {
            throw ValidationException::withMessages(['planned_date' => 'Tanggal hanya dapat diatur setelah Izin Lintas berstatus Released.']);
        }

        $lastDate = min(now()->addMonth()->endOfDay(), $submission->release_expired_at?->endOfDay() ?? now()->addMonth()->endOfDay());
        $data = $request->validate([
            'planned_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$lastDate->toDateString()],
        ]);

        $item = $submission->planningItems()->whereNull('planned_date')->oldest()->first()
            ?? $submission->planningItems()->oldest('planned_date')->first()
            ?? $submission->planningItems()->create(['position' => 0]);
        $item->update(['planned_date' => $data['planned_date'], 'updated_by' => $request->user()->id]);
        SafeBroadcast::dispatch(new PlanningUpdated($item->fresh('submission')));

        return back()->with('success', 'Tanggal planning tersimpan dan telah disinkronkan ke koordinator serta vendor.');
    }
}
