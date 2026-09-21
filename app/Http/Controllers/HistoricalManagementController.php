<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ExpeditionReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HistoricalManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeRole($request);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:150'], 'document_type' => ['nullable', 'in:all,blood-pressure,p5m,p2h'],
            'status' => ['nullable', 'in:all,complete,incomplete'], 'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $from = $filters['date_from'] ?? today()->toDateString();
        $to = $filters['date_to'] ?? today()->toDateString();
        $type = $filters['document_type'] ?? 'all';
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $base = ExpeditionReport::query()
            ->where('site', $request->user()->site)
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $sub) => $sub
                ->where('report_number', 'like', '%'.$request->search.'%')
                ->orWhere('vehicle_plate', 'like', '%'.$request->search.'%')
                ->orWhere('driver_name', 'like', '%'.$request->search.'%')
                ->orWhere('expedition_company', 'like', '%'.$request->search.'%')));

        $this->applyArtifactDateFilter($base, $from, $to, $type);
        $base->when(($filters['status'] ?? 'all') === 'complete', fn (Builder $query) => $query
            ->whereNotNull('blood_pressure_photo_path')->whereNotNull('p5m_photo_path')->whereNotNull('p2h_photo_path'))
            ->when(($filters['status'] ?? 'all') === 'incomplete', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub
                ->whereNull('blood_pressure_photo_path')->orWhereNull('p5m_photo_path')->orWhereNull('p2h_photo_path')));

        $statsQuery = ExpeditionReport::query()->where('site', $request->user()->site);
        $this->applyArtifactDateFilter($statsQuery, $from, $to, 'all');
        $stats = [
            'blood_pressure' => (clone $statsQuery)->whereNotNull('blood_pressure_photo_path')->count(),
            'p5m' => (clone $statsQuery)->whereNotNull('p5m_photo_path')->count(),
            'p2h' => (clone $statsQuery)->whereNotNull('p2h_photo_path')->count(),
        ];

        $items = $base
            ->orderByRaw("COALESCE(escort_started_at, blood_pressure_photo_captured_at, arrived_at) {$direction}")
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Historical/Index', [
            'items' => $items, 'stats' => $stats,
            'filters' => [...$filters, 'date_from' => $from, 'date_to' => $to, 'document_type' => $type, 'status' => $filters['status'] ?? 'all', 'direction' => $direction, 'per_page' => $perPage],
        ]);
    }

    public function photo(Request $request, ExpeditionReport $report, string $kind)
    {
        $this->authorizeRole($request);
        abort_unless($report->site === $request->user()->site, 403);
        $path = match ($kind) {
            'blood-pressure' => $report->blood_pressure_photo_path,
            'p5m' => $report->p5m_photo_path,
            'p2h' => $report->p2h_photo_path,
            default => abort(404),
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function applyArtifactDateFilter(Builder $query, string $from, string $to, string $type): void
    {
        $query->where(function (Builder $dates) use ($from, $to, $type) {
            if (in_array($type, ['all', 'blood-pressure'], true)) {
                $dates->orWhere(fn (Builder $sub) => $sub->whereNotNull('blood_pressure_photo_path')->whereBetween('blood_pressure_photo_captured_at', [$from.' 00:00:00', $to.' 23:59:59']));
            }
            if (in_array($type, ['all', 'p5m'], true)) {
                $dates->orWhere(fn (Builder $sub) => $sub->whereNotNull('p5m_photo_path')->whereBetween('escort_started_at', [$from.' 00:00:00', $to.' 23:59:59']));
            }
            if (in_array($type, ['all', 'p2h'], true)) {
                $dates->orWhere(fn (Builder $sub) => $sub->whereNotNull('p2h_photo_path')->whereBetween('arrived_at', [$from.' 00:00:00', $to.' 23:59:59']));
            }
        });
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless(in_array($request->user()->role, [UserRole::COORDINATOR, UserRole::DEVELOPER, UserRole::PURCHASING_ADMIN], true), 403);
    }
}
