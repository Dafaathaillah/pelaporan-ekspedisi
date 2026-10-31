<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
@page{margin:22px 26px}body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#111;margin:0}table{width:100%;border-collapse:collapse}td,th{border:1px solid #333;padding:4px;vertical-align:top;word-wrap:break-word}th,.section,.label{background:#a7d8e9;font-weight:bold}.brand{font-weight:bold;font-size:12px;color:#111}.ppa{color:#d71920;font-size:19px;margin-right:6px}h1{text-align:center;background:#a7d8e9;border:1px solid #333;margin:6px 0 0;padding:6px;font-size:14px}.intro{border:1px solid #333;border-top:0;padding:5px;font-style:italic}.section{border:1px solid #333;padding:5px}.items td{padding:4px;line-height:1.3}.items tr{page-break-inside:avoid}.center{text-align:center}.small{font-size:7px}.result{border:2px solid #08752c;color:#065f46;background:#ecfdf5;padding:5px;text-align:center;font-size:11px;font-weight:bold;margin:6px 0}.no-go{border-color:#b91c1c;color:#991b1b;background:#fef2f2}.signatures td{height:76px}.approval-stamp{width:62px;height:62px}.muted{color:#555}.space{margin-top:6px}
</style></head><body>
<table><tr><td style="border:0;padding:0"><span class="ppa">PPA</span><span class="brand">PT PUTRA PERKASA ABADI</span></td><td style="border:0;text-align:right;font-size:8px">PPA-BIB-F-SHE-78B</td></tr></table>
<h1>CHECKLIST KESIAPAN PENGAWALAN UNIT</h1>
<div class="intro">Form ini digunakan untuk memonitor pelaksanaan kesiapan pengawalan Escort Commander unit.</div>
<div class="section">A. IDENTITAS</div>
<table><tr><td class="label" width="19%">Departemen</td><td width="32%">{{ $form->department }}</td><td class="label" width="18%">Hari/Tanggal</td><td width="31%">{{ $form->submitted_at->copy()->timezone('Asia/Makassar')->locale('id')->translatedFormat('l, d F Y') }}</td></tr>
<tr><td class="label">Nama Escort Commander</td><td>{{ $form->escorter_name }}</td><td class="label">NO POL Unit Dikawal</td><td>{{ implode(', ', array_column($form->units, 'vehicle_plate')) }}</td></tr>
<tr><td class="label">NIK</td><td>{{ $form->nik }}</td><td class="label">Nama Driver Dikawal</td><td>{{ implode(', ', array_column($form->units, 'driver_name')) }}</td></tr>
<tr><td class="label">Jabatan</td><td>{{ $form->position }}</td><td class="label">Lokasi Tujuan</td><td>{{ $form->destination }}</td></tr></table>
<div class="section space">B. ITEM YANG DIPERIKSA <span class="small">Sesuai (✓), Tidak Sesuai (X), atau Nihil (NA)</span></div>
<table class="items"><thead><tr><th width="4%">NO</th><th width="45%">ITEM WAJIB DIPERIKSA</th><th width="10%">TERLAKSANA</th><th width="20%">BUKTI / LAMPIRAN DOKUMENTASI</th><th width="21%">KETERANGAN</th></tr></thead><tbody>
@foreach($checklist as $index => $label)
<tr><td class="center">{{ $index + 1 }}</td><td>{{ $label }}</td><td class="center">{{ match($form->items[$index]['condition'] ?? '') { 'SESUAI' => '✓', 'TIDAK_SESUAI' => 'X', 'NA' => 'NA', default => '-' } }}</td><td>{{ $form->items[$index]['evidence'] ?? '-' }}</td><td>{{ $form->items[$index]['note'] ?? '-' }}</td></tr>
@endforeach
<tr><td colspan="2" class="center"><b>Apakah unit layak dilakukan pengawalan?</b><br><span class="small">Keputusan Kelayakan (Diisi Driver)</span></td><td colspan="3"><b>{{ $form->driver_decision === 'LAIK' ? '☑ Layak     ☐ Tidak Layak' : '☐ Layak     ☑ Tidak Layak' }}</b></td></tr>
</tbody></table>
<div class="result {{ $form->result === 'GO' ? '' : 'no-go' }}">{{ $form->result === 'GO' ? 'GO — DISETUJUI UNTUK MULAI ESCORT' : ($form->status === 'PENDING' ? 'MENUNGGU APPROVAL — BELUM BOLEH MULAI ESCORT' : 'NO GO — TIDAK BOLEH MULAI ESCORT') }}</div>
<div class="section">C. PENGESAHAN</div>
<table class="signatures"><thead><tr><th width="20%">PEMERIKSA</th><th width="28%">NAMA</th><th width="23%">TANGGAL</th><th width="29%">PENGESAHAN</th></tr></thead><tbody>
<tr><td class="label">Escort Commander</td><td>{{ $form->escorter_name }}</td><td>{{ $form->submitted_at->copy()->timezone('Asia/Makassar')->format('d/m/Y H:i:s') }} WITA</td><td class="small">Diajukan secara elektronik<br>Keputusan driver: {{ $form->driver_decision === 'LAIK' ? 'Layak' : 'Tidak Layak' }}</td></tr>
<tr><td class="label">Tim Pengesahan / Coordinator</td><td>{{ $form->reviewer_name ?: 'Menunggu persetujuan' }}</td><td>{{ $form->reviewed_at?->copy()->timezone('Asia/Makassar')->format('d/m/Y H:i:s') ?: '-' }}{{ $form->reviewed_at ? ' WITA' : '' }}</td><td class="center">@if($form->status === 'APPROVED')@php($approvalStampDataUri = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/approval-ppa.png'))))@include('pdf.partials.approval-stamp')<br><span class="small">Review APPROVED · Hasil: {{ $form->result }}</span>@else{{ $form->status }}@endif</td></tr>
</tbody></table>
@if($form->rejection_reason)<p><b>Alasan dikembalikan:</b> {{ $form->rejection_reason }}</p>@endif
@if($form->notes)<p><b>Catatan driver:</b> {{ $form->notes }}</p>@endif
<p class="small muted">ID {{ $form->id }} · Revisi {{ $form->revision }} · {{ count($form->units) }} unit · Status review {{ $form->status }}<br>Persetujuan pemeriksaan tidak mengizinkan escort apabila keputusan driver Tidak Layak. Foto P5M tersimpan sebagai bukti pengajuan.</p>
</body></html>
