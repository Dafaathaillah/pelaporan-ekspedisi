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
            'site' => ['required', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(['coordinator.expedition', 'coordinator.vendor', 'users.manage'])],
        ];
    }
}
