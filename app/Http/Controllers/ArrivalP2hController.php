<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ArrivalP2h;
use App\Models\ExpeditionReport;
use App\Models\VendorSubmissionUnit;
use App\Services\ArrivalPlanningService;
use App\Services\P2hPdf;
use App\Support\P2hChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArrivalP2hController extends Controller
{
    public function store(Request $request, VendorSubmissionUnit $unit)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'size:'.count(P2hChecklist::ITEMS)],
            'items.*' => ['required', 'array:condition,note'],
            'items.*.condition' => ['required', Rule::in(['BAIK', 'RUSAK', 'NIHIL'])],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'tire_positions' => ['required', 'array', 'min:1'],
            'tire_positions.*' => ['required', 'integer', 'between:1,20', 'distinct'],
            'decision' => ['required', Rule::in(['LAIK', 'TIDAK_LAIK'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (array_keys($data['items']) !== range(0, count(P2hChecklist::ITEMS) - 1)) {
            throw ValidationException::withMessages(['items' => 'Seluruh item pemeriksaan P2H wajib diisi berurutan.']);
        }
        DB::transaction(function () use ($request, $unit, $data) {
            $unit = VendorSubmissionUnit::lockForUpdate()->findOrFail($unit->id);
            $permit = $unit->submission;
            abort_unless(app(ArrivalPlanningService::class)->permits($request->user())->whereKey($permit->id)->exists(), 403);
            $departure = $unit->latestDepartureP2h;
            $scheduled = $permit->planningItems()->whereNotNull('planned_date')
                ->where(fn ($q) => $q->where('vendor_submission_unit_id', $unit->id)->orWhereNull('vendor_submission_unit_id'))->exists();
            if ($permit->status->value !== 'RELEASED' || ! $permit->release_expired_at || $permit->release_expired_at->isPast()
                || ! $scheduled || ! $departure || $departure->status !== 'APPROVED' || ! $departure->departed_at) {
                throw ValidationException::withMessages(['arrival_p2h' => 'Izin harus Released dan masih berlaku, unit memiliki planning, serta keberangkatan sudah disetujui dan dikonfirmasi.']);
            }
            if (! $unit->driver_name || ! $unit->driver_phone || ! $unit->vehicle_type || ! $unit->plate_number || ! $permit->destination) {
                throw ValidationException::withMessages(['arrival_p2h' => 'Lengkapi identitas driver, kendaraan, dan tujuan pada izin.']);
            }
            $latest = $unit->arrivalP2hs()->first();
            if (($latest && $latest->status !== 'REJECTED') || ExpeditionReport::where('vendor_submission_unit_id', $unit->id)->exists()) {
                throw ValidationException::withMessages(['arrival_p2h' => 'P2H kedatangan sedang menunggu persetujuan, sudah disetujui, atau kedatangan telah dilaporkan.']);
            }
            ArrivalP2h::create([...$data, 'vendor_submission_unit_id' => $unit->id, 'revision' => ($latest?->revision ?? 0) + 1,
                'submitted_by' => $request->user()->id, 'driver_name' => $unit->driver_name, 'vehicle_plate' => $unit->plate_number,
                'vehicle_type' => $unit->vehicle_type, 'status' => 'PENDING']);
        });

        return back()->with('success', 'P2H kedatangan dikirim untuk persetujuan Coordinator.');
    }

    public function review(Request $request, ArrivalP2h $p2h)
    {
        abort_unless($p2h->unit->submission->site === $request->user()->site, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'reason' => ['required_if:status,REJECTED', 'nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $p2h, $data) {
            $unit = VendorSubmissionUnit::lockForUpdate()->findOrFail($p2h->vendor_submission_unit_id);
            $latest = $unit->arrivalP2hs()->first();
            if (! $latest || $latest->id !== $p2h->id || $latest->status !== 'PENDING') {
                throw ValidationException::withMessages(['status' => 'Hanya revisi P2H kedatangan terbaru yang masih menunggu persetujuan dapat diperiksa.']);
            }
            $latest->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'REJECTED' ? $data['reason'] : null,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', $data['status'] === 'APPROVED' ? 'P2H kedatangan disetujui. Form kedatangan dapat diisi.' : 'P2H kedatangan ditolak untuk diperbaiki.');
    }

    public function download(Request $request, ArrivalP2h $p2h)
    {
        $unit = $p2h->unit;
        $permit = $unit->submission;
        $user = $request->user();
        $reviewer = in_array($user->role, [UserRole::COORDINATOR, UserRole::DEVELOPER], true) && $user->hasPermission('coordinator.expedition');
        $reporter = ($user->role === UserRole::VENDOR && $user->hasPermission('vendor.submission'))
            || ($user->role === UserRole::EXPEDITION && $user->hasPermission('expedition.report'));
        abort_unless($permit->site === $user->site && ($reviewer || ($reporter && app(ArrivalPlanningService::class)->permits($user)->whereKey($permit->id)->exists())), 403);
        $p2h->load('reviewer');
        $unit->load('latestArrivalReport');
        $pdf = app(P2hPdf::class)->render($permit, $unit, $p2h);
        $filename = 'P2H-Kedatangan-'.preg_replace('/[^A-Za-z0-9-]/', '-', $p2h->vehicle_plate).'-R'.$p2h->revision.'.pdf';

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }
}
