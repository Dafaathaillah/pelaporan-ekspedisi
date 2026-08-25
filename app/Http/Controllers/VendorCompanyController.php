<?php

namespace App\Http\Controllers;

use App\Models\VendorCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VendorCompanyController extends Controller
{
    public function index(Request $request)
    {
        $sort = in_array($request->sort, ['name', 'abbreviation', 'category', 'is_active', 'created_at'], true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 25;
        $base = VendorCompany::query();
        $vendors = (clone $base)->when($request->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'like', '%'.$v.'%')->orWhere('abbreviation', 'like', '%'.$v.'%')))->when($request->category, fn ($q, $v) => $q->where('category', $v))->when($request->status !== null && $request->status !== '', fn ($q) => $q->where('is_active', $request->status === 'active'))->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Coordinator/Vendors', ['vendors' => $vendors, 'stats' => ['total' => (clone $base)->count(), 'regular' => (clone $base)->where('category', 'REGULAR')->count(), 'nonRegular' => (clone $base)->where('category', 'NON_REGULAR')->count(), 'active' => (clone $base)->where('is_active', true)->count()], 'filters' => $request->only(['search', 'category', 'status', 'sort', 'direction', 'per_page'])]);
    }

    public function store(Request $request)
    {
        VendorCompany::create($this->validated($request));

        return back()->with('success', 'Master vendor berhasil ditambahkan.');
    }

    public function update(Request $request, VendorCompany $vendorCompany)
    {
        $vendorCompany->update($this->validated($request, $vendorCompany));

        return back()->with('success', 'Master vendor berhasil diperbarui.');
    }

    private function validated(Request $request, ?VendorCompany $vendor = null): array
    {
        $request->merge(['name' => mb_strtoupper(trim((string) $request->name)), 'abbreviation' => mb_strtoupper(trim((string) $request->abbreviation))]);

        return $request->validate(['name' => ['required', 'string', 'max:200', Rule::unique('vendor_companies', 'name')->ignore($vendor)], 'abbreviation' => ['required', 'string', 'max:30', Rule::unique('vendor_companies', 'abbreviation')->ignore($vendor)], 'category' => ['required', Rule::in(['REGULAR', 'NON_REGULAR'])], 'remarks' => ['nullable', 'string', 'max:255'], 'is_active' => ['required', 'boolean']]);
    }
}
