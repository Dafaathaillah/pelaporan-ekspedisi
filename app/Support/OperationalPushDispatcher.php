<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Events\ExpeditionEscortUpdated;
use App\Events\ExpeditionReportCreated;
use App\Events\ExpeditionRequestCreated;
use App\Events\ExpeditionRequestUpdated;
use App\Events\PlanningUpdated;
use App\Events\VendorResubmissionRequested;
use App\Events\VendorSubmissionCreated;
use App\Events\VendorSubmissionStatusChanged;
use App\Events\VendorSubmissionUpdated;
use App\Models\User;
use App\Notifications\OperationalPushNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OperationalPushDispatcher
{
    public function dispatch(object $event): void
    {
        try {
            [$users, $title, $body, $url, $tag] = $this->resolve($event);

            $users = $users->filter()->unique('id')->values();
            if ($users->isEmpty()) {
                return;
            }

            $users->each(fn (User $user) => $user->loadMissing('pushSubscriptions'));
            $users = $users->filter(fn (User $user) => $user->pushSubscriptions->isNotEmpty())->values();

            if ($users->isEmpty()) {
                return;
            }

            Notification::send(
                $users,
                new OperationalPushNotification($title, $body, $url, $tag),
            );
        } catch (Throwable $exception) {
            Log::warning('Web Push unavailable; primary operation completed.', [
                'event' => $event::class,
                'exception' => $exception::class,
            ]);
        }
    }

    private function resolve(object $event): array
    {
        return match (true) {
            $event instanceof ExpeditionRequestCreated => $this->requestCreated($event),
            $event instanceof ExpeditionRequestUpdated => $this->requestUpdated($event),
            $event instanceof VendorSubmissionCreated,
            $event instanceof VendorSubmissionUpdated => $this->submissionReceived($event),
            $event instanceof VendorSubmissionStatusChanged => $this->submissionStatus($event),
            $event instanceof VendorResubmissionRequested => $this->resubmissionRequested($event),
            $event instanceof PlanningUpdated => $this->planningUpdated($event),
            $event instanceof ExpeditionReportCreated => $this->reportCreated($event),
            $event instanceof ExpeditionEscortUpdated => $this->escortUpdated($event),
            default => [collect(), '', '', '/', 'unknown'],
        };
    }

    private function requestCreated(ExpeditionRequestCreated $event): array
    {
        $item = $event->expeditionRequest;
        $users = $this->siteUsers($item->site, [UserRole::COORDINATOR, UserRole::DEVELOPER, UserRole::PURCHASING_ADMIN])
            ->merge($item->vendorCompany->users);

        return [$users, 'Request Ekspedisi Baru', "{$item->request_number} untuk {$item->vendorCompany->name}", '/dashboard', 'request-'.$item->id];
    }

    private function requestUpdated(ExpeditionRequestUpdated $event): array
    {
        $item = $event->expeditionRequest;
        $users = $this->siteUsers($item->site, [UserRole::COORDINATOR, UserRole::DEVELOPER, UserRole::PURCHASING_ADMIN])
            ->merge($item->vendorCompany->users);

        return [$users, 'Request Ekspedisi Diperbarui', "PR/PO {$item->request_number} telah diperbarui.", '/dashboard', 'request-'.$item->id];
    }

    private function submissionReceived(VendorSubmissionCreated|VendorSubmissionUpdated $event): array
    {
        $item = $event->submission;

        return [$this->coordinators($item->site), 'Pengajuan Vendor Masuk', "{$item->vendor_name} mengirim {$item->type->label()}.", '/coordinator/submissions/'.$item->type->value, 'submission-'.$item->id];
    }

    private function submissionStatus(VendorSubmissionStatusChanged $event): array
    {
        $item = $event->submission;
        $users = $this->coordinators($item->site)->push($item->creator);

        return [$users, 'Status Pengajuan Diperbarui', "{$item->submission_number}: {$item->status->value}", '/dashboard', 'submission-'.$item->id];
    }

    private function resubmissionRequested(VendorResubmissionRequested $event): array
    {
        $item = $event->submission;

        return [collect([$item->creator]), 'Surat Sehat Baru Diperlukan', $item->resubmission_request_reason ?: "Perbarui dokumen {$item->submission_number}.", '/vendor/submissions/'.$item->id, 'health-letter-'.$item->id];
    }

    private function planningUpdated(PlanningUpdated $event): array
    {
        $item = $event->planningItem;
        $submission = $item->submission;
        $users = $this->coordinators($submission->site)
            ->merge($this->siteUsers($submission->site, [UserRole::EXPEDITION]))
            ->push($submission->creator);

        return [$users, 'Planning Escort Diperbarui', "{$submission->vendor_name}: ".($item->planned_date?->format('d/m/Y') ?? 'belum dijadwalkan'), '/dashboard', 'planning-'.$item->id];
    }

    private function reportCreated(ExpeditionReportCreated $event): array
    {
        $item = $event->report;
        $users = $this->coordinators($item->site)->merge($this->siteUsers($item->site, [UserRole::DRIVER]));

        return [$users, 'Kedatangan Ekspedisi Baru', "{$item->expedition_company} · {$item->vehicle_plate}", '/coordinator', 'report-'.$item->id];
    }

    private function escortUpdated(ExpeditionEscortUpdated $event): array
    {
        $item = $event->report;
        $users = $this->coordinators($item->site)->merge($this->siteUsers($item->site, [UserRole::DRIVER]));

        return [$users, 'Status Escort Diperbarui', "{$item->vehicle_plate}: {$item->escort_status}", '/coordinator', 'escort-'.$item->id];
    }

    private function coordinators(string $site): Collection
    {
        return $this->siteUsers($site, [UserRole::COORDINATOR, UserRole::DEVELOPER])
            ->filter(fn (User $user) => $user->hasPermission('coordinator.expedition') || $user->hasPermission('coordinator.vendor') || $user->hasPermission('coordinator.requests') || $user->hasPermission('coordinator.planning'));
    }

    private function siteUsers(string $site, array $roles): Collection
    {
        return User::query()
            ->where('site', $site)
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles))
            ->get();
    }
}
