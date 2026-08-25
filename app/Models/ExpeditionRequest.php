<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExpeditionRequest extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function vendorCompany()
    {
        return $this->belongsTo(VendorCompany::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(VendorSubmission::class);
    }
}
