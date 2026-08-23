<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExpeditionReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['arrived_at' => 'datetime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
