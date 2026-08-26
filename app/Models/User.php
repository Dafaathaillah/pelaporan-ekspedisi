<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

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
        'hull_number',
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
        if ($this->role === UserRole::DEVELOPER) {
            return true;
        }

        $permissions = $this->permissions;

        if ($permissions === null) {
            $permissions = match ($this->role) {
                UserRole::DEVELOPER => ['*'],
                UserRole::COORDINATOR => ['coordinator.expedition', 'coordinator.requests', 'coordinator.vendor', 'coordinator.planning', 'users.manage', 'vendors.manage'],
                UserRole::VENDOR => ['vendor.submission'],
                UserRole::EXPEDITION => ['expedition.report'],
                UserRole::DRIVER => ['driver.escort'],
                UserRole::PURCHASING_ADMIN => ['purchasing.requests'],
            };
        }

        return in_array($permission, $permissions, true);
    }

    public function homePath(): string
    {
        if (in_array($this->role, [UserRole::DEVELOPER, UserRole::COORDINATOR], true)) {
            if ($this->hasPermission('coordinator.expedition')) {
                return '/coordinator';
            }
            if ($this->hasPermission('coordinator.vendor')) {
                return '/coordinator/submissions/VISITOR';
            }
            if ($this->hasPermission('coordinator.requests')) {
                return '/coordinator/expedition-requests';
            }
            if ($this->hasPermission('coordinator.planning')) {
                return '/coordinator/planning';
            }
            if ($this->hasPermission('users.manage')) {
                return '/coordinator/users';
            }
            if ($this->hasPermission('vendors.manage')) {
                return '/coordinator/vendors';
            }
        }

        return match ($this->role) {
            UserRole::VENDOR => '/vendor',
            UserRole::DRIVER => '/driver/escorts',
            UserRole::PURCHASING_ADMIN => '/purchasing/requests',
            default => '/expedition',
        };
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
