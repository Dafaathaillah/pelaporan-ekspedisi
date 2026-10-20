<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionRequestCreated;
use App\Events\ExpeditionRequestUpdated;
use App\Models\ExpeditionRequest;
use App\Models\VendorCompany;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExpeditionRequestController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->user()->role->value;
        $isPurchasing = $role === 'purchasing_admin';
        $perPage = in_array($request->integer('per_page'), [10, 25, 50, 100], true) ? $request->integer('per_page') : 10;
        $sort = in_array($request->string('sort')->toString(), ['created_at', 'request_number', 'expedition_type', 'route_from', 'route_to', 'status'], true)
            ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $items = ExpeditionRequest::query()->with('vendorCompany:id,name,abbreviation')->withCount('submissions')
            ->where('site', $request->user()->site)
            ->when($isPurchasing, fn ($query) => $query->where('created_by', $request->user()->id))
            ->when($request->search, fn ($query, $value) => $query->where(fn ($sub) => $sub
                ->where('request_number', 'like', '%'.$value.'%')->orWhere('route_from', 'like', '%'.$value.'%')
                ->orWhere('route_to', 'like', '%'.$value.'%')->orWhereHas('vendorCompany', fn ($vendor) => $vendor->where('name', 'like', '%'.$value.'%'))))
            ->when($request->status, fn ($query, $value) => $query->where('status', $value))
            ->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Purchasing/Requests', [
            'items' => $items,
            'vendors' => VendorCompany::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'filters' => $request->only(['search', 'status', 'sort', 'direction', 'per_page']),
            'canCreate' => in_array($role, ['purchasing_admin', 'developer'], true),
            'pageRole' => $role,
            'endpoint' => $isPurchasing ? '/purchasing/requests' : '/developer/expedition-requests',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_company_id' => ['required', 'integer', 'exists:vendor_companies,id'],
            'expedition_type' => ['required', 'in:FUSO,COLT_DIESEL,TRAILER,DUMP_TRUCK,PICKUP,LIGHT_VEHICLE,LAINNYA'],
            'route_from' => ['required', 'string', 'max:200'],
            'route_to' => ['required', 'string', 'max:200', 'different:route_from'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $now = now();
        $number = 'REQ-EXP-'.$now->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        $item = ExpeditionRequest::create([...$data,
            'request_number' => $number,
            'status' => 'OPEN',
            'created_by' => $request->user()->id,
            'site' => $request->user()->site,
        ]);
        SafeBroadcast::dispatch(new ExpeditionRequestCreated($item->load('vendorCompany')));

        return back()->with('success', 'Request ekspedisi '.$number.' berhasil dikirim ke vendor tujuan.');
    }

    public function updateProcurement(Request $request, ExpeditionRequest $expeditionRequest)
    {
        abort_unless($expeditionRequest->site === $request->user()->site, 403);
        $data = $request->validate([
            'pr_number' => ['nullable', 'string', 'max:100'],
            'po_number' => ['nullable', 'string', 'max:100'],
        ]);
        $expeditionRequest->update([
            'pr_number' => filled($data['pr_number'] ?? null) ? trim($data['pr_number']) : null,
            'po_number' => filled($data['po_number'] ?? null) ? trim($data['po_number']) : null,
        ]);
        SafeBroadcast::dispatch(new ExpeditionRequestUpdated($expeditionRequest->fresh('vendorCompany')));

        return back()->with('success', 'Nomor PR dan PO berhasil diperbarui.');
    }

    public function updateReadiness(Request $request, ExpeditionRequest $expeditionRequest)
    {
        $user = $request->user();
        abort_unless($user->vendor_company_id && $expeditionRequest->site === $user->site && $expeditionRequest->vendor_company_id === $user->vendor_company_id, 403);
        if ($expeditionRequest->status !== 'OPEN') {
            throw ValidationException::withMessages(['readiness_status' => 'Request yang sudah diproses tidak dapat diubah.']);
        }
        $data = $request->validate([
            'readiness_status' => ['required', Rule::in(['READY', 'NOT_READY'])],
            'readiness_reason' => ['required_if:readiness_status,NOT_READY', 'nullable', 'string', 'max:1000'],
        ]);
        $expeditionRequest->update([
            'readiness_status' => $data['readiness_status'],
            'readiness_reason' => $data['readiness_status'] === 'NOT_READY' ? $data['readiness_reason'] : null,
            'readiness_by' => $user->id,
            'readiness_at' => now(),
        ]);
        SafeBroadcast::dispatch(new ExpeditionRequestUpdated($expeditionRequest->fresh('vendorCompany')));

        return back()->with('success', 'Kesiapan unit untuk request berhasil diperbarui.');
    }

    public function evidence(Request $request, ExpeditionRequest $expeditionRequest)
    {
        $user = $request->user();
        $allowed = ($user->role->value === 'purchasing_admin' && $expeditionRequest->created_by === $user->id)
            || (in_array($user->role->value, ['developer', 'coordinator'], true) && $expeditionRequest->site === $user->site)
            || ($user->role->value === 'vendor' && $user->vendor_company_id === $expeditionRequest->vendor_company_id);
        abort_unless($allowed && Storage::disk('local')->exists($expeditionRequest->evidence_path), 404);

        return Storage::disk('local')->response($expeditionRequest->evidence_path, $expeditionRequest->evidence_original_name);
    }
}
