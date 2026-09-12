<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectPlanning extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean', 'locked_at' => 'datetime'];
    }

    public function vendorCompany()
    {
        return $this->belongsTo(VendorCompany::class);
    }

    public function units()
    {
        return $this->hasMany(DirectPlanningUnit::class)->orderBy('position');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
