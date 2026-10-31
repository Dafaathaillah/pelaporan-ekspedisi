<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionEscortUpdated;
use App\Models\EscortGoNoGo;
use App\Models\ExpeditionReport;
use App\Services\EscortGoNoGoService;
use App\Services\EscortReadiness;
use App\Services\PrivatePhotoResizer;
use App\Support\GoNoGoChecklist;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DriverEscortController extends Controller
{
    public function index(Request $request)
    {
        $items = ExpeditionReport::query()
            ->with(['escortDriver:id,name,hull_number', 'escortCloser:id,name,hull_number', 'internalIjinLintas:id,title,escort_date', 'goNoGo:id,submitted_by,status,driver_decision,started_at'])
            ->where('site', $request->user()->site)
            ->where(fn ($query) => $query
                ->whereIn('escort_status', ['WAITING', 'PROCESS'])
                ->orWhere('escort_driver_id', $request->user()->id)
                ->orWhere('escort_closed_by', $request->user()->id))
            ->latest('arrived_at')->get();

        return Inertia::render('Driver/Escorts', ['items' => $items, 'goNoGoChecklist' => GoNoGoChecklist::ITEMS, 'goNoGoForms' => EscortGoNoGo::where('site', $request->user()->site)->where('submitted_by', $request->user()->id)->latest('submitted_at')->paginate(10, ['*'], 'gng_page')->withQueryString()]);
    }

    public function claim(Request $request, ExpeditionReport $report)
    {
        $request->validate(['go_no_go_id' => ['nullable', 'uuid']]);
        DB::transaction(function () use ($request, $report) {
            $item = ExpeditionReport::lockForUpdate()->findOrFail($report->id);
            abort_unless($item->site === $request->user()->site, 403);
            app(EscortReadiness::class)->ensure($item);
            abort_if($item->escort_status !== 'WAITING' || $item->escort_driver_id, 422, 'Unit ekspedisi sudah diambil driver escort lain.');
            $form = app(EscortGoNoGoService::class)->approvedForStart($request->user(), collect([$item]), $request->input('go_no_go_id'));
            $item->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $request->user()->id, 'escort_started_at' => now(), 'p5m_photo_path' => $form->p5m_photo_path]);
            $form->update(['started_at' => $item->escort_started_at]);
        });
        SafeBroadcast::dispatch(new ExpeditionEscortUpdated($report->fresh()));

        return back()->with('success', 'Escort dimulai berdasarkan GO/NO GO yang disetujui.');
    }

    public function claimBatch(Request $request)
    {
        $data = $request->validate(['report_ids' => ['required', 'array', 'min:1', 'max:100'], 'report_ids.*' => ['required', 'uuid', 'distinct'], 'go_no_go_id' => ['nullable', 'uuid']]);
        $ids = collect($data['report_ids'])->unique()->sort()->values();
        $batchId = (string) Str::uuid();
        DB::transaction(function () use ($request, $ids, $batchId) {
            $items = app(EscortGoNoGoService::class)->lockedReports($ids->all());
            abort_if($items->contains(fn ($item) => $item->site !== $request->user()->site), 403);
            $items->each(fn ($item) => app(EscortReadiness::class)->ensure($item));
            abort_if($items->contains(fn ($item) => $item->escort_status !== 'WAITING' || $item->escort_driver_id), 422, 'Salah satu unit sudah diambil driver escort lain. Muat ulang daftar kendaraan.');
            $form = app(EscortGoNoGoService::class)->approvedForStart($request->user(), $items, $request->input('go_no_go_id'));
            $startedAt = now();
            foreach ($items as $item) {
                $item->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $request->user()->id, 'escort_batch_id' => $batchId, 'escort_started_at' => $startedAt, 'p5m_photo_path' => $form->p5m_photo_path]);
            }
            $form->update(['started_at' => $startedAt]);
        });
        ExpeditionReport::whereIn('id', $ids)->get()->each(fn ($item) => SafeBroadcast::dispatch(new ExpeditionEscortUpdated($item)));

        return back()->with('success', $ids->count().' unit berhasil dimulai berdasarkan GO/NO GO.');
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
}
