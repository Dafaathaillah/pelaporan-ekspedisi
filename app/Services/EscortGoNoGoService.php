<?php

namespace App\Services;

use App\Models\EscortGoNoGo;
use App\Models\ExpeditionReport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EscortGoNoGoService
{
    public function lockedReports(array $ids): Collection
    {
        $reports = ExpeditionReport::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        abort_unless($reports->count() === count($ids), 422, 'Salah satu unit tidak ditemukan. Muat ulang daftar kendaraan.');

        return $reports;
    }

    public function submit(User $user, array $data, $photo): EscortGoNoGo
    {
        $path = null;
        try {
            return DB::transaction(function () use ($user, $data, $photo, &$path) {
                $reports = $this->lockedReports($data['report_ids']);
                abort_if($reports->contains(fn ($report) => $report->site !== $user->site), 403);
                foreach ($reports as $report) {
                    app(EscortReadiness::class)->ensure($report);
                    abort_if($report->escort_status !== 'WAITING' || $report->escort_driver_id, 422, 'Salah satu unit sudah diambil driver escort lain.');
                    if ($report->go_no_go_id) {
                        throw ValidationException::withMessages(['go_no_go' => 'Salah satu unit sudah berada dalam pengajuan GO/NO GO. Batalkan pengajuan lama atau tunggu keputusan.']);
                    }
                }
                $id = (string) Str::uuid();
                $groupKey = hash('sha256', $user->id.'|'.$reports->pluck('id')->implode(','));
                $path = app(PrivatePhotoResizer::class)->store($photo, 'private/go-no-go/'.$id, 'p5m');
                $form = EscortGoNoGo::create([
                    ...collect($data)->only(['escorter_name', 'nik', 'items', 'driver_decision', 'notes'])->all(),
                    'id' => $id, 'group_key' => $groupKey, 'revision' => (EscortGoNoGo::where('group_key', $groupKey)->max('revision') ?? 0) + 1,
                    'site' => $user->site, 'submitted_by' => $user->id, 'department' => 'SCM', 'position' => 'Driver LV', 'destination' => 'PPA-BIB',
                    'units' => $reports->map(fn ($report) => $report->only(['id', 'vehicle_plate', 'driver_name', 'report_number']))->all(),
                    'p5m_photo_path' => $path, 'status' => 'PENDING', 'submitted_at' => now(),
                ]);
                $reports->each(fn ($report) => $report->update(['go_no_go_id' => $form->id]));

                return $form;
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function review(EscortGoNoGo $form, User $user, array $data): void
    {
        DB::transaction(function () use ($form, $user, $data) {
            $reports = $this->lockedReports(array_column($form->units, 'id'));
            abort_if($reports->contains(fn ($report) => $report->site !== $user->site), 403);
            $current = EscortGoNoGo::lockForUpdate()->findOrFail($form->id);
            if ($current->status !== 'PENDING' || $reports->contains(fn ($report) => $report->go_no_go_id !== $current->id || $report->escort_status !== 'WAITING' || $report->escort_driver_id)) {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah diputuskan, dibatalkan, atau unit tidak lagi tersedia.']);
            }
            if ($data['status'] === 'APPROVED' && ! $this->identitiesMatch($current, $reports)) {
                throw ValidationException::withMessages(['status' => 'Identitas unit/driver sudah berubah. Kembalikan pengajuan untuk diperbaiki.']);
            }
            $current->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'REJECTED' ? $data['reason'] : null, 'reviewed_by' => $user->id, 'reviewer_name' => $user->name, 'reviewed_at' => now()]);
            if ($current->result !== 'GO') {
                $reports->each(fn ($report) => $report->update(['go_no_go_id' => null]));
            }
        });
    }

    public function cancel(EscortGoNoGo $form): void
    {
        DB::transaction(function () use ($form) {
            $reports = $this->lockedReports(array_column($form->units, 'id'));
            $current = EscortGoNoGo::lockForUpdate()->findOrFail($form->id);
            if ($current->started_at || ! in_array($current->status, ['PENDING', 'APPROVED'], true)) {
                throw ValidationException::withMessages(['go_no_go' => 'Pengajuan sudah dipakai mulai escort atau tidak dapat dibatalkan.']);
            }
            $current->update(['status' => 'CANCELLED']);
            $reports->filter(fn ($report) => $report->go_no_go_id === $current->id)->each(fn ($report) => $report->update(['go_no_go_id' => null]));
        });
    }

    // Caller holds all report locks. Do not delete this form-owned photo on a failed start.
    public function approvedForStart(User $user, Collection $reports, ?string $expectedId = null): EscortGoNoGo
    {
        $ids = $reports->pluck('go_no_go_id')->unique();
        $id = $ids->count() === 1 ? $ids->first() : null;
        $form = $id ? EscortGoNoGo::lockForUpdate()->find($id) : null;
        if (! $form || $form->site !== $user->site || $form->submitted_by !== $user->id || $form->result !== 'GO' || $form->started_at
            || ($expectedId && $form->id !== $expectedId)
            || collect(array_column($form->units, 'id'))->sort()->values()->all() !== $reports->pluck('id')->sort()->values()->all()
            || ! $this->identitiesMatch($form, $reports)
            || ! Storage::disk('local')->exists($form->p5m_photo_path)) {
            throw ValidationException::withMessages(['go_no_go' => 'Isi GO/NO GO dan tunggu approval Coordinator. Hanya pengaju dengan keputusan Layak + Approved yang dapat memulai seluruh unit pada form yang sama.']);
        }

        return $form;
    }

    private function identitiesMatch(EscortGoNoGo $form, Collection $reports): bool
    {
        $snapshots = collect($form->units)->keyBy('id');

        return $reports->every(fn ($report) => isset($snapshots[$report->id])
            && $snapshots[$report->id]['vehicle_plate'] === $report->vehicle_plate
            && $snapshots[$report->id]['driver_name'] === $report->driver_name);
    }
}
