<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorCompany extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function expeditionRequests()
    {
        return $this->hasMany(ExpeditionRequest::class);
    }
}
