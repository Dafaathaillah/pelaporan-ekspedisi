<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InternalIjinLintas extends Model
{
    use HasUuids;

    protected $table = 'internal_ijin_lintas';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['escort_date' => 'date:Y-m-d', 'vehicle_plates' => 'array'];
    }
}
