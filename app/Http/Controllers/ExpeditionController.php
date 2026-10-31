<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Events\ExpeditionReportCreated;
use App\Http\Requests\StoreExpeditionReportRequest;
use App\Models\ExpeditionReport;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VendorSubmissionUnit;
use App\Services\ArrivalPlanningService;
use App\Services\PrivatePhotoResizer;
use App\Support\BloodPressureAssessment;
use App\Support\P2hChecklist;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpeditionController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;
        $reports = ExpeditionReport::where('created_by', $request->user()->id)->where('site', $request->user()->site)
            ->latest('arrived_at')->paginate($perPage)->withQueryString();

        $permits = app(ArrivalPlanningService::class)->permits($request->user())->where('status', 'RELEASED')->where('release_expired_at', '>=', now())->with(['units.latestDepartureP2h', 'units.latestArrivalP2h.reviewer:id,name', 'planningItems' => fn ($q) => $q->whereNotNull('planned_date')->with('reports:id,planning_item_id,vendor_submission_unit_id,arrived_at')])->latest('released_at')->get();

        return Inertia::render('Expedition/Index', ['vehicleTypes' => ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya'], 'expeditionCompany' => $request->user()->name, 'reports' => $reports, 'permits' => $permits, 'requiresVendorCompany' => $request->user()->role === UserRole::EXPEDITION && ! $request->user()->vendor_company_id, 'p2hChecklist' => P2hChecklist::ITEMS, 'reportBase' => $request->user()->role->value === 'vendor' ? '/vendor/arrivals' : '/expedition/reports']);
    }

    public function store(StoreExpeditionReportRequest $request, PrivatePhotoResizer $photoResizer): RedirectResponse|JsonResponse
    {
        return DB::transaction(function () use ($request, $photoResizer) {
            $unit = VendorSubmissionUnit::lockForUpdate()->findOrFail($request->vendor_submission_unit_id);
            $p2h = $unit->latestArrivalP2h;
            if (! $p2h || $p2h->status !== 'APPROVED') {
                throw ValidationException::withMessages(['vendor_submission_unit_id' => 'P2H kedatangan unit harus disetujui Coordinator sebelum mengisi form kedatangan.']);
            }
            $planning = $request->planning_item_id ? PlanningItem::lockForUpdate()->findOrFail($request->planning_item_id) : null;
            if ($planning && $planning->reports()->where('vendor_submission_unit_id', $request->vendor_submission_unit_id)->exists()) {
                throw ValidationException::withMessages(['vendor_submission_unit_id' => 'Unit ini sudah dilaporkan datang pada planning tersebut.']);
            }
            $now = now();
            $vendorUser = User::where('site', $request->user()->site)->where('role', UserRole::VENDOR)->where('name', $request->sender_vendor)->first();
            if ($planning) {
                $vendorUser = $planning->submission->creator;
            }
            $number = 'EXP-'.$now->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $directory = 'private/expedition-reports/'.$number;
            try {
                $arrivalPath = $photoResizer->store($request->file('arrival_photo'), $directory, 'arrival');
                $pressurePath = $photoResizer->store($request->file('blood_pressure_photo'), $directory, 'blood_pressure');
                $assessment = BloodPressureAssessment::assess((int) $request->blood_pressure_systolic, (int) $request->blood_pressure_diastolic);
                $report = ExpeditionReport::create([...$request->safe()->except(['arrival_photo', 'blood_pressure_photo']), 'arrival_p2h_id' => $p2h->id, 'p2h_status' => 'APPROVED', 'p2h_reviewed_by' => $p2h->reviewed_by, 'p2h_reviewed_at' => $p2h->reviewed_at, 'health_status' => $assessment['status'], 'health_status_label' => $assessment['label'], 'health_status_reason' => $assessment['reason'], 'expedition_company' => $request->user()->name, 'arrival_photo_path' => $arrivalPath, 'blood_pressure_photo_path' => $pressurePath, 'vendor_user_id' => $vendorUser?->id, 'report_number' => $number, 'arrived_at' => $now, 'created_by' => $request->user()->id, 'site' => $request->user()->site]);
            } catch (\Throwable $error) {
                Storage::disk('local')->deleteDirectory($directory);
                throw $error;
            }
            SafeBroadcast::dispatch(new ExpeditionReportCreated($report));

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'report' => ['id' => $report->id, 'report_number' => $report->report_number]], 201);
            }

            return back()->with('success', 'Laporan kedatangan berhasil dikirim.');
        });
    }

    public function monitoring(Request $request): Response
    {
        $service = app(ArrivalPlanningService::class);
        $items = PlanningItem::with(['submission.units.latestDepartureP2h', 'reports:id,planning_item_id,vendor_submission_unit_id,internal_ijin_lintas_id,vehicle_plate,driver_name,vehicle_type,arrived_at,escort_started_at,escort_status'])
            ->whereIn('vendor_submission_id', $service->permits($request->user())->select('id'))
            ->whereNotNull('planned_date')->orderByDesc('planned_date')->get()
            ->map(fn ($item) => [...$item->toArray(), ...$service->progress($item)]);

        return Inertia::render('Expedition/ArrivalMonitoring', ['items' => $items, 'isVendor' => $request->user()->role->value === 'vendor']);
    }

    public function photo(Request $request, ExpeditionReport $report, string $kind)
    {
        abort_unless($report->site === $request->user()->site, 403);
        abort_unless($request->user()->id === $report->created_by || in_array($request->user()->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true), 403);
        abort_unless(in_array($kind, ['arrival', 'blood-pressure', 'p2h'], true), 404);
        $path = match ($kind) {
            'arrival' => $report->arrival_photo_path,
            'blood-pressure' => $report->blood_pressure_photo_path,
            'p2h' => $report->p2h_photo_path,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function replaceP2h(Request $request, ExpeditionReport $report, PrivatePhotoResizer $photoResizer): RedirectResponse
    {
        abort_unless($report->site === $request->user()->site && $report->created_by === $request->user()->id, 403);
        abort_unless($report->p2h_status === 'REJECTED', 422, 'Foto P2H hanya dapat diunggah ulang setelah ditolak coordinator.');
        $request->validate(['p2h_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp']]);
        $oldPath = $report->p2h_photo_path;
        $path = $photoResizer->store($request->file('p2h_photo'), 'private/expedition-reports/'.$report->report_number, 'p2h');
        try {
            $report->update(['p2h_photo_path' => $path, 'p2h_status' => 'PENDING', 'p2h_rejection_reason' => null, 'p2h_reviewed_at' => null, 'p2h_reviewed_by' => null]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', 'Foto P2H baru berhasil dikirim dan menunggu approval coordinator.');
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
