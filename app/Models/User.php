<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'vendor_company_id',
        'site',
        'is_active',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions;

        if ($permissions === null) {
            $permissions = match ($this->role) {
                UserRole::COORDINATOR => ['coordinator.expedition', 'coordinator.vendor', 'users.manage'],
                UserRole::VENDOR => ['vendor.submission'],
                UserRole::EXPEDITION => ['expedition.report'],
            };
        }

        return in_array($permission, $permissions, true);
    }

    public function homePath(): string
    {
        if ($this->role === UserRole::COORDINATOR) {
            if ($this->hasPermission('coordinator.expedition')) {
                return '/coordinator';
            }
            if ($this->hasPermission('coordinator.vendor')) {
                return '/coordinator/submissions/VISITOR';
            }
            if ($this->hasPermission('users.manage')) {
                return '/coordinator/users';
            }
        }

        return $this->role === UserRole::VENDOR ? '/vendor' : '/expedition';
    }

    public function expeditionReports()
    {
        return $this->hasMany(ExpeditionReport::class, 'created_by');
    }

    public function vendorSubmissions()
    {
        return $this->hasMany(VendorSubmission::class, 'created_by');
    }

    public function vendorCompany()
    {
        return $this->belongsTo(VendorCompany::class);
    }
}
