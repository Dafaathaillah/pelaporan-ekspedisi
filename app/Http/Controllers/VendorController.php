<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Events\VendorSubmissionCreated;
use App\Http\Requests\StoreVendorSubmissionRequest;
use App\Models\VendorSubmission;
use App\Services\VendorDocumentRequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    // Vendor submissions are site-scoped.
    public function index(Request $request, VendorDocumentRequirementService $service): Response
    {
        $u = $request->user();
        $allowedSort = ['submitted_at', 'submission_number', 'type', 'vendor_name', 'applicant_name', 'status', 'released_at'];
        $sort = in_array($request->sort, $allowedSort, true) ? $request->sort : 'submitted_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;
        $items = VendorSubmission::withCount('documents')->where('created_by', $u->id)->where('site', $u->site)
            ->when($request->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('submission_number', 'like', '%'.$v.'%')->orWhere('vendor_name', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')))
            ->when($request->history_type, fn ($q, $v) => $q->where('type', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Vendor/Index', ['requirements' => $service->all(), 'submissions' => $items, 'tableFilters' => $request->only(['search', 'history_type', 'status', 'sort', 'direction', 'per_page'])]);
    }

    public function show(VendorSubmission $submission): Response
    {
        $u = request()->user();
        abort_unless($submission->created_by === $u->id && $submission->site === $u->site, 403);

        return Inertia::render('Vendor/Show', ['submission' => $submission->load('documents')]);
    }

    public function store(StoreVendorSubmissionRequest $request, VendorDocumentRequirementService $service): RedirectResponse
    {
        $reqs = collect($service->for($request->type))->keyBy('code');
        $files = collect($request->file('documents', []))->filter();
        foreach ($reqs->where('required', true) as $r) {
            if (! $files->has($r['code'])) {
                throw ValidationException::withMessages(['documents.'.$r['code'] => $r['name'].' wajib diunggah.']);
            }
        }
        if ($files->sum(fn ($f) => $f->getSize()) > 18874368) {
            throw ValidationException::withMessages(['documents' => 'Total seluruh dokumen maksimal 18 MB.']);
        }
        $submission = DB::transaction(fn () => $this->persist($request, $reqs, $files));
        VendorSubmissionCreated::dispatch($submission);

        return back()->with('success', 'Pengajuan berhasil dikirim.');
    }

    private function persist($request, $reqs, $files): VendorSubmission
    {
        $now = now();
        $s = VendorSubmission::create([...$request->safe()->except('documents'), 'submission_number' => 'VND-'.$now->format('Ymd-His').'-'.random_int(100, 999), 'status' => SubmissionStatus::SUBMITTED, 'submitted_at' => $now, 'created_by' => $request->user()->id, 'site' => $request->user()->site]);
        foreach ($files as $code => $file) {
            $r = $reqs[$code] ?? null;
            if (! $r) {
                continue;
            } $path = $file->store('private/vendor-documents/'.$s->id, 'local');
            $s->documents()->create(['document_code' => $code, 'document_name' => $r['name'], 'file_name' => basename($path), 'original_name' => $file->getClientOriginalName(), 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => $file->getSize(), 'is_required' => $r['required'], 'uploaded_at' => $now]);
        }

        return $s;
    }
}
