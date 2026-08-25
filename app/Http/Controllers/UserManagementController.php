<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Models\VendorCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->toString(), ['name', 'username', 'role', 'site', 'hull_number', 'is_active', 'created_at'], true)
            ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), [10, 25, 50, 100], true) ? $request->integer('per_page') : 10;

        $users = User::query()
            ->when($request->user()->role !== UserRole::DEVELOPER, fn ($query) => $query->where('role', '!=', UserRole::DEVELOPER->value))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('username', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->orderBy($sort, $direction)->paginate($perPage)->withQueryString();

        return Inertia::render('Coordinator/Users', [
            'users' => $users,
            'filters' => $request->only('search', 'role', 'status', 'sort', 'direction', 'per_page'),
            'permissionOptions' => [
                ['value' => 'coordinator.expedition', 'label' => 'Monitoring Ekspedisi', 'description' => 'Dashboard kedatangan, detail escort, filter, dan ekspor data.'],
                ['value' => 'coordinator.requests', 'label' => 'Request Ekspedisi', 'description' => 'Membuat dan memonitor request ekspedisi untuk vendor.'],
                ['value' => 'coordinator.vendor', 'label' => 'Pengajuan Vendor', 'description' => 'Review Visitor, Waskat, Permit Non Simper, dan Ijin Lintas.'],
                ['value' => 'coordinator.planning', 'label' => 'Planning Escort', 'description' => 'Mengatur agenda escort, tanggal, dan evidence planning.'],
                ['value' => 'users.manage', 'label' => 'Manajemen Pengguna', 'description' => 'Membuat akun serta mengatur role dan akses modul.'],
                ['value' => 'vendors.manage', 'label' => 'Manajemen Vendor', 'description' => 'Mengelola master perusahaan vendor dan statusnya.'],
            ],
            'vendorCompanies' => VendorCompany::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'canManageDevelopers' => $request->user()->role === UserRole::DEVELOPER,
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        abort_if($request->validated('role') === UserRole::DEVELOPER->value && $request->user()->role !== UserRole::DEVELOPER, 403);
        User::create($this->payload($request));

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(SaveUserRequest $request, User $managedUser): RedirectResponse
    {
        abort_if($managedUser->role === UserRole::DEVELOPER && $request->user()->role !== UserRole::DEVELOPER, 403);
        abort_if($request->validated('role') === UserRole::DEVELOPER->value && $request->user()->role !== UserRole::DEVELOPER, 403);
        $payload = $this->payload($request);
        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        if ($managedUser->is($request->user())) {
            $payload['role'] = $managedUser->role === UserRole::DEVELOPER ? UserRole::DEVELOPER->value : UserRole::COORDINATOR->value;
            $payload['is_active'] = true;
            $payload['permissions'] = $managedUser->role === UserRole::DEVELOPER ? ['*'] : array_values(array_unique([...$payload['permissions'], 'users.manage']));
        }

        $managedUser->update($payload);

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    private function payload(SaveUserRequest $request): array
    {
        $data = $request->validated();
        if ($data['role'] === UserRole::VENDOR->value) {
            $company = VendorCompany::findOrFail($data['vendor_company_id']);
            $data['name'] = $company->name;
        } else {
            $data['vendor_company_id'] = null;
        }
        if ($data['role'] !== UserRole::DRIVER->value) {
            $data['hull_number'] = null;
        }
        $data['permissions'] = match ($data['role']) {
            UserRole::DEVELOPER->value => ['*'],
            UserRole::EXPEDITION->value => ['expedition.report'],
            UserRole::DRIVER->value => ['driver.escort'],
            UserRole::PURCHASING_ADMIN->value => ['purchasing.requests'],
            UserRole::VENDOR->value => ['vendor.submission'],
            default => array_values(array_unique($data['permissions'] ?? [])),
        };

        return $data;
    }
}
