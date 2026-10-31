<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\EscortGoNoGo;
use App\Models\User;
use App\Services\EscortGoNoGoService;
use App\Services\EscortReadiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait PreparesApprovedGoNoGo
{
    // Existing close/readiness tests need an explicit, valid GO prerequisite.
    private function prepareApprovedGoNoGo(User $driver, array|Collection $reports, ?UploadedFile $photo = null): ?EscortGoNoGo
    {
        $reports = collect($reports)->map(fn ($report) => $report->fresh());
        if ($reports->contains(fn ($report) => $report->site !== $driver->site || $report->escort_status !== 'WAITING' || $report->escort_driver_id || $report->go_no_go_id)) {
            return null;
        }
        try {
            $reports->each(fn ($report) => app(EscortReadiness::class)->ensure($report));
        } catch (ValidationException) {
            return null;
        }
        $service = app(EscortGoNoGoService::class);
        $form = $service->submit($driver, ['report_ids' => $reports->pluck('id')->all(), 'escorter_name' => 'Escort Fixture', 'nik' => '8123', 'driver_decision' => 'LAIK', 'items' => array_fill(0, 11, ['condition' => 'SESUAI', 'note' => '', 'evidence' => ''])], $photo ?? UploadedFile::fake()->image('approved-p5m.jpg'));
        $reviewer = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => $driver->site]);
        $service->review($form, $reviewer, ['status' => 'APPROVED']);

        return $form->fresh();
    }
}
