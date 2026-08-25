<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.manage') ?? false;
    }

    public function rules(): array
    {
        $managedUser = $this->route('managedUser');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($managedUser)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($managedUser)],
            'password' => [$managedUser ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'vendor_company_id' => ['nullable', 'required_if:role,vendor', 'integer', 'exists:vendor_companies,id'],
            'hull_number' => ['nullable', 'required_if:role,driver', 'string', 'max:30', Rule::unique('users')->where(fn ($query) => $query->where('site', $this->input('site')))->ignore($managedUser)],
            'site' => ['required', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(['coordinator.expedition', 'coordinator.vendor', 'users.manage'])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan oleh pengguna lain. Silakan gunakan username yang berbeda.',
            'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
            'password.min' => 'Password minimal harus terdiri dari 8 karakter.',
            'hull_number.required_if' => 'Nomor lambung wajib diisi untuk akun driver.',
            'hull_number.unique' => 'Nomor lambung sudah digunakan driver lain pada site ini.',
            'permissions.*.in' => 'Pilihan akses modul tidak valid.',
        ];
    }
}
