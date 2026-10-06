<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionEscortUpdated;
use App\Models\ExpeditionReport;
use App\Services\PrivatePhotoResizer;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DriverEscortController extends Controller
{
    public function index(Request $request)
    {
        $items = ExpeditionReport::query()
            ->with(['escortDriver:id,name,hull_number', 'escortCloser:id,name,hull_number', 'internalIjinLintas:id,title,escort_date'])
            ->where('site', $request->user()->site)
            ->where(fn ($query) => $query
                ->whereIn('escort_status', ['WAITING', 'PROCESS'])
                ->orWhere('escort_driver_id', $request->user()->id)
                ->orWhere('escort_closed_by', $request->user()->id))
            ->latest('arrived_at')->get();

        return Inertia::render('Driver/Escorts', ['items' => $items]);
    }

    public function claim(Request $request, ExpeditionReport $report, PrivatePhotoResizer $photoResizer)
    {
        $request->validate([
            'p5m_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'p5m_photo.required' => 'Foto P5M wajib diambil sebelum memulai escort.',
        ]);
        DB::transaction(function () use ($request, $report, $photoResizer) {
            $item = ExpeditionReport::lockForUpdate()->findOrFail($report->id);
            abort_unless($item->site === $request->user()->site, 403);
            $this->ensureP2hAndWaitingPeriodAreComplete($item);
            $this->ensureInternalEscortIsScheduledToday($item);
            $this->ensureExpeditionIsReady($item);
            abort_if($item->escort_status !== 'WAITING' || $item->escort_driver_id, 422, 'Unit ekspedisi sudah diambil driver escort lain.');
            $path = $photoResizer->store($request->file('p5m_photo'), 'private/expedition-reports/'.$item->report_number, 'p5m');
            try {
                $item->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $request->user()->id, 'escort_started_at' => now(), 'p5m_photo_path' => $path]);
            } catch (\Throwable $error) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                }
                throw $error;
            }
        });
        SafeBroadcast::dispatch(new ExpeditionEscortUpdated($report->fresh()));

        return back()->with('success', 'Escort dimulai. Selesaikan tugas dan lakukan close escort di lokasi tujuan.');
    }

    public function claimBatch(Request $request, PrivatePhotoResizer $photoResizer)
    {
        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1'],
            'report_ids.*' => ['required', 'uuid', 'distinct'],
            'p5m_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'report_ids.required' => 'Pilih minimal satu unit yang akan di-escort.',
            'report_ids.min' => 'Pilih minimal satu unit yang akan di-escort.',
            'p5m_photo.required' => 'Foto P5M wajib diambil sebelum memulai escort.',
        ]);

        $ids = collect($data['report_ids'])->unique()->sort()->values();
        $batchId = (string) Str::uuid();
        $path = null;
        $updatedIds = [];

        try {
            DB::transaction(function () use ($request, $ids, $batchId, $photoResizer, &$path, &$updatedIds) {
                $items = ExpeditionReport::query()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                abort_if($items->count() !== $ids->count(), 422, 'Salah satu unit tidak ditemukan. Muat ulang daftar kendaraan.');
                abort_if($items->contains(fn ($item) => $item->site !== $request->user()->site), 403);
                $items->each(fn ($item) => $this->ensureP2hAndWaitingPeriodAreComplete($item));
                $items->each(fn ($item) => $this->ensureInternalEscortIsScheduledToday($item));
                $items->each(fn ($item) => $this->ensureExpeditionIsReady($item));
                abort_if($items->contains(fn ($item) => $item->escort_status !== 'WAITING' || $item->escort_driver_id), 422, 'Salah satu unit sudah diambil driver escort lain. Muat ulang daftar kendaraan.');

                $path = $photoResizer->store($request->file('p5m_photo'), 'private/expedition-reports/escort-batches/'.$batchId, 'p5m');
                $startedAt = now();

                foreach ($items as $item) {
                    $item->update([
                        'escort_status' => 'PROCESS',
                        'escort_driver_id' => $request->user()->id,
                        'escort_batch_id' => $batchId,
                        'escort_started_at' => $startedAt,
                        'p5m_photo_path' => $path,
                    ]);
                    $updatedIds[] = $item->id;
                }
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        ExpeditionReport::whereIn('id', $updatedIds)->get()->each(
            fn ($item) => SafeBroadcast::dispatch(new ExpeditionEscortUpdated($item)),
        );

        return back()->with('success', $ids->count().' unit berhasil dimulai dalam satu batch escort.');
    }

    public function close(Request $request, ExpeditionReport $report, PrivatePhotoResizer $photoResizer)
    {
        abort_unless($report->site === $request->user()->site && $report->escort_status === 'PROCESS', 403);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100'],
            'location_name' => ['nullable', 'string', 'max:1000'],
            'captured_at' => ['required', 'date'],
            'escort_close_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'accuracy.max' => 'Akurasi lokasi close escort wajib di bawah atau sama dengan ±100 meter. Aktifkan GPS dan coba kembali.',
        ]);
        $path = $photoResizer->store($request->file('escort_close_photo'), 'private/expedition-reports/'.$report->report_number, 'escort_close');
        try {
            DB::transaction(function () use ($request, $report, $data, $path) {
                $item = ExpeditionReport::lockForUpdate()->findOrFail($report->id);
                abort_unless($item->site === $request->user()->site && $item->escort_status === 'PROCESS', 422, 'Escort sudah ditutup driver lain.');
                $item->update([
                    'escort_status' => 'CLOSED', 'escort_closed_at' => now(), 'escort_closed_by' => $request->user()->id,
                    'escort_close_photo_path' => $path, 'escort_close_latitude' => $data['latitude'],
                    'escort_close_longitude' => $data['longitude'], 'escort_close_location_accuracy' => $data['accuracy'],
                    'escort_close_location_name' => $data['location_name'] ?? null, 'escort_close_photo_captured_at' => $data['captured_at'],
                ]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        SafeBroadcast::dispatch(new ExpeditionEscortUpdated($report->fresh()));

        return back()->with('success', 'Escort berhasil ditutup. Koordinat dan identitas driver penutup telah tersimpan.');
    }

    public function closeBatch(Request $request, ExpeditionReport $report, PrivatePhotoResizer $photoResizer)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100'],
            'location_name' => ['nullable', 'string', 'max:1000'],
            'captured_at' => ['required', 'date'],
            'escort_close_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'accuracy.max' => 'Akurasi lokasi close escort wajib di bawah atau sama dengan ±100 meter. Aktifkan GPS dan coba kembali.',
        ]);

        $closedIds = [];
        $path = $photoResizer->store($request->file('escort_close_photo'), 'private/expedition-reports/escort-closing/'.($report->escort_batch_id ?: $report->id), 'escort_close');
        try {
            DB::transaction(function () use ($request, $report, $data, $path, &$closedIds) {
                $items = ExpeditionReport::query()
                    ->when(
                        $report->escort_batch_id,
                        fn ($query, $batchId) => $query->where('escort_batch_id', $batchId),
                        fn ($query) => $query->whereKey($report->id),
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                abort_if($items->isEmpty(), 422, 'Batch escort tidak ditemukan.');
                abort_if($items->contains(fn ($item) => $item->site !== $request->user()->site), 403);
                abort_if($items->contains(fn ($item) => $item->escort_status !== 'PROCESS'), 422, 'Salah satu unit dalam batch sudah tidak aktif. Muat ulang daftar escort.');

                $closedAt = now();
                foreach ($items as $item) {
                    $item->update([
                        'escort_status' => 'CLOSED',
                        'escort_closed_at' => $closedAt,
                        'escort_closed_by' => $request->user()->id,
                        'escort_close_photo_path' => $path,
                        'escort_close_latitude' => $data['latitude'],
                        'escort_close_longitude' => $data['longitude'],
                        'escort_close_location_accuracy' => $data['accuracy'],
                        'escort_close_location_name' => $data['location_name'] ?? null,
                        'escort_close_photo_captured_at' => $data['captured_at'],
                    ]);
                    $closedIds[] = $item->id;
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        ExpeditionReport::whereIn('id', $closedIds)->get()->each(
            fn ($item) => SafeBroadcast::dispatch(new ExpeditionEscortUpdated($item)),
        );

        return back()->with('success', count($closedIds).' unit dalam batch escort berhasil ditutup.');
    }

    public function photo(Request $request, ExpeditionReport $report)
    {
        $allowed = in_array($request->user()->id, [$report->escort_driver_id, $report->escort_closed_by], true)
            || (in_array($request->user()->role->value, ['developer', 'coordinator'], true) && $report->site === $request->user()->site);
        abort_unless($allowed && $report->escort_close_photo_path && Storage::disk('local')->exists($report->escort_close_photo_path), 404);

        return Storage::disk('local')->response($report->escort_close_photo_path);
    }

    public function p5mPhoto(Request $request, ExpeditionReport $report)
    {
        $allowed = $report->site === $request->user()->site && (
            in_array($request->user()->id, [$report->escort_driver_id, $report->escort_closed_by], true)
            || in_array($request->user()->role->value, ['developer', 'coordinator'], true)
        );
        abort_unless($allowed && $report->p5m_photo_path && Storage::disk('local')->exists($report->p5m_photo_path), 404);

        $extension = pathinfo($report->p5m_photo_path, PATHINFO_EXTENSION) ?: 'jpg';
        $reportName = str($report->report_number)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->value();
        $driverName = str($report->escortDriver?->name ?? 'Driver')->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->value();
        $downloadName = "P5M-{$reportName}-{$driverName}.{$extension}";

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($report->p5m_photo_path, $downloadName);
        }

        return Storage::disk('local')->response(
            $report->p5m_photo_path,
            basename($report->p5m_photo_path),
            ['Content-Disposition' => 'inline; filename='.basename($report->p5m_photo_path)],
        );
    }

    public function reverseGeocode(Request $request)
    {
        $coordinates = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $key = 'escort-geocode:'.round((float) $coordinates['latitude'], 5).':'.round((float) $coordinates['longitude'], 5);
        $name = Cache::remember($key, now()->addDays(30), function () use ($coordinates) {
            $response = Http::timeout(8)->withHeaders(['User-Agent' => 'PPA-Pelaporan-Ekspedisi/1.0 (ICT Site BIB)'])->get('https://nominatim.openstreetmap.org/reverse', ['format' => 'jsonv2', 'lat' => $coordinates['latitude'], 'lon' => $coordinates['longitude'], 'zoom' => 16, 'accept-language' => 'id']);

            return $response->successful() ? $response->json('display_name') : null;
        });

        return response()->json(['display_name' => $name]);
    }

    private function ensureInternalEscortIsScheduledToday(ExpeditionReport $report): void
    {
        if (! $report->internal_ijin_lintas_id) {
            return;
        }

        $escortDate = $report->internalIjinLintas()->value('escort_date');
        if (! $escortDate || ! Carbon::parse($escortDate)->isToday()) {
            throw ValidationException::withMessages([
                'escort_date' => 'Izin internal hanya dapat di-escort tepat pada tanggal yang dijadwalkan.',
            ]);
        }
    }

    private function ensureP2hAndWaitingPeriodAreComplete(ExpeditionReport $report): void
    {
        if ($report->p2h_photo_path && $report->p2h_status !== 'APPROVED') {
            throw ValidationException::withMessages([
                'p2h_status' => 'Unit belum dapat di-escort karena P2H belum disetujui coordinator.',
            ]);
        }
    }

    private function ensureExpeditionIsReady(ExpeditionReport $report): void
    {
        if ($report->internal_ijin_lintas_id || $report->is_ready_for_escort) {
            return;
        }

        throw ValidationException::withMessages([
            'ready_escort_at' => 'Unit belum ready escort. Dapat mulai escort pada '.$report->ready_escort_at->format('d/m/Y H:i').' WITA.',
        ]);
    }
}
