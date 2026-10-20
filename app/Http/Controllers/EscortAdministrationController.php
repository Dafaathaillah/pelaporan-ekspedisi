<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EscortAdministrationController extends Controller
{
    public function vendorIndex(Request $request): Response
    {
        $reports = ExpeditionReport::query()
            ->where('site', $request->user()->site)
            ->where('vendor_user_id', $request->user()->id)
            ->where('escort_status', 'CLOSED')
            ->orderByDesc('escort_closed_at')->paginate(10);

        return Inertia::render('Vendor/EscortAdministration', ['reports' => $reports]);
    }

    public function financeIndex(Request $request): Response
    {
        abort_unless($request->user()->role === UserRole::FINANCE, 403);
        $reports = ExpeditionReport::query()
            ->where('site', $request->user()->site)
            ->whereNotNull('vendor_user_id')
            ->where('escort_status', 'CLOSED')
            ->orderByDesc('escort_closed_at')->paginate(10);

        return Inertia::render('Finance/EscortAdministration', ['reports' => $reports]);
    }

    public function upload(Request $request, ExpeditionReport $report, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['invoice', 'spb'], true), 404);
        abort_unless($request->user()->role === UserRole::VENDOR && $report->vendor_user_id === $request->user()->id && $report->site === $request->user()->site, 403);
        abort_unless($report->escort_status === 'CLOSED', 422, 'Dokumen administrasi hanya dapat diunggah setelah escort selesai.');

        $request->validate(['scan' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $column = $kind === 'invoice' ? 'invoice_scan_path' : 'spb_scan_path';
        $oldPath = $report->{$column};
        $path = $request->file('scan')->store('private/escort-administration/'.$report->id, 'local');
        try {
            $report->update([$column => $path, $kind.'_uploaded_at' => now()]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', strtoupper($kind).' berhasil diunggah.');
    }

    public function download(Request $request, ExpeditionReport $report, string $kind)
    {
        abort_unless(in_array($kind, ['invoice', 'spb'], true), 404);
        abort_unless($report->site === $request->user()->site, 403);
        if ($kind === 'invoice') {
            abort_unless($request->user()->role === UserRole::FINANCE, 403);
        } else {
            abort_unless($request->user()->role === UserRole::FINANCE || ($request->user()->role === UserRole::VENDOR && $report->vendor_user_id === $request->user()->id), 403);
        }
        $path = $report->{$kind === 'invoice' ? 'invoice_scan_path' : 'spb_scan_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, strtoupper($kind).'-'.$report->report_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
