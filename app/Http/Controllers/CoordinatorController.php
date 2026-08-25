<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Events\VendorResubmissionRequested;
use App\Events\VendorSubmissionCreated;
use App\Events\VendorSubmissionStatusChanged;
use App\Http\Requests\UpdateSubmissionStatusRequest;
use App\Models\ExpeditionReport;
use App\Models\VendorDocument;
use App\Models\VendorSubmission;
use App\Services\PlanningService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CoordinatorController extends Controller
{
    public function index(Request $r): Response
    {
        $site = $r->user()->site;
        $today = now()->startOfDay();
        $base = ExpeditionReport::where('site', $site);
        $latest = (clone $base)->latest('arrived_at')->first(['arrived_at', 'expedition_company']);
        $summary = [
            'today' => (clone $base)->where('arrived_at', '>=', $today)->count(),
            'month' => (clone $base)->where('arrived_at', '>=', now()->startOfMonth())->count(),
            'total' => (clone $base)->count(),
            'companies' => (clone $base)->where('arrived_at', '>=', $today)->distinct()->count('expedition_company'),
            'latest' => $latest ? ['time' => $latest->arrived_at->format('H:i:s'), 'company' => $latest->expedition_company] : null,
        ];
        $hourCounts = (clone $base)->whereBetween('arrived_at', [$today, now()->endOfDay()])->get(['arrived_at'])->countBy(fn ($x) => $x->arrived_at->format('H'));
        $hourly = collect(range(0, 23))->map(fn ($hour) => ['hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT), 'total' => $hourCounts->get(str_pad((string) $hour, 2, '0', STR_PAD_LEFT), 0)]);
        $options = [
            'companies' => (clone $base)->distinct()->orderBy('expedition_company')->pluck('expedition_company'),
            'vehicleTypes' => (clone $base)->distinct()->orderBy('vehicle_type')->pluck('vehicle_type'),
            'vendors' => (clone $base)->distinct()->orderBy('sender_vendor')->pluck('sender_vendor'),
            'destinations' => (clone $base)->distinct()->orderBy('destination')->pluck('destination'),
        ];

        $reports = $this->expeditionQuery($r)
            ->orderBy($this->sortColumn($r, ['arrived_at', 'expedition_company', 'driver_name', 'driver_phone', 'vehicle_plate', 'vehicle_type', 'sender_vendor', 'destination'], 'arrived_at'), $this->sortDirection($r))
            ->paginate($this->perPage($r))->withQueryString();

        return Inertia::render('Coordinator/Index', [
            'reports' => $reports,
            'summary' => $summary,
            'hourly' => $hourly,
            'options' => $options,
            'filters' => $r->only(['search', 'date_from', 'date_to', 'company', 'vehicle_type', 'vendor', 'destination', 'sort', 'direction', 'per_page']),
        ]);
    }

    private function expeditionQuery(Request $r)
    {
        return ExpeditionReport::with('escortDriver:id,name,hull_number')->where('site', $r->user()->site)->when($r->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('driver_name', 'like', '%'.$v.'%')->orWhere('vehicle_plate', 'like', '%'.$v.'%')->orWhere('expedition_company', 'like', '%'.$v.'%')->orWhere('sender_vendor', 'like', '%'.$v.'%')->orWhere('destination', 'like', '%'.$v.'%')))->when($r->date_from, fn ($q, $v) => $q->whereDate('arrived_at', '>=', $v))->when($r->date_to, fn ($q, $v) => $q->whereDate('arrived_at', '<=', $v))->when($r->company, fn ($q, $v) => $q->where('expedition_company', $v))->when($r->vehicle_type, fn ($q, $v) => $q->where('vehicle_type', $v))->when($r->vendor, fn ($q, $v) => $q->where('sender_vendor', $v))->when($r->destination, fn ($q, $v) => $q->where('destination', $v));
    }

    public function export(Request $r)
    {
        $name = 'expedition-report-'.$r->user()->site.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Jam', 'Ekspedisi', 'Driver', 'HP', 'Nopol', 'Kendaraan', 'Vendor', 'Tujuan']);
            $this->expeditionQuery($r)->orderBy('arrived_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $x) {
                    fputcsv($out, [$x->arrived_at->format('d/m/Y'), $x->arrived_at->format('H:i:s').' WITA', $x->expedition_company, $x->driver_name, $x->driver_phone, $x->vehicle_plate, $x->vehicle_type, $x->sender_vendor, $x->destination]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function submissions(Request $r, SubmissionType $type): Response
    {
        $base = VendorSubmission::where('site', $r->user()->site)->where('type', $type);
        $q = (clone $base)->with('expeditionRequest:id,request_number,expedition_type,route_from,route_to')->withCount(['documents', 'resubmissions', 'units'])->when($r->search, fn ($x, $v) => $x->where(fn ($y) => $y->where('vendor_name', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')->orWhere('submission_number', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%')->orWhereHas('expeditionRequest', fn ($request) => $request->where('request_number', 'like', '%'.$v.'%'))))->when($r->status, fn ($x, $v) => $x->where('status', $v))->when($r->date_from, fn ($x, $v) => $x->whereDate('submitted_at', '>=', $v))->when($r->date_to, fn ($x, $v) => $x->whereDate('submitted_at', '<=', $v));
        $stats = ['total' => (clone $base)->count(), 'submitted' => (clone $base)->where('status', 'SUBMITTED')->count(), 'approved' => (clone $base)->where('status', 'APPROVED')->count(), 'revision' => (clone $base)->where('status', 'REVISION')->count()];
        $sort = $this->sortColumn($r, ['submitted_at', 'submission_number', 'vendor_name', 'applicant_name', 'phone', 'status', 'released_at'], 'submitted_at');

        $items = $q->orderBy($sort, $this->sortDirection($r))->paginate($this->perPage($r))->withQueryString();
        $items->getCollection()->load('expeditionRequest');

        return Inertia::render('Coordinator/Submissions', ['type' => $type->value, 'items' => $items, 'stats' => $stats, 'filters' => $r->only(['search', 'status', 'date_from', 'date_to', 'sort', 'direction', 'per_page'])]);
    }

    public function exportSubmissions(Request $r, SubmissionType $type)
    {
        $query = VendorSubmission::where('site', $r->user()->site)->where('type', $type)
            ->when($r->search, fn ($x, $v) => $x->where(fn ($y) => $y->where('vendor_name', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')->orWhere('submission_number', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%')))
            ->when($r->status, fn ($x, $v) => $x->where('status', $v))->when($r->date_from, fn ($x, $v) => $x->whereDate('submitted_at', '>=', $v))->when($r->date_to, fn ($x, $v) => $x->whereDate('submitted_at', '<=', $v));
        $name = 'vendor-submissions-'.$r->user()->site.'-'.strtolower($type->value).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'ID Pengajuan', 'Jenis', 'Vendor', 'Pemohon/PIC', 'Nomor HP', 'Keterangan', 'Status', 'Catatan Koordinator', 'Tanggal Release']);
            $query->orderBy('submitted_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $x) {
                    fputcsv($out, [$x->submitted_at?->format('d/m/Y H:i:s'), $x->submission_number, $x->type->value, $x->vendor_name, $x->applicant_name, $x->phone, $x->description, $x->status->value, $x->coordinator_note, $x->released_at?->format('d/m/Y H:i:s')]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function showSubmission(VendorSubmission $submission): Response
    {
        abort_unless($submission->site === request()->user()->site, 403);
        if (! $submission->coordinator_opened_at) {
            $submission->update(['coordinator_opened_at' => now()]);
        }

        return Inertia::render('Coordinator/Show', ['submission' => $submission->load('documents', 'units.documents', 'creator', 'resubmissions:id,parent_submission_id,resubmission_subject_ids', 'expeditionRequest.vendorCompany', 'expeditionRequest.creator:id,name')]);
    }

    public function updateStatus(UpdateSubmissionStatusRequest $r, VendorSubmission $submission)
    {
        abort_unless($submission->site === $r->user()->site, 403);
        $next = SubmissionStatus::from($r->status);
        abort_unless(($submission->status === SubmissionStatus::SUBMITTED && in_array($next, [SubmissionStatus::APPROVED, SubmissionStatus::REVISION, SubmissionStatus::REJECTED], true)) || ($submission->status === SubmissionStatus::APPROVED && $next === SubmissionStatus::RELEASED) || (in_array($submission->status, [SubmissionStatus::RELEASED, SubmissionStatus::REJECTED], true) && $next === SubmissionStatus::CLOSED), 422);
        DB::transaction(function () use ($r, $submission, $next) {
            $releasedAt = $next === SubmissionStatus::RELEASED ? now() : $submission->released_at;
            $submission->update(['status' => $next, 'coordinator_note' => $r->coordinator_note, 'reviewed_at' => now(), 'reviewed_by' => $r->user()->id, 'coordinator_opened_at' => $submission->coordinator_opened_at ?? now(), 'released_at' => $releasedAt, 'release_expired_at' => $next === SubmissionStatus::RELEASED ? $releasedAt->copy()->addDays(7) : $submission->release_expired_at, 'closed_at' => $next === SubmissionStatus::CLOSED ? now() : $submission->closed_at]);
        });
        SafeBroadcast::dispatch(new VendorSubmissionStatusChanged($submission->fresh()));
        app(PlanningService::class)->syncRelease($submission->fresh());

        return back()->with('success', 'Status pengajuan berhasil diperbarui.');
    }

    public function requestResubmission(Request $request, VendorSubmission $submission)
    {
        $validated = $request->validate(['mode' => 'required|in:USE_EXISTING,REQUEST_HEALTH', 'subject_ids' => 'required|array|min:1', 'subject_ids.*' => 'uuid']);
        $mode = $validated['mode'];
        $subjectIds = $validated['subject_ids'];
        abort_unless($submission->site === $request->user()->site, 403);
        abort_unless(in_array($submission->type, SubmissionType::cases(), true), 422);
        abort_unless($submission->units()->whereIn('id', $subjectIds)->count() === count(array_unique($subjectIds)), 422, 'Pilihan orang/unit tidak valid.');
        abort_unless($submission->release_expired_at && $submission->release_expired_at->isPast(), 422, 'Pengajuan masih berlaku dan belum dapat diajukan ulang.');
        abort_if($submission->resubmission_requested_at, 422, 'Masih ada permintaan dokumen yang belum diselesaikan vendor.');
        $used = $submission->resubmissions()->get()->pluck('resubmission_subject_ids')->flatten()->filter()->all();
        abort_if(array_intersect($subjectIds, $used), 422, 'Salah satu orang/unit terpilih sudah pernah diajukan ulang.');
        if ($submission->type === SubmissionType::WASKAT && $submission->submission_sequence >= 3) {
            abort(422, 'Waskat maksimal tiga kali pengajuan.');
        }
        if ($submission->type === SubmissionType::WASKAT && $submission->submission_sequence === 2 && ! $submission->documents()->whereIn('document_code', ['MINE_PERMIT_PENGAWAS_1', 'MINE_PERMIT_PENGAWAS_2'])->exists()) {
            abort(422, 'Pengajuan Waskat ketiga memerlukan permit yang sudah tersedia pada dokumen sebelumnya.');
        }
        if ($mode === 'USE_EXISTING') {
            abort_if($submission->documents()->whereIn('vendor_submission_unit_id', $subjectIds)->where('verification_status', '!=', 'VALID')->exists(), 422, 'Semua dokumen orang/unit terpilih harus divalidasi sesuai terlebih dahulu.');
            $child = DB::transaction(fn () => $this->cloneSubmission($submission, $subjectIds));
            app(PlanningService::class)->register($child);
            SafeBroadcast::dispatch(new VendorSubmissionCreated($child));

            return back()->with('success', 'Dokumen dinyatakan sesuai dan pengajuan ulang langsung dibuat.');
        }
        $submission->update(['resubmission_requested_at' => now(), 'resubmission_requested_by' => $request->user()->id, 'resubmission_subject_ids' => $subjectIds]);
        SafeBroadcast::dispatch(new VendorResubmissionRequested($submission->fresh()));

        return back()->with('success', 'Permintaan pengajuan ulang dikirim. Vendor hanya perlu memperbarui Surat Sehat.');
    }

    public function verifyDocument(Request $request, VendorDocument $document)
    {
        $data = $request->validate(['status' => 'required|in:VALID,INVALID', 'note' => 'nullable|string|max:1000']);
        $submission = $document->submission;
        abort_unless($submission->site === $request->user()->site, 403);
        $document->update(['verification_status' => $data['status'], 'verification_note' => $data['note'] ?? null, 'verified_by' => $request->user()->id, 'verified_at' => now()]);

        return back()->with('success', $data['status'] === 'VALID' ? 'Dokumen dinyatakan sesuai.' : 'Dokumen ditandai tidak sesuai.');
    }

    private function cloneSubmission(VendorSubmission $source, array $subjectIds): VendorSubmission
    {
        $source->load('units.documents');
        $now = now();
        $child = $source->replicate(['submission_number', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'coordinator_opened_at', 'released_at', 'release_expired_at', 'closed_at', 'resubmission_requested_at', 'resubmission_requested_by', 'resubmission_subject_ids']);
        $child->parent_submission_id = $source->id;
        $child->resubmission_subject_ids = $subjectIds;
        $child->submission_sequence = $source->submission_sequence + 1;
        $child->submission_number = 'VND-'.$now->format('Ymd-His').'-'.random_int(100, 999);
        $child->status = SubmissionStatus::SUBMITTED;
        $child->submitted_at = $now;
        $child->save();
        foreach ($source->units->whereIn('id', $subjectIds)->values() as $index => $oldUnit) {
            $unit = $child->units()->create(['subject_type' => $oldUnit->subject_type, 'person_name' => $oldUnit->person_name, 'plate_number' => $oldUnit->plate_number, 'unit_order' => $index + 1]);
            foreach ($oldUnit->documents as $d) {
                $path = 'private/vendor-documents/'.$child->id.'/'.$unit->id.'/'.$d->file_name;
                Storage::disk('local')->copy($d->file_path, $path);
                $unit->documents()->create($d->only(['document_code', 'document_name', 'file_name', 'original_name', 'mime_type', 'file_size', 'is_required']) + ['vendor_submission_id' => $child->id, 'file_path' => $path, 'uploaded_at' => $now]);
            }
        }

        return $child;
    }

    private function perPage(Request $request): int
    {
        $value = (int) $request->input('per_page', 25);

        return in_array($value, [10, 25, 50, 100], true) ? $value : 25;
    }

    private function sortColumn(Request $request, array $allowed, string $default): string
    {
        $sort = (string) $request->input('sort', $default);

        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    private function sortDirection(Request $request): string
    {
        return $request->input('direction') === 'asc' ? 'asc' : 'desc';
    }
}
