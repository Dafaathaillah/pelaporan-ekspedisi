<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Events\VendorSubmissionCreated;
use App\Http\Requests\StoreVendorSubmissionRequest;
use App\Models\VendorSubmission;
use App\Models\ExpeditionReport;
use App\Services\VendorDocumentRequirementService;
use App\Services\PlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    // Vendor submissions are site-scoped.
    public function index(Request $request, VendorDocumentRequirementService $service): Response
    {
        $u = $request->user();
        $u->load('vendorCompany');
        $allowedSort = ['submitted_at', 'submission_number', 'type', 'vendor_name', 'applicant_name', 'status', 'released_at'];
        $sort = in_array($request->sort, $allowedSort, true) ? $request->sort : 'submitted_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;
        $items = VendorSubmission::with('units:id,vendor_submission_id,plate_number,unit_order')->withCount(['documents', 'resubmissions', 'units'])->where('created_by', $u->id)->where('site', $u->site)
            ->when($request->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('submission_number', 'like', '%'.$v.'%')->orWhere('vendor_name', 'like', '%'.$v.'%')->orWhere('applicant_name', 'like', '%'.$v.'%')))
            ->when($request->history_type, fn ($q, $v) => $q->where('type', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Vendor/Index', ['requirements' => $service->all(), 'submissions' => $items, 'vendorIdentity' => $u->vendorCompany?->name ?? $u->name, 'tableFilters' => $request->only(['search', 'history_type', 'status', 'sort', 'direction', 'per_page'])]);
    }

    public function show(VendorSubmission $submission): Response
    {
        $u = request()->user();
        abort_unless($submission->created_by === $u->id && $submission->site === $u->site, 403);

        return Inertia::render('Vendor/Show', ['submission' => $submission->load('documents', 'units.documents')]);
    }

    public function monitoringSarana(Request $request): Response
    {
        $user = $request->user();
        $allowedSort = ['arrived_at', 'expedition_company', 'driver_name', 'vehicle_plate', 'vehicle_type', 'sender_vendor', 'destination'];
        $sort = in_array($request->sort, $allowedSort, true) ? $request->sort : 'arrived_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;
        $reports = ExpeditionReport::where('site', $user->site)->where('vendor_user_id', $user->id)
            ->when($request->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('report_number', 'like', '%'.$v.'%')->orWhere('driver_name', 'like', '%'.$v.'%')->orWhere('vehicle_plate', 'like', '%'.$v.'%')->orWhere('destination', 'like', '%'.$v.'%')))
            ->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Vendor/MonitoringSarana', ['reports' => $reports, 'filters' => $request->only(['search', 'sort', 'direction', 'per_page'])]);
    }

    public function store(StoreVendorSubmissionRequest $request, VendorDocumentRequirementService $service): RedirectResponse
    {
        $reqs = collect($service->for($request->type))->keyBy('code');
        if (in_array($request->type, ['VISITOR','WASKAT','PERMIT_VENDOR_NON_SIMPER','IJIN_LINTAS'], true)) return $this->storeUnits($request, $reqs);
        $files = collect($request->file('documents', []))->filter();
        $parent = $request->filled('parent_submission_id') ? VendorSubmission::with('documents')->findOrFail($request->parent_submission_id) : null;
        if ($request->type === 'IJIN_LINTAS' && ! $request->filled('service_category')) {
            throw ValidationException::withMessages(['service_category' => 'Pilih kategori Reguler atau Non-Reguler.']);
        }
        $requiredCodes = $this->requiredCodes($request, $reqs, $parent);
        foreach ($reqs->whereIn('code', $requiredCodes) as $r) {
            if (! $files->has($r['code'])) {
                throw ValidationException::withMessages(['documents.'.$r['code'] => $r['name'].' wajib diunggah.']);
            }
        }
        if ($files->sum(fn ($f) => $f->getSize()) > 18874368) {
            throw ValidationException::withMessages(['documents' => 'Total seluruh dokumen maksimal 18 MB.']);
        }
        $submission = DB::transaction(fn () => $this->persist($request, $reqs, $files, $parent));
        VendorSubmissionCreated::dispatch($submission);

        return back()->with('success', 'Pengajuan berhasil dikirim.');
    }

    private function storeUnits(StoreVendorSubmissionRequest $request, $reqs): RedirectResponse
    {
        $parent = $request->filled('parent_submission_id') ? VendorSubmission::with('units.documents')->findOrFail($request->parent_submission_id) : null;
        $units = collect($request->input('units', []));
        if ($units->isEmpty()) throw ValidationException::withMessages(['units' => 'Tambahkan minimal satu orang/unit.']);
        $isUnit=$request->type==='IJIN_LINTAS';
        if ($isUnit && ! $parent) {
            if (! in_array($request->planning_frequency, ['DAILY', 'WEEKLY', 'NON_REGULAR'], true)) throw ValidationException::withMessages(['planning_frequency' => 'Pilih frekuensi planning Ijin Lintas.']);
            $request->merge(['service_category' => $request->planning_frequency === 'NON_REGULAR' ? 'NON_REGULAR' : 'REGULAR']);
        }
        foreach($units as $i=>$unit){$identity=$isUnit?($unit['plate_number']??null):($unit['person_name']??null);if(blank($identity))throw ValidationException::withMessages(["units.$i.".($isUnit?'plate_number':'person_name')=>($isUnit?'Nomor plat':'Nama orang').' wajib diisi.']);}
        if ($parent) {
            $this->requiredCodes($request, $reqs, $parent);
            $allowed=collect($parent->resubmission_subject_ids?:$parent->units->pluck('id'))->all();foreach($units as $i=>$unit)if(!in_array($unit['source_unit_id']??null,$allowed,true))throw ValidationException::withMessages(["units.$i.source_unit_id"=>'Orang/unit tidak termasuk permintaan koordinator.']);
        }
        foreach ($units as $i => $unit) {
            $files = collect($request->file("units.$i.documents", []))->filter();
            $renewalCode=match($request->type){'WASKAT'=>'SURAT_SEHAT_VENDOR','IJIN_LINTAS'=>'SURAT_SEHAT','PERMIT_VENDOR_NON_SIMPER'=>'MCU',default=>'SURAT_TUGAS'};
            $required = $parent ? [$renewalCode] : $reqs->where('required', true)->pluck('code')->all();
            foreach ($required as $code) if (! $files->has($code)) throw ValidationException::withMessages(["units.$i.documents.$code" => ($reqs[$code]['name'] ?? $code).' wajib diunggah untuk unit ini.']);
        }
        $submission = DB::transaction(function () use ($request, $reqs, $parent, $units) {
            $now=now();$data=$request->safe()->except(['documents','units']);$data['vendor_name']=$request->user()->vendorCompany?->name??$request->user()->name;if($request->type==='IJIN_LINTAS')$data['service_category']=$request->planning_frequency==='NON_REGULAR'?'NON_REGULAR':'REGULAR';
            $s=VendorSubmission::create([...$data,'parent_submission_id'=>$parent?->id,'resubmission_subject_ids'=>$units->pluck('source_unit_id')->filter()->values()->all()?:null,'submission_sequence'=>$parent?$parent->submission_sequence+1:1,'submission_number'=>'VND-'.$now->format('Ymd-His').'-'.random_int(100,999),'status'=>SubmissionStatus::SUBMITTED,'submitted_at'=>$now,'created_by'=>$request->user()->id,'site'=>$request->user()->site]);
            foreach($units as $i=>$payload){$old=$parent?->units->firstWhere('id',$payload['source_unit_id']??null);$isUnit=$request->type==='IJIN_LINTAS';$unit=$s->units()->create(['subject_type'=>$isUnit?'UNIT':'PERSON','person_name'=>$isUnit?null:$payload['person_name'],'plate_number'=>$isUnit?strtoupper($payload['plate_number']):null,'unit_order'=>$i+1]);$newFiles=collect($request->file("units.$i.documents",[]))->filter();if($old)foreach($old->documents as $d){if($newFiles->has($d->document_code))continue;$path='private/vendor-documents/'.$s->id.'/'.$unit->id.'/'.$d->file_name;Storage::disk('local')->copy($d->file_path,$path);$unit->documents()->create($d->only(['document_code','document_name','file_name','original_name','mime_type','file_size','is_required'])+['vendor_submission_id'=>$s->id,'file_path'=>$path,'uploaded_at'=>$now]);}foreach($newFiles as $code=>$file){$r=$reqs[$code]??null;if(!$r)continue;$path=$file->store('private/vendor-documents/'.$s->id.'/'.$unit->id,'local');$unit->documents()->create(['vendor_submission_id'=>$s->id,'document_code'=>$code,'document_name'=>$r['name'],'file_name'=>basename($path),'original_name'=>$file->getClientOriginalName(),'file_path'=>$path,'mime_type'=>'application/pdf','file_size'=>$file->getSize(),'is_required'=>$r['required'],'uploaded_at'=>$now]);}}
            if($parent)$parent->update(['resubmission_requested_at'=>null,'resubmission_requested_by'=>null,'resubmission_subject_ids'=>null]);return $s;
        });
        app(PlanningService::class)->register($submission);
        VendorSubmissionCreated::dispatch($submission);
        return back()->with('success',$parent?'Pembaruan Surat Sehat seluruh unit berhasil dikirim.':'Pengajuan multi-unit berhasil dikirim.');
    }

    private function requiredCodes($request, $reqs, ?VendorSubmission $parent): array
    {
        if (! $parent) {
            return $reqs->where('required', true)->pluck('code')->all();
        }
        $u = $request->user();
        abort_unless($parent->created_by === $u->id && $parent->site === $u->site && $parent->type->value === $request->type, 403);
        if (! $parent->resubmission_requested_at) throw ValidationException::withMessages(['parent_submission_id' => 'Pengajuan ulang harus diminta terlebih dahulu oleh koordinator.']);
        $sequence = $parent->submission_sequence + 1;
        if ($request->type === 'WASKAT') { if ($sequence > 3) throw ValidationException::withMessages(['parent_submission_id' => 'Waskat maksimal tiga kali dalam satu rangkaian pengajuan.']); return ['SURAT_SEHAT_VENDOR']; }
        if ($request->type === 'IJIN_LINTAS') return ['SURAT_SEHAT'];
        return [];
    }

    private function persist($request, $reqs, $files, ?VendorSubmission $parent): VendorSubmission
    {
        $now = now();
        $s = VendorSubmission::create([...$request->safe()->except('documents'), 'parent_submission_id' => $parent?->id, 'submission_sequence' => $parent ? $parent->submission_sequence + 1 : 1, 'submission_number' => 'VND-'.$now->format('Ymd-His').'-'.random_int(100, 999), 'status' => SubmissionStatus::SUBMITTED, 'submitted_at' => $now, 'created_by' => $request->user()->id, 'site' => $request->user()->site]);
        if ($parent) {
            foreach ($parent->documents as $document) {
                if ($files->has($document->document_code)) continue;
                $path = 'private/vendor-documents/'.$s->id.'/'.$document->file_name;
                Storage::disk('local')->copy($document->file_path, $path);
                $s->documents()->create($document->only(['document_code', 'document_name', 'file_name', 'original_name', 'mime_type', 'file_size', 'is_required']) + ['file_path' => $path, 'uploaded_at' => $now]);
            }
        }
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
