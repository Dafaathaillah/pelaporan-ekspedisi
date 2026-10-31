<?php

namespace App\Services;

use App\Models\ExpeditionReport;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EscortReadiness
{
    public function ensure(ExpeditionReport $report): void
    {
        if (($report->p2h_photo_path || $report->arrival_p2h_id) && $report->p2h_status !== 'APPROVED') {
            throw ValidationException::withMessages(['p2h_status' => 'Unit belum dapat di-escort karena P2H belum disetujui coordinator.']);
        }
        if ($report->internal_ijin_lintas_id) {
            $date = $report->internalIjinLintas()->value('escort_date');
            if (! $date || ! Carbon::parse($date)->isToday()) {
                throw ValidationException::withMessages(['escort_date' => 'Izin internal hanya dapat di-escort tepat pada tanggal yang dijadwalkan.']);
            }
        } elseif (! $report->is_ready_for_escort) {
            throw ValidationException::withMessages(['ready_escort_at' => 'Unit belum ready escort. Dapat mulai escort pada '.($report->ready_escort_at?->format('d/m/Y H:i') ?? '-').' WITA.']);
        }
    }
}
