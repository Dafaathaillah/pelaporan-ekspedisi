<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\DepartureP2h;
use App\Models\VendorSubmissionUnit;
use App\Services\P2hPdf;
use App\Support\P2hChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartureP2hController extends Controller
{
    private function expeditionUnit(Request $request, VendorSubmissionUnit $unit): void
    {
        $permit = $unit->submission;
        abort_unless($permit->site === $request->user()->site && $permit->type->value === 'IJIN_LINTAS', 403);
        abort_unless($request->user()->vendor_company_id && $permit->creator?->vendor_company_id === $request->user()->vendor_company_id, 403);
        if ($permit->status->value !== 'RELEASED' || ! $permit->release_expired_at || $permit->release_expired_at->isPast()) {
            throw ValidationException::withMessages(['departure' => 'Izin lintas harus Released dan masih berlaku.']);
        }
    }

    public function store(Request $request, VendorSubmissionUnit $unit)
    {
        $this->expeditionUnit($request, $unit);
        $data = $request->validate([
            'items' => ['required', 'array', 'size:'.count(P2hChecklist::ITEMS)],
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
            $latest = $unit->departureP2hs()->first();
            if ($latest && $latest->status !== 'REJECTED') {
                throw ValidationException::withMessages(['departure' => 'P2H sedang menunggu vendor atau sudah disetujui.']);
            }
            if (! $unit->driver_name || ! $unit->vehicle_type || ! $unit->plate_number) {
                throw ValidationException::withMessages(['departure' => 'Identitas driver dan kendaraan pada izin belum lengkap.']);
            }
            DepartureP2h::create([...$data, 'vendor_submission_unit_id' => $unit->id, 'revision' => ($latest?->revision ?? 0) + 1, 'submitted_by' => $request->user()->id, 'driver_name' => $unit->driver_name, 'vehicle_plate' => $unit->plate_number, 'vehicle_type' => $unit->vehicle_type, 'status' => 'PENDING']);
        });

        return back()->with('success', 'Form P2H dikirim ke vendor untuk persetujuan.');
    }

    public function review(Request $request, VendorSubmissionUnit $unit)
    {
        abort_unless($unit->submission->site === $request->user()->site && $unit->submission->created_by === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'reason' => ['required_if:status,REJECTED', 'nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($unit, $request, $data) {
            $unit = VendorSubmissionUnit::lockForUpdate()->findOrFail($unit->id);
            $p2h = $unit->departureP2hs()->first();
            if (! $p2h || $p2h->status !== 'PENDING') {
                throw ValidationException::withMessages(['status' => 'Tidak ada P2H yang menunggu persetujuan.']);
            }
            $p2h->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'REJECTED' ? $data['reason'] : null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', $data['status'] === 'APPROVED' ? 'P2H disetujui.' : 'P2H ditolak.');
    }

    public function confirm(Request $request, VendorSubmissionUnit $unit)
    {
        $this->expeditionUnit($request, $unit);
        DB::transaction(function () use ($unit) {
            $unit = VendorSubmissionUnit::lockForUpdate()->findOrFail($unit->id);
            $p2h = $unit->departureP2hs()->first();
            if (! $p2h || $p2h->status !== 'APPROVED') {
                throw ValidationException::withMessages(['departure' => 'P2H harus disetujui vendor sebelum keberangkatan.']);
            }
            if ($p2h->departed_at) {
                throw ValidationException::withMessages(['departure' => 'Keberangkatan unit sudah dikonfirmasi.']);
            }
            $p2h->update(['departed_at' => now()]);
        });

        return back()->with('success', 'Keberangkatan unit dikonfirmasi.');
    }

    public function download(Request $request, VendorSubmissionUnit $unit)
    {
        $user = $request->user();
        $submission = $unit->submission;
        abort_unless($submission->site === $user->site, 403);
        $reviewer = in_array($user->role, [UserRole::COORDINATOR, UserRole::DEVELOPER], true)
            && ($user->hasPermission('coordinator.vendor') || $user->hasPermission('coordinator.planning') || $user->hasPermission('coordinator.expedition'));
        $purchasing = $user->role === UserRole::PURCHASING_ADMIN && $user->hasPermission('purchasing.requests');
        $expedition = $user->role === UserRole::EXPEDITION && $user->vendor_company_id && $submission->creator?->vendor_company_id === $user->vendor_company_id;
        abort_unless($reviewer || $purchasing || $expedition || $submission->created_by === $user->id, 403);
        $p2h = $unit->departureP2hs()->with('reviewer')->firstOrFail();
        $unit->load('latestArrivalReport');
        $pdf = app(P2hPdf::class)->render($submission, $unit, $p2h);
        $filename = 'P2H-'.preg_replace('/[^A-Za-z0-9-]/', '-', $unit->plate_number).'-R'.$p2h->revision.'.pdf';

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }
}
