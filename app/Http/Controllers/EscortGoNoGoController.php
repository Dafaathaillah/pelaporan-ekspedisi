<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\EscortGoNoGo;
use App\Services\EscortGoNoGoService;
use App\Services\GoNoGoPdf;
use App\Support\GoNoGoChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EscortGoNoGoController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])]]);
        $forms = EscortGoNoGo::where('site', $request->user()->site)
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('escorter_name', 'like', '%'.$search.'%')->orWhere('nik', 'like', '%'.$search.'%')->orWhere('units', 'like', '%'.$search.'%')))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")->latest('submitted_at')->paginate(10)->withQueryString();

        return Inertia::render('Coordinator/GoNoGo', ['forms' => $forms, 'checklist' => GoNoGoChecklist::ITEMS, 'filters' => $data]);
    }

    public function store(Request $request, EscortGoNoGoService $service)
    {
        $data = $request->validate([
            'report_ids' => ['required', 'array', 'min:1', 'max:100'], 'report_ids.*' => ['required', 'uuid', 'distinct'],
            'escorter_name' => ['required', 'string', 'max:150'], 'nik' => ['required', 'string', 'max:50'],
            'driver_decision' => ['required', Rule::in(['LAIK', 'TIDAK_LAIK'])],
            'items' => ['required', 'array', 'size:'.count(GoNoGoChecklist::ITEMS)], 'items.*' => ['required', 'array:condition,evidence,note'],
            'items.*.condition' => ['required', Rule::in(['SESUAI', 'TIDAK_SESUAI', 'NA'])],
            'items.*.evidence' => ['nullable', 'string', 'max:300'], 'items.*.note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'], 'p5m_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp'],
        ]);
        if (array_keys($data['items']) !== range(0, count(GoNoGoChecklist::ITEMS) - 1)) {
            throw ValidationException::withMessages(['items' => 'Seluruh checklist wajib diisi berurutan.']);
        }
        $service->submit($request->user(), $data, $request->file('p5m_photo'));

        return back()->with('success', 'GO/NO GO dikirim. Tunggu persetujuan Coordinator sebelum mulai escort.');
    }

    public function review(Request $request, EscortGoNoGo $goNoGo, EscortGoNoGoService $service)
    {
        abort_unless($goNoGo->site === $request->user()->site, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'reason' => ['required_if:status,REJECTED', 'nullable', 'string', 'max:1000']]);
        $service->review($goNoGo, $request->user(), $data);

        return back()->with('success', 'Keputusan GO/NO GO disimpan.');
    }

    public function cancel(Request $request, EscortGoNoGo $goNoGo, EscortGoNoGoService $service)
    {
        abort_unless($goNoGo->submitted_by === $request->user()->id && $goNoGo->site === $request->user()->site, 403);
        $service->cancel($goNoGo);

        return back()->with('success', 'Pengajuan dibatalkan. Unit dapat dipilih kembali.');
    }

    private function authorizeRead(Request $request, EscortGoNoGo $form): void
    {
        $user = $request->user();
        $coordinator = in_array($user->role, [UserRole::COORDINATOR, UserRole::DEVELOPER], true) && $user->hasPermission('coordinator.expedition');
        $owner = $user->role === UserRole::DRIVER && $user->hasPermission('driver.escort') && $form->submitted_by === $user->id;
        abort_unless($form->site === $user->site && ($coordinator || $owner), 403);
    }

    public function photo(Request $request, EscortGoNoGo $goNoGo)
    {
        $this->authorizeRead($request, $goNoGo);
        abort_unless(Storage::disk('local')->exists($goNoGo->p5m_photo_path), 404);

        return Storage::disk('local')->response($goNoGo->p5m_photo_path);
    }

    public function download(Request $request, EscortGoNoGo $goNoGo)
    {
        $this->authorizeRead($request, $goNoGo);
        $pdf = app(GoNoGoPdf::class)->render($goNoGo);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="GO-NO-GO-'.$goNoGo->id.'-R'.$goNoGo->revision.'.pdf"']);
    }
}
