<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Events\PlanningDeleted;
use App\Events\PlanningUpdated;
use App\Models\PlanningItem;
use App\Models\VendorSubmission;
use App\Support\SafeBroadcast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorPlanningController extends Controller
{
    public function index(Request $request): Response
    {
        $items = VendorSubmission::query()
            ->with(['units:id,vendor_submission_id,plate_number,unit_order', 'planningItems' => fn ($query) => $query->whereNotNull('planned_date')->with('locker:id,name')->orderBy('planned_date')])
            ->where('created_by', $request->user()->id)->where('site', $request->user()->site)
            ->where('type', SubmissionType::IJIN_LINTAS)->where('status', SubmissionStatus::RELEASED)
            ->whereDate('release_expired_at', '>=', today())->latest('released_at')->get();

        VendorSubmission::whereKey($items->pluck('id'))->whereNull('vendor_release_opened_at')->update(['vendor_release_opened_at' => now()]);

        return Inertia::render('Vendor/PlanningLintas', ['items' => $items]);
    }

    public function store(Request $request, VendorSubmission $submission): RedirectResponse
    {
        $this->authorizeActiveReleased($request, $submission);
        $data = $this->validateDate($request, $submission);
        $item = $submission->planningItems()->whereNull('planned_date')->where('is_locked', false)->oldest()->first()
            ?? $submission->planningItems()->create(['position' => $submission->planningItems()->max('position') + 1]);
        $item->update(['planned_date' => $data['planned_date'], 'updated_by' => $request->user()->id]);
        SafeBroadcast::dispatch(new PlanningUpdated($item->fresh('submission')));

        return back()->with('success', 'Tanggal planning berhasil ditambahkan.');
    }

    public function update(Request $request, PlanningItem $planningItem): RedirectResponse
    {
        $submission = $planningItem->submission;
        $this->authorizeActiveReleased($request, $submission);
        abort_if($planningItem->is_locked, 422, 'Planning telah dikunci oleh reviewer.');
        $data = $this->validateDate($request, $submission, $planningItem);
        $planningItem->update(['planned_date' => $data['planned_date'], 'updated_by' => $request->user()->id]);
        SafeBroadcast::dispatch(new PlanningUpdated($planningItem->fresh('submission')));

        return back()->with('success', 'Tanggal planning berhasil diperbarui.');
    }

    public function destroy(Request $request, PlanningItem $planningItem): RedirectResponse
    {
        $this->authorizeActiveReleased($request, $planningItem->submission);
        abort_if($planningItem->is_locked, 422, 'Planning telah dikunci oleh reviewer.');
        $submission = $planningItem->submission;
        $event = new PlanningDeleted($planningItem->id, $submission->id, $submission->submission_number, $submission->vendor_name, $submission->site, $submission->created_by);
        $planningItem->delete();
        SafeBroadcast::dispatch($event);

        return back()->with('success', 'Tanggal planning berhasil dihapus.');
    }

    private function authorizeActiveReleased(Request $request, VendorSubmission $submission): void
    {
        abort_unless($submission->created_by === $request->user()->id && $submission->site === $request->user()->site, 403);
        if ($submission->type !== SubmissionType::IJIN_LINTAS || $submission->status !== SubmissionStatus::RELEASED) {
            throw ValidationException::withMessages(['planned_date' => 'Planning hanya dapat diisi setelah Ijin Lintas berstatus Released.']);
        }
        if (! $submission->release_expired_at || $submission->release_expired_at->isPast()) {
            throw ValidationException::withMessages(['planned_date' => 'Masa berlaku Ijin Lintas telah berakhir.']);
        }
    }

    private function validateDate(Request $request, VendorSubmission $submission, ?PlanningItem $planningItem = null): array
    {
        return $request->validate([
            'planned_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$submission->release_expired_at->toDateString(), Rule::unique('planning_items', 'planned_date')->where('vendor_submission_id', $submission->id)->ignore($planningItem?->id)],
        ], ['planned_date.unique' => 'Tanggal tersebut sudah ada pada planning izin ini.']);
    }
}
