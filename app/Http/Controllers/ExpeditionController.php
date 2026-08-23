<?php

namespace App\Http\Controllers;

use App\Events\ExpeditionReportCreated;
use App\Http\Requests\StoreExpeditionReportRequest;
use App\Models\ExpeditionReport;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpeditionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Expedition/Index', ['vehicleTypes' => ['Fuso', 'Colt Diesel', 'Trailer', 'Dump Truck', 'Pickup', 'Light Vehicle', 'Lainnya']]);
    }

    public function store(StoreExpeditionReportRequest $request): RedirectResponse
    {
        $now = now();
        $report = ExpeditionReport::create([...$request->validated(), 'report_number' => 'EXP-'.$now->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT), 'arrived_at' => $now, 'created_by' => $request->user()->id, 'site' => $request->user()->site]);
        ExpeditionReportCreated::dispatch($report);

        return back()->with('success', 'Laporan kedatangan berhasil dikirim.');
    }
}
