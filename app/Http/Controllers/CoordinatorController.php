<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Events\VendorSubmissionStatusChanged;
use App\Http\Requests\UpdateSubmissionStatusRequest;
use App\Models\ExpeditionReport;
use App\Models\VendorSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('Coordinator/Index', [
            'reports' => $this->expeditionQuery($r)
                ->orderBy($this->sortColumn($r, ['arrived_at', 'expedition_company', 'driver_name', 'driver_phone', 'vehicle_plate', 'vehicle_type', 'sender_vendor', 'destination'], 'arrived_at'), $this->sortDirection($r))
                ->paginate($this->perPage($r))->withQueryString(),
            'summary' => $summary,
            'hourly' => $hourly,
            'options' => $options,
            'filters' => $r->only(['search', 'date_from', 'date_to', 'company', 'vehicle_type', 'vendor', 'destination', 'sort', 'direction', 'per_page']),
        ]);
    }

    private function expeditionQuery(Request $r)
    {
        return ExpeditionReport::where('site', $r->user()->site)->when($r->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('driver_name', 'like', '%'.$v.'%')->orWhere('vehicle_plate', 'like', '%'.$v.'%')->orWhere('expedition_company', 'like', '%'.$v.'%')->orWhere('sender_vendor', 'like', '%'.$v.'%')->orWhere('destination', 'like', '%'.$v.'%')))->when($r->date_from, fn ($q, $v) => $q->whereDate('arrived_at', '>=', $v))->when($r->date_to, fn ($q, $v) => $q->whereDate('arrived_at', '<=', $v))->when($r->company, fn ($q, $v) => $q->where('expedition_company', $v))->when($r->vehicle_type, fn ($q, $v) => $q->where('vehicle_type', $v))->when($r->vendor, fn ($q, $v) => $q->where('sender_vendor', $v))->when($r->destination, fn ($q, $v) => $q->where('destination', $v));
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
        $q = (clone $base)->withCount('documents')->when($r->search, fn ($x, $v) => $x->where(fn ($y) => $y->where('vendor_name', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')->orWhere('submission_number', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%')))->when($r->status, fn ($x, $v) => $x->where('status', $v))->when($r->date_from, fn ($x, $v) => $x->whereDate('submitted_at', '>=', $v))->when($r->date_to, fn ($x, $v) => $x->whereDate('submitted_at', '<=', $v));
        $stats = ['total' => (clone $base)->count(), 'submitted' => (clone $base)->where('status', 'SUBMITTED')->count(), 'approved' => (clone $base)->where('status', 'APPROVED')->count(), 'revision' => (clone $base)->where('status', 'REVISION')->count()];
        $sort = $this->sortColumn($r, ['submitted_at', 'submission_number', 'vendor_name', 'applicant_name', 'phone', 'status', 'released_at'], 'submitted_at');

        return Inertia::render('Coordinator/Submissions', ['type' => $type->value, 'items' => $q->orderBy($sort, $this->sortDirection($r))->paginate($this->perPage($r))->withQueryString(), 'stats' => $stats, 'filters' => $r->only(['search', 'status', 'date_from', 'date_to', 'sort', 'direction', 'per_page'])]);
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

        return Inertia::render('Coordinator/Show', ['submission' => $submission->load('documents', 'creator')]);
    }

    public function updateStatus(UpdateSubmissionStatusRequest $r, VendorSubmission $submission)
    {
        abort_unless($submission->site === $r->user()->site, 403);
        $next = SubmissionStatus::from($r->status);
        abort_unless(($submission->status === SubmissionStatus::SUBMITTED && in_array($next, [SubmissionStatus::APPROVED, SubmissionStatus::REVISION, SubmissionStatus::REJECTED], true)) || ($submission->status === SubmissionStatus::APPROVED && $next === SubmissionStatus::RELEASED), 422);
        DB::transaction(function () use ($r, $submission, $next) {
            $submission->update(['status' => $next, 'coordinator_note' => $r->coordinator_note, 'reviewed_at' => now(), 'reviewed_by' => $r->user()->id, 'released_at' => $next === SubmissionStatus::RELEASED ? now() : $submission->released_at]);
        });
        VendorSubmissionStatusChanged::dispatch($submission->fresh());

        return back()->with('success', 'Status pengajuan berhasil diperbarui.');
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
