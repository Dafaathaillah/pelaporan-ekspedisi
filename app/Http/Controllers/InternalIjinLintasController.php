<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternalIjinLintasRequest;
use App\Models\ExpeditionReport;
use App\Models\InternalIjinLintas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalIjinLintasController extends Controller
{
    public function store(StoreInternalIjinLintasRequest $request)
    {
        DB::transaction(function () use ($request) {
            $permit = InternalIjinLintas::create([
                'title' => $request->validated('title'),
                'escort_date' => $request->validated('escort_date'),
                'unit_count' => $request->validated('unit_count'),
                'vehicle_plates' => $request->validated('plates'),
                'site' => $request->user()->site,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($request->validated('plates') as $index => $plate) {
                ExpeditionReport::create([
                    'internal_ijin_lintas_id' => $permit->id,
                    'report_number' => 'INT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)).'-'.($index + 1),
                    'expedition_company' => 'Internal', 'driver_name' => 'Driver Internal', 'driver_phone' => '-',
                    'vehicle_plate' => $plate, 'vehicle_type' => 'Light Vehicle', 'sender_vendor' => 'Internal',
                    'destination' => $permit->title, 'arrived_at' => now(), 'created_by' => $request->user()->id,
                    'site' => $request->user()->site,
                ]);
            }
        });

        return back()->with('success', 'Izin Lintas internal berhasil dibuat dan masuk ke Planning Escort.');
    }

    public function move(Request $request, InternalIjinLintas $internalIjinLintas)
    {
        abort_unless($internalIjinLintas->site === $request->user()->site, 403);
        $data = $request->validate(['escort_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonth()->toDateString()]]);
        $internalIjinLintas->update(['escort_date' => $data['escort_date'], 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Tanggal Izin Lintas internal berhasil dipindahkan.');
    }
}
