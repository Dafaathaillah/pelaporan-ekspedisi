<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->toString(), ['name', 'username', 'role', 'site', 'is_active', 'created_at'], true)
            ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), [10, 25, 50, 100], true) ? $request->integer('per_page') : 10;

        $users = User::query()
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
                ['value' => 'coordinator.expedition', 'label' => 'Monitoring Ekspedisi', 'description' => 'Dashboard kedatangan dan ekspor data ekspedisi.'],
                ['value' => 'coordinator.vendor', 'label' => 'Monitoring Pengajuan Vendor', 'description' => 'Visitor, Waskat, Permit Non Simper, dan Ijin Lintas.'],
                ['value' => 'users.manage', 'label' => 'Manajemen Pengguna', 'description' => 'Membuat akun serta mengatur role dan akses modul.'],
            ],
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        User::create($this->payload($request));

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(SaveUserRequest $request, User $managedUser): RedirectResponse
    {
        $payload = $this->payload($request);
        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        if ($managedUser->is($request->user())) {
            $payload['role'] = UserRole::COORDINATOR->value;
            $payload['is_active'] = true;
            $payload['permissions'] = array_values(array_unique([...$payload['permissions'], 'users.manage']));
        }

        $managedUser->update($payload);

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    private function payload(SaveUserRequest $request): array
    {
        $data = $request->validated();
        $data['permissions'] = match ($data['role']) {
            UserRole::EXPEDITION->value => ['expedition.report'],
            UserRole::VENDOR->value => ['vendor.submission'],
            default => array_values(array_unique($data['permissions'] ?? [])),
        };

        return $data;
    }
}
