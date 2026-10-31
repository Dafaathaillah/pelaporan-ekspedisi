<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EscortGoNoGo extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $appends = ['result'];

    protected function casts(): array
    {
        return ['units' => 'array', 'items' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'started_at' => 'datetime', 'revision' => 'integer'];
    }

    protected function result(): Attribute
    {
        return Attribute::get(fn () => $this->status === 'PENDING' ? 'PENDING' : ($this->status === 'APPROVED' && $this->driver_decision === 'LAIK' ? 'GO' : 'NO_GO'));
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
