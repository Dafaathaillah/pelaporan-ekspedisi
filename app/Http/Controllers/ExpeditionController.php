<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionReportCreated;
use App\Http\Requests\StoreExpeditionReportRequest;
use App\Models\ExpeditionReport;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\BloodPressureAssessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ExpeditionController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;
        $reports = ExpeditionReport::where('created_by', $request->user()->id)->where('site', $request->user()->site)
            ->latest('arrived_at')->paginate($perPage)->withQueryString();
        return Inertia::render('Expedition/Index', ['vehicleTypes' => ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya'], 'expeditionCompany' => $request->user()->name, 'reports' => $reports]);
    }

    public function store(StoreExpeditionReportRequest $request): RedirectResponse
    {
        $now = now();
        $vendorUser = User::where('site', $request->user()->site)->where('role', UserRole::VENDOR)->where('name', $request->sender_vendor)->first();
        $number = 'EXP-'.$now->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $arrivalPath = $request->file('arrival_photo')->store('private/expedition-reports/'.$number, 'local');
        $pressurePath = $request->file('blood_pressure_photo')->store('private/expedition-reports/'.$number, 'local');
        $assessment = BloodPressureAssessment::assess((int) $request->blood_pressure_systolic, (int) $request->blood_pressure_diastolic);
        $report = ExpeditionReport::create([...$request->safe()->except(['arrival_photo', 'blood_pressure_photo']), 'health_status' => $assessment['status'], 'health_status_label' => $assessment['label'], 'health_status_reason' => $assessment['reason'], 'expedition_company' => $request->user()->name, 'arrival_photo_path' => $arrivalPath, 'blood_pressure_photo_path' => $pressurePath, 'vendor_user_id' => $vendorUser?->id, 'report_number' => $number, 'arrived_at' => $now, 'created_by' => $request->user()->id, 'site' => $request->user()->site]);
        ExpeditionReportCreated::dispatch($report);

        return back()->with('success', 'Laporan kedatangan berhasil dikirim.');
    }

    public function photo(Request $request, ExpeditionReport $report, string $kind)
    {
        abort_unless($report->site === $request->user()->site, 403);
        abort_unless($request->user()->id === $report->created_by || in_array($request->user()->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true), 403);
        abort_unless(in_array($kind, ['arrival', 'blood-pressure'], true), 404);
        $path = $kind === 'arrival' ? $report->arrival_photo_path : $report->blood_pressure_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function reverseGeocode(Request $request)
    {
        $coordinates = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $key = 'reverse-geocode:'.round((float) $coordinates['latitude'], 5).':'.round((float) $coordinates['longitude'], 5);
        $location = Cache::remember($key, now()->addDays(30), function () use ($coordinates) {
            try {
                $response = Http::timeout(8)->withHeaders(['User-Agent' => 'PPA-Pelaporan-Ekspedisi/1.0 (ICT Site BIB)', 'Referer' => config('app.url')])->get('https://nominatim.openstreetmap.org/reverse', ['format' => 'jsonv2', 'lat' => $coordinates['latitude'], 'lon' => $coordinates['longitude'], 'zoom' => 16, 'addressdetails' => 1, 'accept-language' => 'id']);
                return $response->successful() ? $response->json('display_name') : null;
            } catch (\Throwable) {
                return null;
            }
        });

        return response()->json(['location_name' => $location]);
    }
}
