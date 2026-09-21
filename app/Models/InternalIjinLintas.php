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

    public function escortReports()
    {
        return $this->hasMany(ExpeditionReport::class, 'internal_ijin_lintas_id');
    }

    public function getOperationalStatusAttribute(): string
    {
        $reports = $this->relationLoaded('escortReports') ? $this->escortReports : $this->escortReports()->get();

        if ($reports->isNotEmpty() && $reports->every(fn ($report) => $report->escort_status === 'CLOSED')) {
            return 'CLOSED';
        }

        return $reports->contains(fn ($report) => $report->escort_status === 'PROCESS') ? 'PROCESS' : 'WAITING';
    }
}
