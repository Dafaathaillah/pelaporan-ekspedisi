<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionEscortUpdated;
use App\Models\ExpeditionReport;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DriverEscortController extends Controller
{
    public function index(Request $request)
    {
        $items = ExpeditionReport::query()
            ->with('escortDriver:id,name,hull_number')
            ->where('site', $request->user()->site)
            ->where(fn ($query) => $query->where('escort_status', 'WAITING')->orWhere('escort_driver_id', $request->user()->id))
            ->latest('arrived_at')->get();

        return Inertia::render('Driver/Escorts', ['items' => $items]);
    }

    public function claim(Request $request, ExpeditionReport $report)
    {
        DB::transaction(function () use ($request, $report) {
            $item = ExpeditionReport::lockForUpdate()->findOrFail($report->id);
            abort_unless($item->site === $request->user()->site, 403);
            abort_if($item->escort_status !== 'WAITING' || $item->escort_driver_id, 422, 'Unit ekspedisi sudah diambil driver escort lain.');
            $item->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $request->user()->id, 'escort_started_at' => now()]);
        });
        SafeBroadcast::dispatch(new ExpeditionEscortUpdated($report->fresh()));

        return back()->with('success', 'Escort dimulai. Selesaikan tugas dan unggah bukti close di lokasi tujuan.');
    }

    public function close(Request $request, ExpeditionReport $report)
    {
        abort_unless($report->escort_driver_id === $request->user()->id && $report->escort_status === 'PROCESS', 403);
        $data = $request->validate([
            'close_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:5'],
            'location_name' => ['nullable', 'string', 'max:1000'],
            'captured_at' => ['required', 'date'],
        ], [
            'accuracy.max' => 'Akurasi lokasi close escort wajib maksimal ±5 meter. Aktifkan GPS dan ambil ulang foto di area terbuka.',
        ]);
        $path = $data['close_photo']->store('private/escort-close/'.$report->id, 'local');
        $report->update([
            'escort_status' => 'CLOSED', 'escort_closed_at' => now(), 'escort_close_photo_path' => $path,
            'escort_close_latitude' => $data['latitude'], 'escort_close_longitude' => $data['longitude'],
            'escort_close_location_accuracy' => $data['accuracy'], 'escort_close_location_name' => $data['location_name'] ?? null,
            'escort_close_photo_captured_at' => $data['captured_at'],
        ]);
        SafeBroadcast::dispatch(new ExpeditionEscortUpdated($report->fresh()));

        return back()->with('success', 'Escort berhasil ditutup. Foto dan koordinat telah tersimpan.');
    }

    public function photo(Request $request, ExpeditionReport $report)
    {
        $allowed = $report->escort_driver_id === $request->user()->id
            || (in_array($request->user()->role->value, ['developer', 'coordinator'], true) && $report->site === $request->user()->site);
        abort_unless($allowed && $report->escort_close_photo_path && Storage::disk('local')->exists($report->escort_close_photo_path), 404);

        return Storage::disk('local')->response($report->escort_close_photo_path);
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
